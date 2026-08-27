<?php

namespace App\Support\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConsultaPersonasRegistradas
{
    private const ROLES_PERSONA = ['Persona', 'Candidato'];

    /** En minúsculas: la comparación con el catálogo ignora mayúsculas. */
    private const ESTADOS_FILTRO = ['en revisión', 'aprobada', 'rechazada'];

    /**
     * Obtiene una fila por persona que ya tuvo una solicitud aprobada, sin
     * limitarla a una convocatoria vigente. Una aprobación representa que el
     * pre-registro y la revisión documental concluyeron satisfactoriamente.
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
     * Estados con los que se puede filtrar la bandeja. Son los tres que le
     * importan a quien revisa; se toman del catálogo para conservar su
     * escritura exacta y se comparan sin distinguir mayúsculas, porque los
     * scripts de la base no coinciden entre sí («En revisión» / «En Revisión»).
     */
    public function estados(): array
    {
        return DB::table('c_estado_solicitud')
            ->orderBy('esso_id_c_estado_solicitud')
            ->pluck('esso_estado_solicitud')
            ->map(fn (mixed $estado): string => (string) $estado)
            ->filter(fn (string $estado): bool => in_array(
                mb_strtolower($estado),
                self::ESTADOS_FILTRO,
                true
            ))
            ->values()
            ->all();
    }

    /**
     * Una persona de la bandeja, con el id de su usuario para operar la
     * cuenta. Devuelve null si no está en la bandeja: solo se restauran
     * claves de personas con solicitud aprobada, rol Persona/Candidato y
     * clave vigente.
     */
    public function persona(int $id_persona): ?array
    {
        $persona = $this->consultaPersonas()
            ->addSelect('u.usua_id_usuario')
            ->where('p.pers_id_persona', $id_persona)
            ->first();

        if (!$persona) {
            return null;
        }

        return $this->normalizarPersona($persona) + [
            'id_usuario' => (int) $persona->usua_id_usuario,
        ];
    }

    public function resumenDashboard(): array
    {
        return [
            'personas_registradas' => (clone $this->consultaPersonas())->count(),
            'solicitudes_en_revision' => $this->consultaSolicitudes()
                ->where('ces.esso_estado_solicitud', 'En revisión')
                ->count(),
            'certificados_pendientes' => null,
        ];
    }

    private function consultaPersonas(): Builder
    {
        return DB::table('persona as p')
            ->join('usuario as u', 'u.usua_id_usuario', '=', 'p.pers_id_usuario')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->joinSub($this->solicitudesAprobadas(), 'solicitud_aprobada', function ($join): void {
                $join->on('solicitud_aprobada.soli_id_persona', '=', 'p.pers_id_persona');
            })
            ->join('solicitud as s', 's.soli_id_solicitud', '=', 'solicitud_aprobada.id_solicitud')
            ->joinSub($this->ultimosEstadosSolicitud(), 'ultimo_estado', function ($join): void {
                $join->on('ultimo_estado.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'ultimo_estado.id_estado')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->whereIn('r.rol_tipo_rol', self::ROLES_PERSONA)
            ->whereNotNull('u.usua_clave_acceso')
            ->where('ces.esso_estado_solicitud', 'Aprobada')
            ->select([
                'p.pers_id_persona',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
                'p.pers_fecha_registro',
                'ces.esso_estado_solicitud as estado',
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
            ->whereIn('r.rol_tipo_rol', self::ROLES_PERSONA)
            ->whereNotNull('u.usua_clave_acceso');
    }

    private function solicitudesAprobadas(): Builder
    {
        return DB::table('solicitud as s')
            ->joinSub($this->ultimosEstadosSolicitud(), 'ultimo_estado', function ($join): void {
                $join->on('ultimo_estado.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'ultimo_estado.id_estado')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->whereNotNull('s.soli_id_persona')
            ->where('ces.esso_estado_solicitud', 'Aprobada')
            ->selectRaw('s.soli_id_persona, MAX(s.soli_id_solicitud) as id_solicitud')
            ->groupBy('s.soli_id_persona');
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
