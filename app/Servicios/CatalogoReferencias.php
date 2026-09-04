<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * CatalogoReferencias
 *
 * Responsabilidad: administrar el catálogo de referencias bancarias que el
 * área administrativa carga en un solo paquete ZIP —el CSV de la DEC y un PDF
 * por referencia— y la entrega de una referencia a una persona.
 *
 * Una referencia se entrega una sola vez: al asignarse nace el PAGO de la
 * solicitud con la referencia y su PDF, y el renglón del catálogo queda ligado
 * a ese PAGO. REFERENCIA_BANCARIA.REBA_ID_PAGO es único, de modo que la base
 * rechaza cualquier intento de repartir dos veces la misma referencia.
 */
class CatalogoReferencias
{
    /** Carpeta del disco 'referencias' donde viven los PDF del catálogo. */
    public const CARPETA_FORMATOS = 'catalogo';

    /**
     * Tope de lo que puede pesar el paquete ya descomprimido.
     *
     * Un ZIP de 50 MB puede descomprimir a varios gigabytes, y esto corre
     * dentro del request. El tamaño se consulta en la tabla del ZIP, antes de
     * leer un solo byte.
     */
    private const MAX_DESCOMPRIMIDO = 300 * 1024 * 1024;

    /**
     * Qué cuenta como «tiene su formato PDF», en SQL.
     *
     * Vive en un solo lugar porque de esto depende que una referencia se pueda
     * entregar: si el contador del tablero y la consulta que reparte usaran
     * criterios distintos, el administrador vería referencias listas que nadie
     * puede obtener.
     */
    private const TIENE_FORMATO = "reba_path IS NOT NULL AND reba_path <> ''";

    /**
     * En qué estado está cada referencia, en SQL.
     *
     * Vive en una sola constante por el mismo motivo que TIENE_FORMATO: la
     * expresión se usa dos veces —como columna del reporte y como filtro— y dos
     * copias acabarían discrepando, de modo que el selector diría una cosa y la
     * columna Estado otra.
     *
     * El orden de los WHEN es la regla de negocio: el comprobante manda sobre
     * la vigencia, así que quien ya pagó no sale como vencido aunque el sello
     * del banco llegue tarde. «Vencida» recoge también las que nadie tomó y
     * caducaron; «Sin asignar» se queda con las libres que siguen vigentes.
     *
     * CURRENT_DATE en lugar de un parámetro porque la expresión se interpola en
     * dos cláusulas distintas y duplicar el binding es más frágil que confiar en
     * una función que PostgreSQL y el SQLite de las pruebas entienden igual.
     */
    private const ESTADO = "CASE
            WHEN cep.esta_estado_pago = 'Completado' THEN 'Validada'
            WHEN cep.esta_estado_pago = 'Declinado' THEN 'Rechazada'
            WHEN pg.pago_comprobante_path IS NOT NULL AND pg.pago_comprobante_path <> '' THEN 'Pagada'
            WHEN rb.reba_vigencia IS NOT NULL AND rb.reba_vigencia < CURRENT_DATE THEN 'Vencida'
            WHEN rb.reba_id_pago IS NOT NULL THEN 'Sin pagar'
            ELSE 'Sin asignar'
        END";

    /**
     * Los estados con los que se acota el reporte: valor de la URL => etiqueta
     * que imprime ESTADO. La misma etiqueta se ofrece en el selector, de modo
     * que lo que se elige y lo que sale en la columna se escriben igual.
     */
    public const ESTADOS_REPORTE = [
        'validadas' => 'Validada',
        'pagadas' => 'Pagada',
        'rechazadas' => 'Rechazada',
        'sin-pagar' => 'Sin pagar',
        'vencidas' => 'Vencida',
        'sin-asignar' => 'Sin asignar',
    ];

    /** Encabezados admitidos en el CSV para cada campo. */
    private const ENCABEZADOS = [
        'referencia' => ['referencia', 'referencia_bancaria', 'referenciabancaria', 'numero', 'no_referencia'],
        'monto' => ['monto', 'importe', 'cantidad'],
        'vigencia' => ['vigencia', 'fecha_vigencia', 'fecha_limite', 'vencimiento'],
        'emision' => ['fecha', 'fecha_emision', 'fecha_de_emision', 'emision', 'expedicion', 'fecha_expedicion'],
    ];

    /**
     * Las cuatro columnas que debe traer el archivo, con el nombre con el que
     * se le reclaman al administrador cuando falta alguna.
     */
    private const OBLIGATORIAS = [
        'emision' => 'fecha de emisión',
        'referencia' => 'referencia',
        'monto' => 'importe',
        'vigencia' => 'vigencia',
    ];

    /**
     * Renglones de membrete que se toleran antes de la tabla.
     *
     * El archivo oficial de la DEC trae siete (UNAM, facultad, división y el
     * título del listado) y el encabezado cae en el octavo. El margen sobra
     * para que agregar un renglón no rompa la carga.
     */
    private const MAX_PREAMBULO = 25;

    public function resumen(): array
    {
        $formato = self::TIENE_FORMATO;

        $conteos = DB::table('referencia_bancaria')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE reba_id_pago IS NULL) AS disponibles')
            ->selectRaw("COUNT(*) FILTER (WHERE {$formato}) AS con_formato")
            ->selectRaw("COUNT(*) FILTER (WHERE reba_id_pago IS NULL AND {$formato}) AS entregables")
            ->first();

        return [
            'total' => (int) ($conteos->total ?? 0),
            'disponibles' => (int) ($conteos->disponibles ?? 0),
            'asignadas' => (int) ($conteos->total ?? 0) - (int) ($conteos->disponibles ?? 0),
            'con_formato' => (int) ($conteos->con_formato ?? 0),
            'entregables' => (int) ($conteos->entregables ?? 0),
        ];
    }

    /**
     * Catálogo completo con la persona a la que se entregó cada referencia.
     *
     * @return Collection<int, array>
     */
    public function catalogo(array $filtros = []): Collection
    {
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $estado = (string) ($filtros['estado'] ?? '');

        $consulta = DB::table('referencia_bancaria as rb')
            ->leftJoin('solicitud as s', 's.soli_id_pago', '=', 'rb.reba_id_pago')
            ->leftJoin('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->select([
                'rb.reba_id_referencia_bancaria',
                'rb.reba_referencia',
                'rb.reba_path',
                'rb.reba_monto',
                'rb.reba_vigencia',
                'rb.reba_fecha_emision',
                'rb.reba_id_pago',
                'rb.reba_fecha_asignacion',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
            ])
            ->orderBy('rb.reba_referencia');

        if ($buscar !== '') {
            $consulta->where(function ($grupo) use ($buscar): void {
                $grupo->where('rb.reba_referencia', 'ilike', '%'.$buscar.'%')
                    ->orWhere('p.pers_curp', 'ilike', '%'.$buscar.'%');
            });
        }

        if ($estado === 'disponible') {
            $consulta->whereNull('rb.reba_id_pago');
        } elseif ($estado === 'asignada') {
            $consulta->whereNotNull('rb.reba_id_pago');
        } elseif ($estado === 'sin-formato') {
            $consulta->whereNull('rb.reba_path');
        }

        return $consulta->get()->map(function (object $fila): array {
            $titular = trim(implode(' ', array_filter([
                $fila->pers_nombre,
                $fila->pers_apellido_paterno,
                $fila->pers_apellido_materno,
            ])));

            return [
                'id' => (int) $fila->reba_id_referencia_bancaria,
                'referencia' => (string) $fila->reba_referencia,
                'tiene_formato' => $fila->reba_path !== null && $fila->reba_path !== '',
                'monto' => $fila->reba_monto === null ? null : (float) $fila->reba_monto,
                'vigencia' => $fila->reba_vigencia,
                'fecha_emision' => $fila->reba_fecha_emision,
                'asignada' => $fila->reba_id_pago !== null,
                'fecha_asignacion' => $fila->reba_fecha_asignacion,
                'titular' => $titular,
                'curp' => (string) ($fila->pers_curp ?? ''),
            ];
        });
    }

    /**
     * El catálogo completo para descargarlo, con el estado de cada referencia.
     *
     * Arranca de REFERENCIA_BANCARIA y no de PAGO —como hacía el reporte al que
     * sustituye— porque una referencia que nadie tomó no tiene renglón de pago
     * del que colgar: consultada desde PAGO sería invisible, y es justo la que
     * hay que ver para saber cuántas quedan.
     *
     * Todos los joins hacia la persona son leftJoin por lo mismo: media tabla
     * no tiene dueño todavía y sus columnas van vacías, que es el dato.
     *
     * @return array<int, array<string, string|float|null>>
     */
    public function reporte(?int $id_convocatoria = null, string $estado = ''): array
    {
        $consulta = DB::table('referencia_bancaria as rb')
            ->leftJoin('pago as pg', 'pg.pago_id_pago', '=', 'rb.reba_id_pago')
            /* Una referencia especial cubre a varias personas con un solo pago;
               sin esta subconsulta el reporte repetiría el mismo renglón una vez
               por participante. Se toma la solicitud más antigua como
               representante: en el pago individual es la única que hay. */
            ->leftJoinSub($this->solicitudesRepresentantes(), 'representante', function ($join): void {
                $join->on('representante.soli_id_pago', '=', 'rb.reba_id_pago');
            })
            ->leftJoin('solicitud as s', 's.soli_id_solicitud', '=', 'representante.id_solicitud')
            ->leftJoin('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->leftJoin('entidad_federativa as ef', 'ef.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            ->leftJoin('convocatoria as cv', 'cv.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->leftJoin('evaluacion as ev', 'ev.eval_id_evaluacion', '=', 's.soli_id_evaluacion')
            ->leftJoin('grupo as gr', 'gr.grup_id_grupo', '=', 'ev.grup_id_grupo')
            ->leftJoin('sede as sd', 'sd.sede_id_sede', '=', 'gr.sede_id_sede')
            /* El estado vigente del pago es el último renglón de la bitácora. */
            ->leftJoinSub($this->ultimosEstadosPago(), 'estado_actual', function ($join): void {
                $join->on('estado_actual.espa_id_pago', '=', 'rb.reba_id_pago');
            })
            ->leftJoin('estado_pago as ep', 'ep.espa_id_estado_pago', '=', 'estado_actual.id_estado')
            ->leftJoin('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->orderBy('rb.reba_referencia')
            ->select([
                'rb.reba_referencia',
                'rb.reba_monto',
                'rb.reba_vigencia',
                'rb.reba_fecha_asignacion',
                'pg.pago_monto_pagado',
                'pg.pago_fecha_pago',
                'ep.espa_fecha as fecha_resolucion',
                'ep.espa_hora as hora_resolucion',
                'p.pers_curp',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'ef.enfe_entidad_federativa',
                'cv.conv_nombre',
                'sd.sede_nombre',
                'gr.grup_fecha_inicio',
                'gr.grup_hora_inicio',
                'gr.grup_hora_fin',
            ])
            ->selectRaw(self::ESTADO.' as estado');

        $etiqueta = self::ESTADOS_REPORTE[$estado] ?? null;

        if ($etiqueta !== null) {
            $consulta->whereRaw(self::ESTADO.' = ?', [$etiqueta]);
        }

        /* Acotar por convocatoria deja fuera a las referencias sin asignar: no
           pertenecen a ninguna todavía. Es correcto y la pantalla lo advierte. */
        if ($id_convocatoria) {
            $consulta->where('s.soli_id_convocatoria', $id_convocatoria);
        }

        return $consulta->get()
            ->map(fn (object $fila): array => [
                'referencia' => (string) $fila->reba_referencia,
                'estado' => (string) $fila->estado,
                /* Lo que se cobró sale del catálogo y lo que la persona declaró
                   haber pagado, de PAGO. Las dos cifras van como número para
                   poder sumarlas en la hoja. */
                'monto_cobrado' => $fila->reba_monto === null ? null : (float) $fila->reba_monto,
                'vigencia' => (string) ($fila->reba_vigencia ?? ''),
                'fecha_asignacion' => (string) ($fila->reba_fecha_asignacion ?? ''),
                'curp' => (string) ($fila->pers_curp ?? ''),
                'nombre_completo' => trim(implode(' ', array_filter([
                    $fila->pers_nombre,
                    $fila->pers_apellido_paterno,
                    $fila->pers_apellido_materno,
                ]))),
                'entidad_federativa' => (string) ($fila->enfe_entidad_federativa ?? ''),
                'convocatoria' => (string) ($fila->conv_nombre ?? ''),
                'monto_declarado' => $fila->pago_monto_pagado === null
                    ? null
                    : (float) $fila->pago_monto_pagado,
                'fecha_pago' => (string) ($fila->pago_fecha_pago ?? ''),
                /* La bitácora guarda la fecha de la última resolución, sea cual
                   sea: sólo se publica cuando esa resolución fue validar, para
                   que la columna no feche rechazos como si fueran validaciones. */
                'fecha_validacion' => $fila->estado === 'Validada'
                    ? trim((string) $fila->fecha_resolucion.' '.(string) $fila->hora_resolucion)
                    : '',
                'sede' => (string) ($fila->sede_nombre ?? ''),
                'fecha_grupo' => (string) ($fila->grup_fecha_inicio ?? ''),
                'horario' => $fila->grup_hora_inicio
                    ? trim((string) $fila->grup_hora_inicio).' a '.trim((string) $fila->grup_hora_fin)
                    : '',
            ])
            ->all();
    }

    /**
     * La solicitud más antigua de cada pago. Es lo que convierte el pago
     * compartido de una referencia especial en un solo renglón.
     */
    private function solicitudesRepresentantes(): Builder
    {
        return DB::table('solicitud')
            ->whereNotNull('soli_id_pago')
            ->selectRaw('soli_id_pago, MIN(soli_id_solicitud) as id_solicitud')
            ->groupBy('soli_id_pago');
    }

    private function ultimosEstadosPago(): Builder
    {
        return DB::table('estado_pago')
            ->selectRaw('espa_id_pago, MAX(espa_id_estado_pago) as id_estado')
            ->groupBy('espa_id_pago');
    }

    /**
     * Carga el paquete con el catálogo y sus formatos de pago.
     *
     * El ZIP trae el CSV de la DEC y un PDF por referencia, nombrado con el
     * número: 1234567890.pdf es el formato de la referencia 1234567890.
     *
     * Los dos van juntos y emparejados uno a uno a propósito. Cuando el
     * catálogo y los formatos se cargaban por separado, entre un paso y otro
     * —o para siempre, si el segundo nunca ocurría— quedaban referencias sin
     * PDF: asignar() no las entrega, así que ocupaban el catálogo sin servirle
     * a nadie y la persona veía que no había referencias disponibles. Ahora o
     * entra el paquete completo o no entra nada.
     */
    public function importarPaquete(UploadedFile $archivo): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new DomainException('El servidor no tiene habilitada la extensión ZIP de PHP y no puede abrir el paquete.');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivo->getRealPath()) !== true) {
            throw new DomainException('El archivo ZIP no pudo abrirse. Verifica que no esté dañado ni protegido con contraseña.');
        }

        try {
            [$csv, $pdfs] = $this->inventariar($zip);

            $renglones = $this->leerCsv($this->contenidoDelZip($zip, $csv, 'el archivo CSV'));

            if ($renglones === []) {
                throw new DomainException('El archivo CSV no contiene referencias.');
            }

            $this->verificarEmparejamiento($renglones, $pdfs);
            $this->verificarQueNingunaEsteAsignada($renglones);

            $nuevas = $this->escribirFormatos($zip, $pdfs);
        } finally {
            $zip->close();
        }

        return $this->guardarRenglones($renglones, $nuevas);
    }

    /**
     * Recorre la tabla del ZIP sin leer contenido y devuelve dónde está el CSV
     * y en qué posición está el PDF de cada referencia.
     *
     * @return array{0: int, 1: array<string, int>}
     */
    private function inventariar(ZipArchive $zip): array
    {
        $csv = null;
        $pdfs = [];
        $bytes = 0;

        for ($posicion = 0; $posicion < $zip->numFiles; $posicion++) {
            $nombre_interno = (string) $zip->getNameIndex($posicion);

            if ($nombre_interno === '' || str_ends_with($nombre_interno, '/')) {
                continue;
            }

            /* basename() anula el zip-slip: una entrada que se llame
               '../../config/app.php' se queda en 'app.php' y ni siquiera es
               PDF. No importa en qué carpeta del ZIP venga cada archivo. */
            $nombre = basename(str_replace('\\', '/', $nombre_interno));

            if (str_starts_with($nombre, '.')) {
                continue;
            }

            $bytes += (int) ($zip->statIndex($posicion)['size'] ?? 0);

            if ($bytes > self::MAX_DESCOMPRIMIDO) {
                throw new DomainException('El contenido del paquete es demasiado grande para procesarse.');
            }

            if ($this->esCsv($nombre)) {
                if ($csv !== null) {
                    throw new DomainException('El paquete trae más de un archivo CSV y no se sabe cuál es el catálogo. Deja sólo uno.');
                }

                $csv = $posicion;

                continue;
            }

            if (!$this->esPdf($nombre)) {
                continue;
            }

            $referencia = $this->referenciaDeNombre($nombre);

            if ($referencia === '') {
                throw new DomainException('El archivo "'.$nombre.'" no lleva por nombre un número de referencia.');
            }

            if (isset($pdfs[$referencia])) {
                throw new DomainException('El paquete trae dos formatos para la referencia '.$referencia.'.');
            }

            $pdfs[$referencia] = $posicion;
        }

        if ($csv === null) {
            throw new DomainException('El paquete no trae el archivo CSV con el catálogo de referencias.');
        }

        return [$csv, $pdfs];
    }

    private function contenidoDelZip(ZipArchive $zip, int $posicion, string $nombre): string
    {
        $contenido = $zip->getFromIndex($posicion);

        if ($contenido === false || $contenido === '') {
            throw new DomainException('No se pudo leer '.$nombre.' dentro del paquete.');
        }

        return $contenido;
    }

    /**
     * Las referencias del CSV y los PDF del paquete tienen que ser exactamente
     * las mismas.
     *
     * Las dos diferencias se reclaman juntas para que el administrador corrija
     * el paquete de una vez en lugar de descubrir los faltantes de a uno.
     *
     * @param  array<int, array{referencia: string, monto: float, vigencia: string, emision: string}>  $renglones
     * @param  array<string, int>  $pdfs
     */
    private function verificarEmparejamiento(array $renglones, array $pdfs): void
    {
        $referencias = array_column($renglones, 'referencia');
        $repetidas = array_diff_assoc($referencias, array_unique($referencias));

        if ($repetidas !== []) {
            throw new DomainException(
                'El CSV repite estas referencias y no se cargó ninguna: '.$this->listar($repetidas).'.'
            );
        }

        $reclamos = [];
        $sin_formato = array_diff($referencias, array_keys($pdfs));
        $sin_renglon = array_diff(array_keys($pdfs), $referencias);

        if ($sin_formato !== []) {
            $reclamos[] = 'faltan los PDF de '.$this->listar($sin_formato);
        }

        if ($sin_renglon !== []) {
            $reclamos[] = 'sobran los PDF de '.$this->listar($sin_renglon);
        }

        if ($reclamos !== []) {
            throw new DomainException(
                'El paquete no está completo y no se cargó ninguna referencia: '.implode('; ', $reclamos).'.'
            );
        }
    }

    /**
     * Una referencia ya entregada no se recarga.
     *
     * Su PDF es el que la persona tiene en la mano para pagar en ventanilla y
     * REBA_ID_PAGO no se deshace, así que un paquete que la incluye está mal
     * armado y se devuelve entero.
     *
     * @param  array<int, array{referencia: string, monto: float, vigencia: string, emision: string}>  $renglones
     */
    private function verificarQueNingunaEsteAsignada(array $renglones): void
    {
        $asignadas = DB::table('referencia_bancaria')
            ->whereIn('reba_referencia', array_column($renglones, 'referencia'))
            ->whereNotNull('reba_id_pago')
            ->pluck('reba_referencia')
            ->all();

        if ($asignadas !== []) {
            throw new DomainException(
                'El paquete incluye referencias que ya se entregaron a una persona y no se cargó '
                .'ninguna: '.$this->listar($asignadas).'. Quítalas del archivo.'
            );
        }
    }

    /**
     * Guarda los PDF y devuelve las rutas que este paquete creó.
     *
     * Los archivos se escriben antes de tocar la base a propósito: un PDF
     * huérfano en el disco no le hace daño a nadie, pero un renglón con
     * REBA_PATH apuntando a un archivo que no existe sí se entrega —
     * TIENE_FORMATO mira la columna, no el disco— y la persona se queda con un
     * número y sin con qué pagar.
     *
     * @param  array<string, int>  $pdfs
     * @return array<int, string>
     */
    private function escribirFormatos(ZipArchive $zip, array $pdfs): array
    {
        $disco = Storage::disk('referencias');
        $nuevas = [];

        try {
            foreach ($pdfs as $referencia => $posicion) {
                /* PHP convierte a entero la clave de un arreglo cuando el texto
                   es sólo dígitos, y hay referencias sin letras. */
                $referencia = (string) $referencia;

                $contenido = $this->contenidoDelZip($zip, $posicion, 'el formato de la referencia '.$referencia);

                /* La extensión no prueba nada: el archivo se sirve después con
                   Content-Type de PDF y basta con renombrar cualquier cosa. */
                if (!str_starts_with($contenido, '%PDF-')) {
                    throw new DomainException('El formato de la referencia '.$referencia.' no es un archivo PDF.');
                }

                $ruta = $this->rutaFormato($referencia);
                $existia = $disco->exists($ruta);

                /* ponytail: recargar un paquete pisa el PDF que ya tenía una
                   referencia libre, y eso no se deshace. Es el mismo formato de
                   la misma referencia, así que no hay nada que perder; si algún
                   día importara, habría que escribir a un nombre temporal y
                   renombrar al confirmar la transacción. */
                $disco->put($ruta, $contenido);

                if (!$existia) {
                    $nuevas[] = $ruta;
                }
            }
        } catch (Throwable $error) {
            $disco->delete($nuevas);

            throw $error;
        }

        return $nuevas;
    }

    /**
     * Escribe el catálogo con sus formatos ya puestos en el disco.
     *
     * Si la transacción falla se borran los PDF que este paquete creó. Los que
     * ya estaban se quedan: son de referencias que siguen en el catálogo.
     *
     * @param  array<int, array{referencia: string, monto: float, vigencia: string, emision: string}>  $renglones
     * @param  array<int, string>  $nuevas
     */
    private function guardarRenglones(array $renglones, array $nuevas): array
    {
        $ahora = Carbon::now();
        $resultado = ['nuevas' => 0, 'actualizadas' => 0, 'total' => count($renglones)];

        try {
            DB::transaction(function () use ($renglones, $ahora, &$resultado): void {
                foreach ($renglones as $renglon) {
                    $referencia = $renglon['referencia'];

                    $columnas = [
                        'reba_path' => $this->rutaFormato($referencia),
                        'reba_monto' => $renglon['monto'],
                        'reba_vigencia' => $renglon['vigencia'],
                        'reba_fecha_emision' => $renglon['emision'],
                        'reba_fecha_carga' => $ahora->toDateString(),
                        'reba_hora_carga' => $ahora->toTimeString(),
                    ];

                    $existente = DB::table('referencia_bancaria')
                        ->where('reba_referencia', $referencia)
                        ->lockForUpdate()
                        ->first();

                    if (!$existente) {
                        DB::table('referencia_bancaria')->insert(
                            ['reba_referencia' => $referencia] + $columnas
                        );

                        $resultado['nuevas']++;

                        continue;
                    }

                    /* verificarQueNingunaEsteAsignada() corre antes de la
                       transacción, así que entre aquella consulta y este
                       bloqueo alguien pudo pedir su referencia. Se comprueba
                       otra vez con el renglón ya bloqueado. */
                    if ($existente->reba_id_pago !== null) {
                        throw new DomainException(
                            'La referencia '.$referencia.' se entregó a una persona mientras se cargaba el '
                            .'paquete, así que no se cargó ninguna. Vuelve a intentarlo sin ella.'
                        );
                    }

                    DB::table('referencia_bancaria')
                        ->where('reba_id_referencia_bancaria', $existente->reba_id_referencia_bancaria)
                        ->update($columnas);

                    $resultado['actualizadas']++;
                }
            });
        } catch (Throwable $error) {
            Storage::disk('referencias')->delete($nuevas);

            throw $error;
        }

        return $resultado;
    }

    /**
     * Entrega la primera referencia libre a la solicitud vigente de la persona
     * y crea el PAGO con el que continúa el trámite.
     */
    public function asignar(int $id_usuario): array
    {
        return DB::transaction(function () use ($id_usuario): array {
            $solicitud = DB::table('solicitud as s')
                ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
                ->where('p.pers_id_usuario', $id_usuario)
                ->orderByDesc('s.soli_id_solicitud')
                ->lockForUpdate()
                ->select('s.soli_id_solicitud', 's.soli_id_pago', 's.soli_id_convocatoria')
                ->first();

            if (!$solicitud) {
                throw new DomainException('Todavía no tienes una solicitud registrada.');
            }

            if ($solicitud->soli_id_pago) {
                throw new DomainException('Tu solicitud ya tiene una referencia bancaria asignada.');
            }

            $this->verificarSolicitudAprobada((int) $solicitud->soli_id_solicitud);

            /* SKIP LOCKED deja que dos personas que piden su referencia al
               mismo tiempo tomen renglones distintos en vez de esperarse. Es
               SQL de PostgreSQL y la suite corre en SQLite, que no lo entiende
               ni lo necesita: ahí no hay concurrencia que resolver. */
            $bloqueo = DB::connection()->getDriverName() === 'pgsql'
                ? ' FOR UPDATE SKIP LOCKED'
                : '';

            /* Sin su formato PDF la referencia no se entrega: la persona se
               quedaría con un número y sin con qué pagar en ventanilla, y la
               entrega no tiene vuelta atrás —REBA_ID_PAGO es único—. La
               condición va dentro de esta consulta y no en un filtro posterior
               para no perder la garantía de SKIP LOCKED. */
            $referencia = DB::selectOne(
                'SELECT reba_id_referencia_bancaria, reba_referencia, reba_path, reba_monto
                   FROM referencia_bancaria
                  WHERE reba_id_pago IS NULL
                    AND '.self::TIENE_FORMATO.'
               ORDER BY reba_id_referencia_bancaria
                  LIMIT 1'.$bloqueo
            );

            if (!$referencia) {
                throw new DomainException($this->motivoSinReferencia());
            }

            $ahora = Carbon::now();

            $id_pago = DB::table('pago')->insertGetId([
                'pago_referencia_bancaria' => $referencia->reba_referencia,
                'pago_referencia_bancaria_path' => $referencia->reba_path,
                'pago_monto_pagado' => $this->montoConvocatoria(
                    (int) $solicitud->soli_id_convocatoria,
                    $referencia->reba_monto === null ? null : (float) $referencia->reba_monto
                ),
            ], 'pago_id_pago');

            $ligadas = DB::table('referencia_bancaria')
                ->where('reba_id_referencia_bancaria', $referencia->reba_id_referencia_bancaria)
                ->whereNull('reba_id_pago')
                ->update([
                    'reba_id_pago' => $id_pago,
                    'reba_fecha_asignacion' => $ahora->toDateString(),
                    'reba_hora_asignacion' => $ahora->toTimeString(),
                ]);

            if ($ligadas !== 1) {
                throw new DomainException('La referencia se entregó a otra persona. Vuelve a intentarlo.');
            }

            DB::table('solicitud')
                ->where('soli_id_solicitud', $solicitud->soli_id_solicitud)
                ->update(['soli_id_pago' => $id_pago]);

            return [
                'referencia' => (string) $referencia->reba_referencia,
                'id_pago' => (int) $id_pago,
            ];
        });
    }

    /**
     * Referencia entregada a la persona, con el PDF sólo si existe el archivo.
     *
     * El monto es el que hay que pagar y sale del catálogo —REBA_MONTO—, no de
     * PAGO_MONTO_PAGADO: desde que la persona captura su pago, esa columna
     * guarda lo que declaró haber pagado y las dos cifras pueden no coincidir.
     * La excepción es el pago compartido de una referencia especial: ahí el
     * total lo fijó la solicitud y todavía puede no haber renglón de catálogo.
     *
     * Un pago compartido nace sin referencia —la emite la DEC más tarde—, así
     * que el renglón existe con el número vacío y se devuelve marcado como
     * pendiente; ver App\Servicios\ReferenciaEspecial.
     */
    public function referenciaDePersona(int $id_usuario): ?array
    {
        $fila = DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->join('pago as pg', 'pg.pago_id_pago', '=', 's.soli_id_pago')
            ->where('p.pers_id_usuario', $id_usuario)
            ->orderByDesc('s.soli_id_solicitud')
            ->select([
                's.soli_id_convocatoria',
                'pg.pago_id_pago',
                'pg.pago_id_dato_fiscal',
                'pg.pago_referencia_bancaria',
                'pg.pago_referencia_bancaria_path',
                'pg.pago_monto_pagado',
                'pg.pago_no_empleado',
            ])
            ->first();

        if (!$fila) {
            return null;
        }

        $catalogo = DB::table('referencia_bancaria')
            ->where('reba_id_pago', $fila->pago_id_pago)
            ->select('reba_vigencia', 'reba_monto')
            ->first();

        $compartida = $fila->pago_no_empleado === null ? null : (int) $fila->pago_no_empleado;

        return [
            'referencia' => (string) $fila->pago_referencia_bancaria,
            'pendiente' => trim((string) $fila->pago_referencia_bancaria) === '',
            'participantes' => $compartida,
            /* Sólo el pago compartido tiene pagador que nombrar, y sólo por eso
               se consulta DATO_FISCAL: en el camino individual esa tabla puede
               ni existir todavía. */
            'razon_social' => $compartida === null ? '' : (string) DB::table('dato_fiscal')
                ->where('dafi_id_dato_fiscal', $fila->pago_id_dato_fiscal)
                ->value('dafi_razon_social'),
            'monto' => $compartida !== null
                ? (float) $fila->pago_monto_pagado
                : $this->montoConvocatoria(
                    (int) $fila->soli_id_convocatoria,
                    $catalogo === null || $catalogo->reba_monto === null
                        ? null
                        : (float) $catalogo->reba_monto
                ),
            'vigencia' => $catalogo?->reba_vigencia,
            'ruta_formato' => $this->rutaFormatoDisponible($fila->pago_referencia_bancaria_path),
        ];
    }

    /**
     * Ruta del PDF dentro del disco 'referencias', o null si no se puede servir.
     */
    public function rutaFormatoDisponible(?string $ruta): ?string
    {
        if (!is_string($ruta) || $ruta === '' || str_starts_with($ruta, '/')) {
            return null;
        }

        if (str_contains($ruta, '..') || !$this->esPdf($ruta)) {
            return null;
        }

        return Storage::disk('referencias')->exists($ruta) ? $ruta : null;
    }

    /**
     * Lee el CSV y devuelve los renglones ya normalizados.
     *
     * El archivo llega tal como lo manda la DEC, con su membrete institucional
     * encima de la tabla, así que el encabezado no es el primer renglón: se
     * busca. Y se exige completo antes de devolver nada, para que un archivo al
     * que le falta una columna no alcance a escribir media carga.
     *
     * @return array<int, array{referencia: string, monto: float, vigencia: string, emision: string}>
     */
    private function leerCsv(string $contenido): array
    {
        $crudos = $this->renglonesDelArchivo($contenido);

        if ($crudos === []) {
            return [];
        }

        [$columnas, $inicio] = $this->ubicarEncabezados($crudos);

        $this->verificarEncabezadoCompleto($columnas);

        $renglones = [];

        foreach (array_slice($crudos, $inicio) as $crudo) {
            $renglones[] = $this->armarRenglon($crudo['campos'], $columnas, $crudo['numero']);
        }

        return $renglones;
    }

    /**
     * Renglones con contenido del archivo, con el número de fila que ve el
     * administrador en Excel para poder nombrárselo si algo falla.
     *
     * @return array<int, array{numero: int, campos: array<int, string>}>
     */
    private function renglonesDelArchivo(string $contenido): array
    {
        /* El CSV viene del ZIP, no del disco. php://temp acepta rewind(), que
           es lo que hace falta para volver al principio después de espiar la
           primera línea en busca del separador; el archivo pesa dos megas como
           mucho, así que cabe de sobra. */
        $manejador = fopen('php://temp', 'r+');
        fwrite($manejador, $contenido);
        rewind($manejador);

        try {
            $primera = fgets($manejador);

            if ($primera === false) {
                return [];
            }

            $primera = preg_replace('/^\xEF\xBB\xBF/', '', $primera);
            $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

            rewind($manejador);
            $renglones = [];
            $numero = 0;

            while (($campos = fgetcsv($manejador, 0, $separador, '"', '')) !== false) {
                $numero++;

                if ($campos === [null] || $campos === []) {
                    continue;
                }

                $campos[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($campos[0] ?? ''));
                $campos = array_map(fn ($campo): string => trim((string) $campo), $campos);

                /* Excel exporta como ",,," los renglones que sólo tienen
                   formato. No son datos ni membrete: sobran. */
                if (implode('', $campos) === '') {
                    continue;
                }

                $renglones[] = ['numero' => $numero, 'campos' => $campos];
            }

            return $renglones;
        } finally {
            fclose($manejador);
        }
    }

    /**
     * Encuentra el renglón de encabezados y devuelve dónde empiezan los datos.
     *
     * Se busca en vez de saltar un número fijo de renglones a propósito: si la
     * DEC agrega un oficio o una fecha de corte al membrete, un salto fijo se
     * comería la primera referencia sin que nadie se entere.
     *
     * @param  array<int, array{numero: int, campos: array<int, string>}>  $renglones
     * @return array{0: array<string, int|null>, 1: int}
     */
    private function ubicarEncabezados(array $renglones): array
    {
        foreach (array_slice($renglones, 0, self::MAX_PREAMBULO) as $indice => $renglon) {
            $mapa = $this->mapearEncabezados($renglon['campos']);

            if ($mapa !== null) {
                return [$mapa, $indice + 1];
            }
        }

        throw new DomainException(
            'No se encontró el encabezado de la tabla. El archivo debe tener un renglón con los '
            .'títulos de las columnas: '.implode(', ', self::OBLIGATORIAS).'.'
        );
    }

    /**
     * Ninguna de las cuatro columnas puede faltar, y se reclaman todas juntas:
     * así el administrador corrige el archivo una vez en lugar de descubrir las
     * faltantes de a una.
     *
     * @param  array<string, int|null>  $columnas
     */
    private function verificarEncabezadoCompleto(array $columnas): void
    {
        $faltantes = [];

        foreach (self::OBLIGATORIAS as $campo => $nombre) {
            if (($columnas[$campo] ?? null) === null) {
                $faltantes[] = $nombre;
            }
        }

        if ($faltantes !== []) {
            throw new DomainException(
                'El archivo está incompleto y no se cargó ninguna referencia. Faltan estas '
                .'columnas: '.implode(', ', $faltantes).'.'
            );
        }
    }

    /**
     * Un renglón de datos ya validado. Que la columna exista no garantiza que
     * traiga dato, así que los cuatro campos se revisan aquí.
     *
     * @param  array<int, string>  $campos
     * @param  array<string, int|null>  $columnas
     * @return array{referencia: string, monto: float, vigencia: string, emision: string}
     */
    private function armarRenglon(array $campos, array $columnas, int $numero): array
    {
        $referencia = $this->normalizarReferencia($campos[$columnas['referencia']] ?? '');

        if ($referencia === '') {
            throw new DomainException('El renglón '.$numero.' del CSV no tiene número de referencia.');
        }

        if (mb_strlen($referencia) > 20) {
            throw new DomainException('La referencia del renglón '.$numero.' excede 20 caracteres.');
        }

        $monto = $this->normalizarMonto($campos[$columnas['monto']] ?? '');

        if ($monto === null) {
            throw new DomainException('El renglón '.$numero.' del CSV no tiene un importe válido.');
        }

        $vigencia = $this->normalizarFecha($campos[$columnas['vigencia']] ?? '');

        if ($vigencia === null) {
            throw new DomainException('El renglón '.$numero.' del CSV no tiene una vigencia válida.');
        }

        $emision = $this->normalizarFecha($campos[$columnas['emision']] ?? '');

        if ($emision === null) {
            throw new DomainException('El renglón '.$numero.' del CSV no tiene una fecha de emisión válida.');
        }

        return [
            'referencia' => $referencia,
            'monto' => $monto,
            'vigencia' => $vigencia,
            'emision' => $emision,
        ];
    }

    /**
     * @return array<string, int|null>|null
     */
    private function mapearEncabezados(array $campos): ?array
    {
        $normalizados = array_map(fn (string $campo): string => $this->clave($campo), $campos);
        $mapa = array_fill_keys(array_keys(self::ENCABEZADOS), null);

        foreach ($normalizados as $posicion => $encabezado) {
            foreach (self::ENCABEZADOS as $campo => $alias) {
                if ($mapa[$campo] === null && in_array($encabezado, $alias, true)) {
                    $mapa[$campo] = $posicion;
                }
            }
        }

        /* La referencia es lo que distingue al encabezado del membrete: sin
           ella, este renglón todavía no es la tabla. */
        return $mapa['referencia'] === null ? null : $mapa;
    }

    private function clave(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return (string) preg_replace('/[^a-z0-9_]/', '_', $texto);
    }

    private function normalizarReferencia(string $valor): string
    {
        return (string) preg_replace('/[^A-Za-z0-9-]/', '', trim($valor));
    }

    private function normalizarMonto(string $valor): ?float
    {
        $valor = trim(str_replace(['$', ',', ' '], '', $valor));

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        /* Hay conversores que no formatean la fecha y dejan el número de serie
           de Excel: 46254 es el 20/08/2026, contado desde el 30/12/1899. Sin
           este caso un archivo bueno se rechazaría por una fecha que sí venía. */
        if (preg_match('/^\d{5}$/', $valor) === 1 && (int) $valor >= 40000 && (int) $valor <= 60000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $valor)->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor);
            } catch (Throwable) {
                continue;
            }

            if ($fecha->format($formato) === $valor) {
                return $fecha->toDateString();
            }
        }

        return null;
    }

    private function referenciaDeNombre(string $nombre): string
    {
        return $this->normalizarReferencia(pathinfo($nombre, PATHINFO_FILENAME));
    }

    private function esPdf(string $nombre): bool
    {
        return str_ends_with(mb_strtolower($nombre), '.pdf');
    }

    private function esCsv(string $nombre): bool
    {
        return str_ends_with(mb_strtolower($nombre), '.csv');
    }

    private function rutaFormato(string $referencia): string
    {
        return self::CARPETA_FORMATOS.'/'.$referencia.'.pdf';
    }

    /**
     * Hasta cinco referencias por mensaje: la lista es para reconocer el
     * problema, no para enumerarlo entero.
     *
     * @param  array<int|string, string>  $referencias
     */
    private function listar(array $referencias): string
    {
        $referencias = array_values(array_unique($referencias));
        $muestra = array_slice($referencias, 0, 5);
        $resto = count($referencias) - count($muestra);

        return implode(', ', $muestra).($resto > 0 ? ' y '.$resto.' más' : '');
    }

    public function montoConvocatoria(int $id_convocatoria, ?float $monto_referencia): float
    {
        if ($monto_referencia !== null && $monto_referencia > 0) {
            return $monto_referencia;
        }

        $monto = DB::table('convocatoria')
            ->where('conv_id_convocatoria', $id_convocatoria)
            ->value('conv_monto_recuperacion');

        /* CONV_MONTO_RECUPERACION es MONEY: llega como '$7,000.00'. */
        $limpio = (float) preg_replace('/[^0-9.\-]/', '', (string) $monto);

        return $limpio > 0 ? $limpio : (float) config('suif.cuota_recuperacion', 7000);
    }

    /**
     * Por qué no hubo referencia que entregar.
     *
     * Se distinguen los dos casos porque no se arreglan igual: si el catálogo
     * está vacío hay que pedirle más referencias al banco, y si sólo faltan los
     * PDF son renglones viejos, de cuando el catálogo y los formatos se
     * cargaban por separado. Un mensaje único mandaría a la persona a soporte
     * sin que soporte sepa qué hacer.
     */
    private function motivoSinReferencia(): string
    {
        $hay_libres = DB::table('referencia_bancaria')
            ->whereNull('reba_id_pago')
            ->exists();

        return $hay_libres
            ? 'Las referencias disponibles todavía no tienen su formato de pago. Comunícate con el equipo administrativo.'
            : 'No hay referencias bancarias disponibles. Comunícate con el equipo administrativo.';
    }

    private function verificarSolicitudAprobada(int $id_solicitud): void
    {
        $estado = DB::table('estado_solicitud as es')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('es.esso_id_solicitud', $id_solicitud)
            ->orderByDesc('es.esso_id_estado_solicitud')
            ->value('ces.esso_estado_solicitud');

        if ($estado !== 'Aprobada') {
            throw new DomainException('Tu referencia estará disponible cuando el equipo administrativo apruebe tu solicitud.');
        }
    }
}
