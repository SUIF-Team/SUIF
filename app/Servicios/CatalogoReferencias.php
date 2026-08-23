<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * CatalogoReferencias
 *
 * Responsabilidad: administrar el catálogo de referencias bancarias que el
 * área administrativa carga por CSV, los formatos PDF que vienen dentro de un
 * ZIP y la entrega de una referencia a una persona.
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
        $conteos = DB::table('referencia_bancaria')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE reba_id_pago IS NULL) AS disponibles')
            ->selectRaw('COUNT(*) FILTER (WHERE reba_path IS NOT NULL) AS con_formato')
            ->first();

        return [
            'total' => (int) ($conteos->total ?? 0),
            'disponibles' => (int) ($conteos->disponibles ?? 0),
            'asignadas' => (int) ($conteos->total ?? 0) - (int) ($conteos->disponibles ?? 0),
            'con_formato' => (int) ($conteos->con_formato ?? 0),
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
     * Carga el CSV con las referencias disponibles.
     *
     * Volver a subir el mismo archivo no duplica: las referencias ya
     * registradas sólo actualizan sus datos, y las ya entregadas a una persona
     * no se tocan.
     *
     * Si al archivo le falta una columna, leerCsv() revienta antes de esta
     * transacción: o entra el catálogo completo o no entra nada.
     */
    public function importarCatalogo(UploadedFile $archivo): array
    {
        $renglones = $this->leerCsv($archivo);

        if ($renglones === []) {
            throw new DomainException('El archivo CSV no contiene referencias.');
        }

        $ahora = Carbon::now();
        $resultado = ['nuevas' => 0, 'actualizadas' => 0, 'omitidas' => 0, 'errores' => []];
        $vistas = [];

        DB::transaction(function () use ($renglones, $ahora, &$resultado, &$vistas): void {
            foreach ($renglones as $renglon) {
                $referencia = $renglon['referencia'];

                if (isset($vistas[$referencia])) {
                    $resultado['omitidas']++;
                    $this->anotarError($resultado, 'La referencia '.$referencia.' viene repetida en el archivo.');

                    continue;
                }

                $vistas[$referencia] = true;

                $existente = DB::table('referencia_bancaria')
                    ->where('reba_referencia', $referencia)
                    ->lockForUpdate()
                    ->first();

                if (!$existente) {
                    DB::table('referencia_bancaria')->insert([
                        'reba_referencia' => $referencia,
                        'reba_monto' => $renglon['monto'],
                        'reba_vigencia' => $renglon['vigencia'],
                        'reba_fecha_emision' => $renglon['emision'],
                        'reba_fecha_carga' => $ahora->toDateString(),
                        'reba_hora_carga' => $ahora->toTimeString(),
                    ]);

                    $resultado['nuevas']++;

                    continue;
                }

                if ($existente->reba_id_pago !== null) {
                    $resultado['omitidas']++;
                    $this->anotarError($resultado, 'La referencia '.$referencia.' ya está asignada y no se modificó.');

                    continue;
                }

                DB::table('referencia_bancaria')
                    ->where('reba_id_referencia_bancaria', $existente->reba_id_referencia_bancaria)
                    ->update([
                        'reba_monto' => $renglon['monto'],
                        'reba_vigencia' => $renglon['vigencia'],
                        'reba_fecha_emision' => $renglon['emision'],
                        'reba_fecha_carga' => $ahora->toDateString(),
                        'reba_hora_carga' => $ahora->toTimeString(),
                    ]);

                $resultado['actualizadas']++;
            }
        });

        return $resultado;
    }

    /**
     * Extrae los PDF del ZIP y los liga a la referencia que nombra el archivo.
     *
     * El nombre de cada PDF es el número de referencia: 1234567890.pdf queda
     * ligado a la referencia 1234567890.
     */
    public function importarFormatos(UploadedFile $archivo): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new DomainException('El servidor no tiene habilitada la extensión ZIP de PHP y no puede extraer el archivo.');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivo->getRealPath()) !== true) {
            throw new DomainException('El archivo ZIP no pudo abrirse. Verifica que no esté dañado ni protegido con contraseña.');
        }

        $disco = Storage::disk('referencias');
        $resultado = ['extraidos' => 0, 'ligados' => 0, 'sin_referencia' => 0, 'errores' => []];

        try {
            for ($posicion = 0; $posicion < $zip->numFiles; $posicion++) {
                $nombre_interno = (string) $zip->getNameIndex($posicion);

                if ($nombre_interno === '' || str_ends_with($nombre_interno, '/')) {
                    continue;
                }

                $nombre = basename(str_replace('\\', '/', $nombre_interno));

                if (str_starts_with($nombre, '.') || !$this->esPdf($nombre)) {
                    continue;
                }

                $contenido = $zip->getFromIndex($posicion);

                if ($contenido === false || $contenido === '') {
                    $this->anotarError($resultado, 'No se pudo leer "'.$nombre.'" dentro del ZIP.');

                    continue;
                }

                $referencia = $this->referenciaDeNombre($nombre);

                $fila = $referencia === ''
                    ? null
                    : DB::table('referencia_bancaria')->where('reba_referencia', $referencia)->first();

                if (!$fila) {
                    $resultado['sin_referencia']++;
                    $this->anotarError($resultado, '"'.$nombre.'" no corresponde a ninguna referencia del catálogo.');

                    continue;
                }

                $ruta = self::CARPETA_FORMATOS.'/'.$referencia.'.pdf';
                $disco->put($ruta, $contenido);
                $resultado['extraidos']++;

                DB::table('referencia_bancaria')
                    ->where('reba_id_referencia_bancaria', $fila->reba_id_referencia_bancaria)
                    ->update(['reba_path' => $ruta]);

                /* La referencia ya entregada conserva su PDF: se actualiza
                   también en PAGO para que la persona vea el archivo nuevo. */
                if ($fila->reba_id_pago !== null) {
                    DB::table('pago')
                        ->where('pago_id_pago', $fila->reba_id_pago)
                        ->update(['pago_referencia_bancaria_path' => $ruta]);
                }

                $resultado['ligados']++;
            }
        } finally {
            $zip->close();
        }

        if ($resultado['extraidos'] === 0 && $resultado['sin_referencia'] === 0) {
            throw new DomainException('El archivo ZIP no contiene documentos PDF.');
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
               mismo tiempo tomen renglones distintos en vez de esperarse. */
            $referencia = DB::selectOne(
                'SELECT reba_id_referencia_bancaria, reba_referencia, reba_path, reba_monto
                   FROM referencia_bancaria
                  WHERE reba_id_pago IS NULL
               ORDER BY reba_id_referencia_bancaria
                  LIMIT 1
                    FOR UPDATE SKIP LOCKED'
            );

            if (!$referencia) {
                throw new DomainException('No hay referencias bancarias disponibles. Comunícate con el equipo administrativo.');
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
     */
    public function referenciaDePersona(int $id_usuario): ?array
    {
        $fila = DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->join('pago as pg', 'pg.pago_id_pago', '=', 's.soli_id_pago')
            ->where('p.pers_id_usuario', $id_usuario)
            ->orderByDesc('s.soli_id_solicitud')
            ->select([
                'pg.pago_id_pago',
                'pg.pago_referencia_bancaria',
                'pg.pago_referencia_bancaria_path',
                'pg.pago_monto_pagado',
            ])
            ->first();

        if (!$fila) {
            return null;
        }

        $vigencia = DB::table('referencia_bancaria')
            ->where('reba_id_pago', $fila->pago_id_pago)
            ->value('reba_vigencia');

        return [
            'referencia' => (string) $fila->pago_referencia_bancaria,
            'monto' => (float) $fila->pago_monto_pagado,
            'vigencia' => $vigencia,
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
    private function leerCsv(UploadedFile $archivo): array
    {
        $crudos = $this->renglonesDelArchivo($archivo);

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
    private function renglonesDelArchivo(UploadedFile $archivo): array
    {
        $manejador = @fopen($archivo->getRealPath(), 'r');

        if ($manejador === false) {
            throw new RuntimeException('No fue posible leer el archivo CSV.');
        }

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

    private function montoConvocatoria(int $id_convocatoria, ?float $monto_referencia): float
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

    private function anotarError(array &$resultado, string $mensaje): void
    {
        if (count($resultado['errores']) < 20) {
            $resultado['errores'][] = $mensaje;
        }
    }
}
