<?php

namespace App\Support\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConsultaPreRegistros
{
    private const ESTADOS_VISIBLES = [
        'En revisión',
        'Aprobada',
        'Rechazada',
    ];

    /**
     * Obtiene las solicitudes de convocatorias vigentes que ya llegaron a
     * revisión administrativa.
     */
    public function bandeja(): array
    {
        return $this->consultaBase()
            ->orderByDesc('p.pers_fecha_registro')
            ->orderByDesc('s.soli_id_solicitud')
            ->get()
            ->map(fn (object $solicitud): array => $this->normalizarBandeja($solicitud))
            ->all();
    }

    /**
     * Resuelve un expediente de la misma población visible en la bandeja.
     */
    public function solicitud(int $id_solicitud): ?array
    {
        $solicitud = $this->consultaBase()
            ->where('s.soli_id_solicitud', $id_solicitud)
            ->first();

        if (!$solicitud) {
            return null;
        }

        $contactos = $this->contactos((int) $solicitud->pers_id_persona);
        $trabajo = $this->trabajo((int) $solicitud->pers_id_persona);

        return [
            'participante' => [
                'id' => (string) $solicitud->soli_id_solicitud,
                'nombre' => (string) $solicitud->pers_nombre,
                'primer_apellido' => (string) ($solicitud->pers_apellido_paterno ?? ''),
                'segundo_apellido' => (string) ($solicitud->pers_apellido_materno ?? ''),
                'curp' => (string) $solicitud->pers_curp,
                'correo_principal' => $contactos['Correo principal'] ?? 'No registrado',
                'correo_alterno' => $contactos['Correo alterno'] ?? 'No registrado',
                'telefono' => $contactos['Teléfono celular'] ?? 'No registrado',
                'entidad_federativa' => $solicitud->enfe_entidad_federativa ?? 'No registrada',
                'folio' => 'Sin folio asignado',
                'ultimo_grado_estudios' => $this->ultimoGrado((int) $solicitud->pers_id_persona)
                    ?? 'No registrado',
                'actividad_vulnerable' => $trabajo
                    ? ($this->esVerdadero($trabajo->trab_actividad_vulnerable) ? 'Sí' : 'No')
                    : 'No registrado',
                'responsable_cumplimiento' => $trabajo
                    ? ($this->esVerdadero($trabajo->trab_responsable) ? 'Sí' : 'No')
                    : 'No registrado',
                'documentos' => [],
            ],
            'estados' => $this->normalizarEstados((string) $solicitud->estado_solicitud),
        ];
    }

    private function consultaBase(): Builder
    {
        $fecha_actual = now()->toDateString();

        return DB::table('solicitud as s')
            ->join('convocatoria as c', 'c.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->leftJoin('entidad_federativa as ef', 'ef.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            ->joinSub($this->ultimosEstados(), 'ultimo_estado', function ($join): void {
                $join->on('ultimo_estado.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'ultimo_estado.id_estado')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->whereDate('c.conv_fecha_inicio_registro', '<=', $fecha_actual)
            ->whereDate('c.conv_fecha_fin', '>=', $fecha_actual)
            ->whereIn('ces.esso_estatus_solicitud', self::ESTADOS_VISIBLES)
            ->select([
                's.soli_id_solicitud',
                'p.pers_id_persona',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
                'p.pers_fecha_registro',
                'ef.enfe_entidad_federativa',
                'ces.esso_estatus_solicitud as estado_solicitud',
            ]);
    }

    private function ultimosEstados(): Builder
    {
        return DB::table('estado_solicitud')
            ->selectRaw('esso_id_solicitud, MAX(esso_id_estado_solicitud) as id_estado')
            ->groupBy('esso_id_solicitud');
    }

    private function normalizarBandeja(object $solicitud): array
    {
        $nombre_completo = trim(implode(' ', array_filter([
            $solicitud->pers_nombre,
            $solicitud->pers_apellido_paterno,
            $solicitud->pers_apellido_materno,
        ])));

        return [
            'id' => (string) $solicitud->soli_id_solicitud,
            'nombre' => (string) $solicitud->pers_nombre,
            'primer_apellido' => (string) ($solicitud->pers_apellido_paterno ?? ''),
            'segundo_apellido' => (string) ($solicitud->pers_apellido_materno ?? ''),
            'nombre_completo' => $nombre_completo,
            'curp' => (string) $solicitud->pers_curp,
            'fecha_registro' => $solicitud->pers_fecha_registro.' 00:00:00',
            'estado_bandeja' => (string) $solicitud->estado_solicitud,
            'clase_estado' => $this->claseEstado((string) $solicitud->estado_solicitud),
        ];
    }

    private function contactos(int $id_persona): array
    {
        $contactos = [];

        DB::table('comunicacion as co')
            ->join('tipo_comunicacion as tc', 'tc.tico_id_tipo_comunicacion', '=', 'co.comu_id_tipo_comunicacion')
            ->where('co.comu_id_persona', $id_persona)
            ->orderByDesc('co.comu_id_comunicacion')
            ->select('tc.tico_tipo_comunicacion', 'co.comu_descripcion')
            ->get()
            ->each(function (object $contacto) use (&$contactos): void {
                if (!isset($contactos[$contacto->tico_tipo_comunicacion])) {
                    $contactos[$contacto->tico_tipo_comunicacion] = $contacto->comu_descripcion;
                }
            });

        return $contactos;
    }

    private function ultimoGrado(int $id_persona): ?string
    {
        return DB::table('grado_persona as gp')
            ->join('nivel_profesional as np', 'np.nipr_id_nivel_profesional', '=', 'gp.grpe_id_nivel_profesional')
            ->where('gp.grpe_id_persona', $id_persona)
            ->orderByDesc('gp.grpe_id_grado_persona')
            ->value('np.nipr_nivel_profesional');
    }

    private function trabajo(int $id_persona): ?object
    {
        return DB::table('trabajo_persona as tp')
            ->join('trabajo as t', 't.trab_id_trabajo', '=', 'tp.trpe_id_trabajo')
            ->where('tp.trpe_id_persona', $id_persona)
            ->orderByDesc('tp.trpe_id_trabajo_persona')
            ->select('t.trab_actividad_vulnerable', 't.trab_responsable')
            ->first();
    }

    private function normalizarEstados(string $estado_solicitud): array
    {
        return match ($estado_solicitud) {
            'Aprobada' => [
                'general' => 'Aprobada',
                'preregistro' => 'Completado',
                'documentacion' => 'Completado',
            ],
            'Rechazada' => [
                'general' => 'Rechazada',
                'preregistro' => 'Rechazado',
                'documentacion' => 'Pendiente',
            ],
            default => [
                'general' => 'En revisión',
                'preregistro' => 'Completado',
                'documentacion' => 'En revisión',
            ],
        };
    }

    private function claseEstado(string $estado_solicitud): string
    {
        return match ($estado_solicitud) {
            'Aprobada' => 'admin-bandeja-preregistros-estado-aceptado',
            'Rechazada' => 'admin-bandeja-preregistros-estado-rechazado',
            default => 'admin-bandeja-preregistros-estado-revision',
        };
    }

    private function esVerdadero(mixed $valor): bool
    {
        return in_array($valor, [true, 1, '1', 't', 'true'], true);
    }
}
