<?php

namespace App\Support\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConsultaPersonasRegistradas
{
    private const ROL_PERSONA = 'Persona';

    /**
     * Obtiene una fila por persona, sin limitarla a una convocatoria vigente.
     * El estado corresponde a la solicitud más reciente de esa persona.
     */
    public function personas(): array
    {
        return $this->consultaPersonas()
            ->orderByDesc('p.pers_fecha_registro')
            ->orderByDesc('p.pers_id_persona')
            ->get()
            ->map(fn (object $persona): array => $this->normalizarPersona($persona))
            ->all();
    }

    /**
     * Devuelve los estados definidos por el catálogo de solicitudes.
     */
    public function estados(): array
    {
        return DB::table('c_estado_solicitud')
            ->orderBy('esso_id_c_estado_solicitud')
            ->pluck('esso_estatus_solicitud')
            ->map(fn (mixed $estado): string => (string) $estado)
            ->all();
    }

    public function resumenDashboard(): array
    {
        return [
            'personas_registradas' => (clone $this->consultaPersonas())->count(),
            'solicitudes_en_revision' => $this->consultaSolicitudes()
                ->where('ces.esso_estatus_solicitud', 'En revisión')
                ->count(),
            'pagos_pendientes' => null,
            'certificados_pendientes' => null,
        ];
    }

    private function consultaPersonas(): Builder
    {
        return DB::table('persona as p')
            ->join('usuario as u', 'u.usua_id_usuario', '=', 'p.pers_id_usuario')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->joinSub($this->ultimasSolicitudes(), 'ultima_solicitud', function ($join): void {
                $join->on('ultima_solicitud.soli_id_persona', '=', 'p.pers_id_persona');
            })
            ->join('solicitud as s', 's.soli_id_solicitud', '=', 'ultima_solicitud.id_solicitud')
            ->joinSub($this->ultimosEstadosSolicitud(), 'ultimo_estado', function ($join): void {
                $join->on('ultimo_estado.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'ultimo_estado.id_estado')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('r.rol_tipo_rol', self::ROL_PERSONA)
            ->whereNotNull('u.usua_clave_acceso')
            ->select([
                'p.pers_id_persona',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
                'p.pers_fecha_registro',
                'ces.esso_estatus_solicitud as estado',
            ]);
    }

    private function consultaSolicitudes(): Builder
    {
        return DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->join('usuario as u', 'u.usua_id_usuario', '=', 'p.pers_id_usuario')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->joinSub($this->ultimosEstadosSolicitud(), 'ultimo_estado', function ($join): void {
                $join->on('ultimo_estado.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'ultimo_estado.id_estado')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('r.rol_tipo_rol', self::ROL_PERSONA)
            ->whereNotNull('u.usua_clave_acceso');
    }

    private function ultimasSolicitudes(): Builder
    {
        return DB::table('solicitud')
            ->whereNotNull('soli_id_persona')
            ->selectRaw('soli_id_persona, MAX(soli_id_solicitud) as id_solicitud')
            ->groupBy('soli_id_persona');
    }

    private function ultimosEstadosSolicitud(): Builder
    {
        return DB::table('estado_solicitud')
            ->selectRaw('esso_id_solicitud, MAX(esso_id_estado_solicitud) as id_estado')
            ->groupBy('esso_id_solicitud');
    }

    private function normalizarPersona(object $persona): array
    {
        $nombre_completo = trim(implode(' ', array_filter([
            $persona->pers_nombre,
            $persona->pers_apellido_paterno,
            $persona->pers_apellido_materno,
        ])));

        return [
            'id' => (string) $persona->pers_id_persona,
            'nombre' => (string) $persona->pers_nombre,
            'primer_apellido' => (string) ($persona->pers_apellido_paterno ?? ''),
            'segundo_apellido' => (string) ($persona->pers_apellido_materno ?? ''),
            'nombre_completo' => $nombre_completo,
            'curp' => (string) $persona->pers_curp,
            'fecha_registro' => $persona->pers_fecha_registro.' 00:00:00',
            'estado' => (string) $persona->estado,
            'clase_estado' => $this->claseEstado((string) $persona->estado),
        ];
    }

    private function claseEstado(string $estado): string
    {
        return match ($estado) {
            'Aprobada' => 'admin-bandeja-preregistros-estado-aceptado',
            'Rechazada', 'Cancelada' => 'admin-bandeja-preregistros-estado-rechazado',
            default => 'admin-bandeja-preregistros-estado-revision',
        };
    }
}
