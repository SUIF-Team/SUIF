<?php

namespace App\Servicios;

use App\Mail\ReferenciaEspecialEmitida;
use App\Support\NombrePersona;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ReferenciaEspecial
 *
 * Responsabilidad: la referencia bancaria con la que un tercero paga la
 * certificación de varios participantes a la vez.
 *
 * No hay tabla nueva: SOLICITUD.SOLI_ID_PAGO ya es N→1 contra PAGO, así que
 * las N solicitudes cuelgan del mismo renglón de PAGO y con un solo
 * comprobante se resuelven todas. Ese pago se distingue de los individuales
 * por PAGO_NO_EMPLEADO —cuántas personas cubre— y nace sin referencia: la
 * emite la DEC más tarde, cuando carga al catálogo una por el monto total.
 *
 * Los datos del pagador viven en DATO_FISCAL desde el primer momento porque
 * el CFDI se emite a nombre de quien paga, no de los participantes.
 */
class ReferenciaEspecial
{
    /** Con una sola persona corresponde la referencia individual. */
    public const MINIMO_PARTICIPANTES = 2;

    /** ponytail: tope arbitrario para acotar la captura; súbelo si la DEC lo pide. */
    public const MAXIMO_PARTICIPANTES = 50;

    /**
     * Qué cuenta como «pago de referencia especial todavía sin emitir», en SQL.
     *
     * ponytail: la marca de pendiente es la cadena vacía porque
     * PAGO_REFERENCIA_BANCARIA es NOT NULL en el esquema y database/scripts/ no
     * se toca sin autorización. Si algún día esa columna acepta NULL, la marca
     * correcta es IS NULL y basta con cambiar esta constante.
     */
    private const PENDIENTE = "pago_no_empleado IS NOT NULL
        AND (pago_referencia_bancaria IS NULL OR pago_referencia_bancaria = '')";

    public function __construct(
        private readonly CatalogoReferencias $catalogo,
        private readonly GestionClaves $claves
    ) {
    }

    /**
     * Registra la solicitud: da de alta los datos fiscales del pagador, crea el
     * pago compartido y le cuelga las solicitudes de los participantes.
     *
     * @param array{razon_social: string, persona_moral: string, regimen_fiscal: int|string,
     *              codigo_postal: string, rfc: string} $pagador
     * @param array<int, array{curp: string, nombre: string, primer_apellido: string,
     *              segundo_apellido: string}> $participantes
     * @return array{id_pago: int, monto: float, participantes: int}
     */
    public function solicitar(int $id_usuario, array $pagador, array $participantes): array
    {
        return DB::transaction(function () use ($id_usuario, $pagador, $participantes): array {
            $solicitante = $this->solicitudDelUsuario($id_usuario);

            if (!$solicitante) {
                throw new DomainException('Todavía no tienes una solicitud registrada.');
            }

            if ($solicitante->soli_id_pago) {
                throw new DomainException('Tu solicitud ya tiene una referencia bancaria asignada.');
            }

            $curps = $this->curpsCapturadas($participantes);

            if (!in_array($this->normalizarCurp((string) $solicitante->pers_curp), $curps, true)) {
                throw new DomainException('Tu CURP tiene que estar en la lista de participantes.');
            }

            $solicitudes = $this->resolverParticipantes(
                $participantes,
                $solicitante->soli_id_convocatoria
            );

            $monto = count($solicitudes) * $this->catalogo->montoConvocatoria(
                (int) $solicitante->soli_id_convocatoria,
                null
            );

            $id_pago = DB::table('pago')->insertGetId([
                'pago_id_dato_fiscal' => $this->altaDatosFiscales($pagador),
                'pago_no_empleado' => count($solicitudes),
                'pago_monto_pagado' => $monto,
                /* Quien paga por varios siempre pide CFDI: la factura se emite
                   a nombre del pagador, no de cada participante. La elección
                   nace hecha —igual que DAFI_USO_CFDI en altaDatosFiscales()—
                   para que ninguno de los N participantes pueda entrar después
                   al paso del comprobante y cambiarla por un ticket, que es
                   definitivo y dejaría a la empresa sin su factura. */
                'pago_uso_cfdi' => true,
                /* Vacías hasta que la DEC emita la referencia. */
                'pago_referencia_bancaria' => '',
                'pago_referencia_bancaria_path' => '',
            ], 'pago_id_pago');

            /* El whereNull no es adorno: es lo único que impide que dos
               solicitudes simultáneas repartan la misma persona en dos pagos.
               SQLite ignora lockForUpdate en silencio y ahí es la única
               defensa; en PostgreSQL se suman las dos. */
            $ligadas = DB::table('solicitud')
                ->whereIn('soli_id_solicitud', $solicitudes)
                ->whereNull('soli_id_pago')
                ->update(['soli_id_pago' => $id_pago]);

            if ($ligadas !== count($solicitudes)) {
                throw new DomainException(
                    'Alguno de los participantes obtuvo su referencia mientras capturabas. Vuelve a intentarlo.'
                );
            }

            return [
                'id_pago' => (int) $id_pago,
                'monto' => $monto,
                'participantes' => count($solicitudes),
            ];
        });
    }

    /**
     * Solicitudes que la DEC todavía no atiende, de la más antigua a la más
     * reciente: se emiten en el orden en que llegaron.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendientes(): array
    {
        return DB::table('pago as pg')
            ->leftJoin('dato_fiscal as df', 'df.dafi_id_dato_fiscal', '=', 'pg.pago_id_dato_fiscal')
            ->whereRaw(self::PENDIENTE)
            ->orderBy('pg.pago_id_pago')
            ->select([
                'pg.pago_id_pago',
                'pg.pago_no_empleado',
                'pg.pago_monto_pagado',
                'df.dafi_razon_social',
                'df.dafi_rfc',
            ])
            ->get()
            ->map(fn (object $fila): array => [
                'id_pago' => (int) $fila->pago_id_pago,
                'participantes' => (int) $fila->pago_no_empleado,
                'monto' => (float) $fila->pago_monto_pagado,
                'razon_social' => (string) ($fila->dafi_razon_social ?? ''),
                'rfc' => (string) ($fila->dafi_rfc ?? ''),
            ])
            ->all();
    }

    /**
     * Cuántas faltan por emitir. Alimenta el indicador del tablero, así que se
     * cuenta en SQL y no armando la bandeja entera.
     */
    public function totalPendientes(): int
    {
        return DB::table('pago')->whereRaw(self::PENDIENTE)->count();
    }

    /**
     * Detalle de una solicitud pendiente con los participantes que cubre y las
     * referencias del catálogo que se le pueden asignar.
     *
     * @return array<string, mixed>|null
     */
    public function detalle(int $id_pago): ?array
    {
        $pago = DB::table('pago as pg')
            ->leftJoin('dato_fiscal as df', 'df.dafi_id_dato_fiscal', '=', 'pg.pago_id_dato_fiscal')
            ->leftJoin('regimen_fiscal as rf', 'rf.refi_id_regimen_fiscal', '=', 'df.dafi_id_regimen_fiscal')
            ->where('pg.pago_id_pago', $id_pago)
            ->whereRaw(self::PENDIENTE)
            ->select([
                'pg.pago_id_pago',
                'pg.pago_no_empleado',
                'pg.pago_monto_pagado',
                'df.dafi_razon_social',
                'df.dafi_rfc',
                'df.dafi_persona_moral',
                'df.dafi_id_codigo_postal',
                'rf.refi_regimen_fiscal',
            ])
            ->first();

        if (!$pago) {
            return null;
        }

        $monto = (float) $pago->pago_monto_pagado;

        return [
            'id_pago' => (int) $pago->pago_id_pago,
            'participantes' => (int) $pago->pago_no_empleado,
            'monto' => $monto,
            'razon_social' => (string) ($pago->dafi_razon_social ?? ''),
            'rfc' => (string) ($pago->dafi_rfc ?? ''),
            'persona_moral' => (bool) ($pago->dafi_persona_moral ?? false),
            'codigo_postal' => (string) ($pago->dafi_id_codigo_postal ?? ''),
            'regimen_fiscal' => (string) ($pago->refi_regimen_fiscal ?? ''),
            'personas' => $this->personasDelPago($id_pago),
            'candidatas' => $this->referenciasPorMonto($monto),
        ];
    }

    /**
     * Liga al pago la referencia que la DEC eligió y avisa a los participantes.
     *
     * @return array{referencia: string, avisados: int}
     */
    public function emitir(int $id_pago, int $id_referencia): array
    {
        $emitida = DB::transaction(function () use ($id_pago, $id_referencia): array {
            $pago = DB::table('pago')
                ->where('pago_id_pago', $id_pago)
                ->whereRaw(self::PENDIENTE)
                ->lockForUpdate()
                ->first();

            if (!$pago) {
                throw new DomainException('Esta solicitud ya no está pendiente de emisión.');
            }

            $referencia = DB::table('referencia_bancaria')
                ->where('reba_id_referencia_bancaria', $id_referencia)
                ->whereNull('reba_id_pago')
                ->lockForUpdate()
                ->first();

            if (!$referencia) {
                throw new DomainException('La referencia elegida ya no está disponible.');
            }

            if ($this->catalogo->rutaFormatoDisponible($referencia->reba_path ?? null) === null) {
                throw new DomainException('La referencia elegida todavía no tiene su formato de pago.');
            }

            if (!$this->montoCoincide($referencia->reba_monto, (float) $pago->pago_monto_pagado)) {
                throw new DomainException('El importe de la referencia no corresponde al total de los participantes.');
            }

            $ahora = Carbon::now();

            $ligadas = DB::table('referencia_bancaria')
                ->where('reba_id_referencia_bancaria', $id_referencia)
                ->whereNull('reba_id_pago')
                ->update([
                    'reba_id_pago' => $id_pago,
                    'reba_fecha_asignacion' => $ahora->toDateString(),
                    'reba_hora_asignacion' => $ahora->toTimeString(),
                ]);

            if ($ligadas !== 1) {
                throw new DomainException('La referencia se asignó a otra solicitud. Vuelve a intentarlo.');
            }

            $escritos = DB::table('pago')
                ->where('pago_id_pago', $id_pago)
                ->whereRaw(self::PENDIENTE)
                ->update([
                    'pago_referencia_bancaria' => $referencia->reba_referencia,
                    'pago_referencia_bancaria_path' => $referencia->reba_path,
                ]);

            if ($escritos !== 1) {
                throw new DomainException('Esta solicitud ya no está pendiente de emisión.');
            }

            return [
                'referencia' => (string) $referencia->reba_referencia,
                'razon_social' => (string) DB::table('dato_fiscal')
                    ->where('dafi_id_dato_fiscal', $pago->pago_id_dato_fiscal)
                    ->value('dafi_razon_social'),
                'participantes' => (int) $pago->pago_no_empleado,
                'personas' => $this->personasDelPago($id_pago),
            ];
        });

        return [
            'referencia' => $emitida['referencia'],
            /* Fuera de la transacción: un correo caído no puede deshacer una
               referencia que la base ya dio por entregada. */
            'avisados' => $this->avisar($emitida),
        ];
    }

    /**
     * Participantes que cubre un pago compartido.
     *
     * @return array<int, array<string, mixed>>
     */
    private function personasDelPago(int $id_pago): array
    {
        return DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->where('s.soli_id_pago', $id_pago)
            ->orderBy('s.soli_id_solicitud')
            ->select([
                'p.pers_id_persona',
                'p.pers_curp',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
            ])
            ->get()
            ->map(fn (object $fila): array => [
                'id_persona' => (int) $fila->pers_id_persona,
                'curp' => (string) $fila->pers_curp,
                'nombre' => NombrePersona::administrativo(
                    $fila->pers_apellido_paterno,
                    $fila->pers_apellido_materno,
                    $fila->pers_nombre
                ),
            ])
            ->all();
    }

    /**
     * Referencias libres, con formato, cuyo importe cubre el total. Es lo único
     * que se le ofrece a la DEC: asignar una por otro monto dejaría a los
     * participantes con un número con el que el banco no cobra lo que debe.
     *
     * @return array<int, array<string, mixed>>
     */
    private function referenciasPorMonto(float $monto): array
    {
        return DB::table('referencia_bancaria')
            ->whereNull('reba_id_pago')
            ->whereNotNull('reba_path')
            ->where('reba_path', '<>', '')
            ->orderBy('reba_referencia')
            ->select(['reba_id_referencia_bancaria', 'reba_referencia', 'reba_monto', 'reba_vigencia'])
            ->get()
            ->filter(fn (object $fila): bool => $this->montoCoincide($fila->reba_monto, $monto))
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->reba_id_referencia_bancaria,
                'referencia' => (string) $fila->reba_referencia,
                'monto' => (float) $fila->reba_monto,
                'vigencia' => $fila->reba_vigencia,
            ])
            ->values()
            ->all();
    }

    /**
     * Comparación al centavo: los dos lados son DECIMAL(10,4) y llegan como
     * cadena, así que compararlos como flotantes sueltos falla por el redondeo.
     */
    private function montoCoincide(mixed $monto_referencia, float $esperado): bool
    {
        if ($monto_referencia === null) {
            return false;
        }

        return round((float) $monto_referencia, 2) === round($esperado, 2);
    }

    /**
     * Convierte las CURP capturadas en identificadores de solicitud, verificando
     * una por una que se les pueda cobrar.
     *
     * @param array<int, array<string, string>> $participantes
     * @return array<int, int>
     */
    private function resolverParticipantes(array $participantes, mixed $id_convocatoria): array
    {
        $total = count($participantes);

        if ($total < self::MINIMO_PARTICIPANTES) {
            throw new DomainException(
                'La referencia especial cubre al menos '.self::MINIMO_PARTICIPANTES
                .' participantes. Para una sola persona usa la referencia individual.'
            );
        }

        if ($total > self::MAXIMO_PARTICIPANTES) {
            throw new DomainException('La referencia especial admite como máximo '.self::MAXIMO_PARTICIPANTES.' participantes.');
        }

        $curps = $this->curpsCapturadas($participantes);

        if (count(array_unique($curps)) !== $total) {
            throw new DomainException('Hay una CURP repetida en la lista de participantes.');
        }

        $solicitudes = [];

        foreach ($participantes as $participante) {
            $solicitudes[] = $this->solicitudCobrable($participante, $id_convocatoria);
        }

        return $solicitudes;
    }

    /**
     * Identificador de la solicitud de un participante, o el motivo por el que
     * no se le puede incluir. El motivo nombra la CURP: en una lista de veinte,
     * «hay un error» no le sirve a nadie.
     *
     * @param array<string, string> $participante
     */
    private function solicitudCobrable(array $participante, mixed $id_convocatoria): int
    {
        $curp = $this->normalizarCurp($participante['curp'] ?? '');

        $fila = DB::table('persona as p')
            ->join('solicitud as s', 's.soli_id_persona', '=', 'p.pers_id_persona')
            ->where('p.pers_curp', $curp)
            ->orderByDesc('s.soli_id_solicitud')
            ->lockForUpdate()
            ->select([
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                's.soli_id_solicitud',
                's.soli_id_pago',
                's.soli_id_convocatoria',
            ])
            ->first();

        if (!$fila) {
            throw new DomainException($curp.' no tiene una solicitud registrada en el sistema.');
        }

        if ((int) $fila->soli_id_convocatoria !== (int) $id_convocatoria) {
            throw new DomainException($curp.' participa en otra convocatoria y no puede compartir tu referencia.');
        }

        if ($fila->soli_id_pago) {
            throw new DomainException($curp.' ya tiene una referencia bancaria asignada.');
        }

        if ($this->estadoDeSolicitud((int) $fila->soli_id_solicitud) !== 'Aprobada') {
            throw new DomainException($curp.' todavía no tiene su solicitud aprobada.');
        }

        $capturado = $this->comparable([
            $participante['nombre'] ?? '',
            $participante['primer_apellido'] ?? '',
            $participante['segundo_apellido'] ?? '',
        ]);

        $registrado = $this->comparable([
            $fila->pers_nombre,
            $fila->pers_apellido_paterno,
            $fila->pers_apellido_materno,
        ]);

        if ($capturado !== $registrado) {
            throw new DomainException('El nombre capturado para '.$curp.' no coincide con el que tiene registrado.');
        }

        return (int) $fila->soli_id_solicitud;
    }

    /**
     * Alta del pagador. Mismo camino que ComprobanteFiscal::guardarDatosFiscales():
     * CODIGO_POSTAL es un catálogo con llave foránea y sin esta alta capturar
     * un código nuevo revienta.
     *
     * @param array<string, mixed> $pagador
     */
    private function altaDatosFiscales(array $pagador): int
    {
        $id_regimen = (int) $pagador['regimen_fiscal'];

        $regimen_existe = DB::table('regimen_fiscal')
            ->where('refi_id_regimen_fiscal', $id_regimen)
            ->exists();

        if (!$regimen_existe) {
            throw new DomainException('El régimen fiscal seleccionado no existe.');
        }

        DB::table('codigo_postal')->insertOrIgnore([
            'copo_id_codigo_postal' => $pagador['codigo_postal'],
        ]);

        return (int) DB::table('dato_fiscal')->insertGetId([
            'dafi_id_regimen_fiscal' => $id_regimen,
            'dafi_id_codigo_postal' => $pagador['codigo_postal'],
            'dafi_razon_social' => $pagador['razon_social'],
            'dafi_rfc' => $pagador['rfc'],
            'dafi_persona_moral' => (string) $pagador['persona_moral'] === '1',
            /* Quien paga por varios siempre pide comprobante fiscal: por eso
               no se factura a nombre de los participantes. */
            'dafi_uso_cfdi' => true,
        ], 'dafi_id_dato_fiscal');
    }

    /**
     * Solicitud vigente de quien captura, con su CURP y su convocatoria.
     */
    private function solicitudDelUsuario(int $id_usuario): ?object
    {
        return DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->where('p.pers_id_usuario', $id_usuario)
            ->orderByDesc('s.soli_id_solicitud')
            ->lockForUpdate()
            ->select('s.soli_id_solicitud', 's.soli_id_pago', 's.soli_id_convocatoria', 'p.pers_curp')
            ->first();
    }

    private function estadoDeSolicitud(int $id_solicitud): ?string
    {
        return DB::table('estado_solicitud as es')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('es.esso_id_solicitud', $id_solicitud)
            ->orderByDesc('es.esso_id_estado_solicitud')
            ->value('ces.esso_estado_solicitud');
    }

    /**
     * @param array<int, array<string, string>> $participantes
     * @return array<int, string>
     */
    private function curpsCapturadas(array $participantes): array
    {
        return array_map(
            fn (array $participante): string => $this->normalizarCurp($participante['curp'] ?? ''),
            $participantes
        );
    }

    private function normalizarCurp(string $curp): string
    {
        return mb_strtoupper(trim($curp), 'UTF-8');
    }

    /**
     * Nombre en la forma en que se comparan dos capturas: sin acentos, sin
     * espacios de más y en mayúsculas. Quien teclea «Hernandez» no debería
     * chocar con el «Hernández» de la base.
     *
     * @param array<int, ?string> $partes
     */
    private function comparable(array $partes): string
    {
        $texto = mb_strtoupper(trim(implode(' ', array_map('trim', array_filter($partes)))), 'UTF-8');
        $texto = preg_replace('/\s+/u', ' ', $texto);

        return strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        ]);
    }

    /**
     * Avisa a cada participante que su referencia ya está. Devuelve a cuántos
     * salió el correo: quien no tenga correo principal registrado se queda sin
     * aviso, pero eso no invalida la emisión.
     *
     * @param array<string, mixed> $emitida
     */
    private function avisar(array $emitida): int
    {
        $avisados = 0;

        foreach ($emitida['personas'] as $persona) {
            $correo = $this->claves->correoPrincipal($persona['id_persona']);

            if (!$correo) {
                continue;
            }

            try {
                Mail::to($correo)->send(new ReferenciaEspecialEmitida(
                    $emitida['referencia'],
                    $emitida['razon_social'],
                    $emitida['participantes']
                ));

                $avisados++;
            } catch (\Throwable $exception) {
                Log::warning('No fue posible avisar de la referencia especial.', [
                    'persona' => $persona['id_persona'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $avisados;
    }
}
