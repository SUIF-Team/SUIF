<?php

namespace App\Support\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConsultaPagos
{
    public const PENDIENTE = 'Pendiente';

    public const COMPLETADO = 'Completado';

    public const DECLINADO = 'Declinado';

    private const ROLES_PERSONA = ['Persona', 'Candidato'];

    /**
     * Devuelve todos los comprobantes registrados para personas solicitantes.
     */
    public function bandeja(): array
    {
        return $this->consultaBase()
            ->get()
            ->map(fn (object $pago): array => $this->normalizar($pago))
            ->sortByDesc('fecha_envio_comprobante')
            ->values()
            ->all();
    }

    /**
     * Busca un pago dentro de la misma población visible en la bandeja.
     */
    public function pago(int $id_pago): ?array
    {
        $pago = $this->consultaBase()
            ->where('pg.pago_id_pago', $id_pago)
            ->first();

        return $pago ? $this->normalizar($pago) : null;
    }

    public function archivoDisponible(?string $ruta): bool
    {
        return $this->rutaArchivoValida($ruta)
            && Storage::disk('comprobantes')->exists($ruta);
    }

    public function rutaArchivoValida(?string $ruta): bool
    {
        if (!is_string($ruta) || $ruta === '' || str_starts_with($ruta, '/')) {
            return false;
        }

        return !str_contains($ruta, '..') && str_ends_with(mb_strtolower($ruta), '.pdf');
    }

    private function consultaBase(): Builder
    {
        return DB::table('pago as pg')
            ->join('solicitud as s', 's.soli_id_pago', '=', 'pg.pago_id_pago')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->join('usuario as u', 'u.usua_id_usuario', '=', 'p.pers_id_usuario')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->leftJoin('entidad_federativa as ef', 'ef.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            ->leftJoinSub($this->ultimosEstadosPago(), 'estado_actual', function ($join): void {
                $join->on('estado_actual.espa_id_pago', '=', 'pg.pago_id_pago');
            })
            ->leftJoin('estado_pago as ep', 'ep.espa_id_estado_pago', '=', 'estado_actual.id_estado')
            ->leftJoin('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->leftJoinSub($this->ultimosEstadosPendiente(), 'ultimo_envio', function ($join): void {
                $join->on('ultimo_envio.espa_id_pago', '=', 'pg.pago_id_pago');
            })
            ->leftJoin('estado_pago as envio', 'envio.espa_id_estado_pago', '=', 'ultimo_envio.id_estado')
            ->leftJoinSub($this->primerosEstadosPago(), 'primer_estado', function ($join): void {
                $join->on('primer_estado.espa_id_pago', '=', 'pg.pago_id_pago');
            })
            ->leftJoin('estado_pago as primer', 'primer.espa_id_estado_pago', '=', 'primer_estado.id_estado')
            ->leftJoinSub($this->ultimosEstadosSolicitud(), 'estado_solicitud_actual', function ($join): void {
                $join->on('estado_solicitud_actual.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->leftJoin('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'estado_solicitud_actual.id_estado')
            ->leftJoin('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->whereIn('r.rol_tipo_rol', self::ROLES_PERSONA)
            ->whereNotNull('pg.pago_comprobante_path')
            ->where('pg.pago_comprobante_path', '<>', '')
            ->select([
                'pg.pago_id_pago',
                'pg.pago_comprobante_path',
                'pg.pago_monto_pagado',
                'pg.pago_referencia_bancaria',
                'pg.pago_fecha_pago',
                'pg.pago_hora_pago',
                's.soli_id_solicitud',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
                'ef.enfe_entidad_federativa',
                'cep.esta_estado_pago',
                'ep.espa_comentario',
                'envio.espa_fecha as fecha_envio',
                'envio.espa_hora as hora_envio',
                'primer.espa_fecha as fecha_primer_estado',
                'primer.espa_hora as hora_primer_estado',
                'ces.esso_estatus_solicitud as estado_solicitud',
            ]);
    }

    private function ultimosEstadosPago(): Builder
    {
        return DB::table('estado_pago')
            ->selectRaw('espa_id_pago, MAX(espa_id_estado_pago) as id_estado')
            ->groupBy('espa_id_pago');
    }

    private function primerosEstadosPago(): Builder
    {
        return DB::table('estado_pago')
            ->selectRaw('espa_id_pago, MIN(espa_id_estado_pago) as id_estado')
            ->groupBy('espa_id_pago');
    }

    private function ultimosEstadosPendiente(): Builder
    {
        return DB::table('estado_pago as esp')
            ->join('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'esp.espa_id_c_estado_pago')
            ->where('cep.esta_estado_pago', self::PENDIENTE)
            ->selectRaw('esp.espa_id_pago, MAX(esp.espa_id_estado_pago) as id_estado')
            ->groupBy('esp.espa_id_pago');
    }

    private function ultimosEstadosSolicitud(): Builder
    {
        return DB::table('estado_solicitud')
            ->selectRaw('esso_id_solicitud, MAX(esso_id_estado_solicitud) as id_estado')
            ->groupBy('esso_id_solicitud');
    }

    private function normalizar(object $pago): array
    {
        $estado_persistido = (string) ($pago->esta_estado_pago ?: self::PENDIENTE);
        $estatus = $this->etiquetaEstado($estado_persistido);
        $archivo_disponible = $this->archivoDisponible($pago->pago_comprobante_path);
        $estado_solicitud = (string) ($pago->estado_solicitud ?: 'Pendiente');
        $puede_revisarse = $estado_persistido === self::PENDIENTE
            && $archivo_disponible
            && $estado_solicitud === 'Aprobada';

        $fecha_envio = $this->fechaHora(
            $pago->fecha_envio,
            $pago->hora_envio,
            $pago->fecha_primer_estado,
            $pago->hora_primer_estado,
            $pago->pago_fecha_pago,
            $pago->pago_hora_pago
        );

        $nombre = trim(implode(' ', array_filter([
            $pago->pers_nombre,
            $pago->pers_apellido_paterno,
            $pago->pers_apellido_materno,
        ])));

        return [
            'id' => (string) $pago->pago_id_pago,
            'id_solicitud' => (string) $pago->soli_id_solicitud,
            'nombre' => (string) $pago->pers_nombre,
            'primer_apellido' => (string) ($pago->pers_apellido_paterno ?? ''),
            'segundo_apellido' => (string) ($pago->pers_apellido_materno ?? ''),
            'nombre_completo' => $nombre,
            'iniciales' => $this->iniciales($pago->pers_nombre, $pago->pers_apellido_paterno),
            'curp' => (string) $pago->pers_curp,
            'entidad_federativa' => (string) ($pago->enfe_entidad_federativa ?? 'Sin información'),
            'estatus' => $estatus,
            'estado_persistido' => $estado_persistido,
            'clase_estado' => $this->claseEstado($estado_persistido),
            'clase_estado_detalle' => $this->claseEstadoDetalle($estado_persistido),
            'fecha_envio_comprobante' => $fecha_envio,
            'estado_preregistro' => $this->estadoPreRegistro($estado_solicitud),
            'estado_documentacion' => $this->estadoDocumentacion($estado_solicitud),
            'clase_paso_preregistro' => $this->clasePaso($this->estadoPreRegistro($estado_solicitud)),
            'clase_paso_documentacion' => $this->clasePaso($this->estadoDocumentacion($estado_solicitud)),
            'clase_paso_pago' => $this->clasePaso($estatus),
            'comprobante' => [
                'nombre' => basename((string) $pago->pago_comprobante_path),
            ],
            'comprobante_disponible' => $archivo_disponible,
            'monto' => '$'.number_format((float) $pago->pago_monto_pagado, 2).' '.config('suif.moneda', 'MXN'),
            'referencia_bancaria' => (string) $pago->pago_referencia_bancaria,
            'fecha_pago' => (string) $pago->pago_fecha_pago,
            'hora_pago' => (string) $pago->pago_hora_pago,
            'motivo_rechazo' => $estado_persistido === self::DECLINADO
                ? trim((string) $pago->espa_comentario)
                : null,
            'puede_revisarse' => $puede_revisarse,
            'mensaje_revision_no_disponible' => $this->mensajeNoDisponible(
                $estado_persistido,
                $archivo_disponible,
                $estado_solicitud
            ),
        ];
    }

    private function fechaHora(
        mixed $fecha_envio,
        mixed $hora_envio,
        mixed $fecha_primer_estado,
        mixed $hora_primer_estado,
        mixed $fecha_pago,
        mixed $hora_pago
    ): string {
        $fecha = $fecha_envio ?: ($fecha_primer_estado ?: $fecha_pago);
        $hora = $hora_envio ?: ($hora_primer_estado ?: $hora_pago);

        return trim((string) $fecha.' '.(string) $hora);
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            self::COMPLETADO => 'Aprobado',
            self::DECLINADO => 'Rechazado',
            default => 'Por revisar',
        };
    }

    private function claseEstado(string $estado): string
    {
        return match ($estado) {
            self::COMPLETADO => 'admin-bandeja-preregistros-estado-aceptado',
            self::DECLINADO => 'admin-bandeja-preregistros-estado-rechazado',
            default => 'admin-bandeja-preregistros-estado-revision',
        };
    }

    private function claseEstadoDetalle(string $estado): string
    {
        return match ($estado) {
            self::COMPLETADO => 'admin-preregistro-estado--completado',
            self::DECLINADO => 'admin-preregistro-estado--rechazado',
            default => 'admin-preregistro-estado--revision',
        };
    }

    private function estadoPreRegistro(string $estado_solicitud): string
    {
        return $estado_solicitud === 'Rechazada' ? 'Rechazado' : 'Completado';
    }

    private function estadoDocumentacion(string $estado_solicitud): string
    {
        return $estado_solicitud === 'Aprobada' ? 'Completado' : 'En revisión';
    }

    private function clasePaso(string $estado): string
    {
        return match ($estado) {
            'Completado', 'Aprobado' => 'admin-preregistro-paso--completado',
            'Rechazado' => 'admin-preregistro-paso--rechazado',
            'Por revisar', 'En revisión' => 'admin-preregistro-paso--actual',
            default => 'admin-preregistro-paso--pendiente',
        };
    }

    private function mensajeNoDisponible(
        string $estado_persistido,
        bool $archivo_disponible,
        string $estado_solicitud
    ): ?string {
        if ($estado_persistido !== self::PENDIENTE) {
            return 'El pago ya fue resuelto y sólo puede consultarse.';
        }

        if (!$archivo_disponible) {
            return 'El archivo del comprobante no está disponible para revisión.';
        }

        if ($estado_solicitud !== 'Aprobada') {
            return 'La solicitud aún no cumple los requisitos previos para revisar el pago.';
        }

        return null;
    }

    private function iniciales(?string $nombre, ?string $apellido): string
    {
        return mb_strtoupper(
            mb_substr((string) $nombre, 0, 1)
            .mb_substr((string) $apellido, 0, 1)
        );
    }
}
