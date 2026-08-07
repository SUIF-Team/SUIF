<?php

namespace App\Servicios;

use App\Models\Evaluacion;
use App\Models\Sede;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GestionSedes
{
    public function bandeja(array $filtros = []): array
    {
        $todas = $this->filasCatalogo();
        $filtradas = $todas->filter(function (array $sede) use ($filtros): bool {
            $buscar = trim((string) ($filtros['buscar'] ?? ''));
            $ubicacion = trim((string) ($filtros['ubicacion'] ?? ''));
            $estado = (string) ($filtros['estado'] ?? '');

            if ($buscar !== '' && mb_stripos($sede['nombre'], $buscar) === false) {
                return false;
            }

            if ($ubicacion !== '' && mb_stripos($sede['direccion'], $ubicacion) === false) {
                return false;
            }

            return $estado === '' || $sede['estado_clave'] === $estado;
        })->values();

        return [
            'sedes' => $filtradas,
            'resumen' => [
                'sedes_con_cupo' => $todas->where('con_cupo', true)->count(),
                'lugares_disponibles' => $todas->sum('disponibles'),
            ],
        ];
    }

    public function catalogoParticipante(string $buscar = ''): Collection
    {
        return $this->filasCatalogo()
            ->where('programada', true)
            ->filter(function (array $sede) use ($buscar): bool {
                if ($buscar === '') {
                    return true;
                }

                return mb_stripos($sede['nombre'], $buscar) !== false
                    || mb_stripos($sede['direccion'], $buscar) !== false;
            })
            ->values();
    }

    public function disponibilidadParticipante(): array
    {
        return $this->catalogoParticipante()
            ->map(fn (array $sede): array => [
                'evaluacion_id' => $sede['evaluacion_id'],
                'disponibles' => $sede['disponibles'],
                'con_cupo' => $sede['con_cupo'],
            ])
            ->all();
    }

    public function crear(array $datos): Sede
    {
        return DB::transaction(function () use ($datos): Sede {
            $sede = Sede::query()->create([
                'sede_nombre' => $datos['nombre'],
                'sede_direccion' => $datos['direccion'],
                'sede_cupo' => $datos['cupo'],
                'sede_estado' => true,
            ]);

            Evaluacion::query()->create($this->datosEvaluacion($sede->sede_id_sede, $datos));

            return $sede;
        });
    }

    public function actualizar(int $id, array $datos): Sede
    {
        return DB::transaction(function () use ($id, $datos): Sede {
            $sede = Sede::query()->lockForUpdate()->findOrFail($id);
            $evaluacion = Evaluacion::query()
                ->where('eval_id_sede', $id)
                ->lockForUpdate()
                ->first();
            $ocupados = $evaluacion ? $this->ocupados($evaluacion->eval_id_evaluacion) : 0;

            if ((int) $datos['cupo'] < $ocupados) {
                throw new DomainException(
                    "El aforo no puede ser menor que los {$ocupados} lugares ya asignados."
                );
            }

            $sede->fill([
                'sede_nombre' => $datos['nombre'],
                'sede_direccion' => $datos['direccion'],
                'sede_cupo' => $datos['cupo'],
                'sede_estado' => (int) $datos['cupo'] > $ocupados,
            ])->save();

            $valores = $this->datosEvaluacion($id, $datos);
            if ($evaluacion) {
                unset($valores['eval_id_sede'], $valores['eval_resultado']);
                $evaluacion->fill($valores)->save();
            } else {
                Evaluacion::query()->create($valores);
            }

            return $sede;
        });
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $sede = Sede::query()->lockForUpdate()->findOrFail($id);
            $evaluacion = Evaluacion::query()
                ->where('eval_id_sede', $sede->sede_id_sede)
                ->lockForUpdate()
                ->first();

            if ($evaluacion && $this->ocupados($evaluacion->eval_id_evaluacion) > 0) {
                throw new DomainException(
                    'No es posible eliminar la sede porque tiene participantes asignados.'
                );
            }

            if ($evaluacion) {
                $evaluacion->delete();
            }

            $sede->delete();
        });
    }

    public function sedeSeleccionadaPorUsuario(int $idUsuario): ?array
    {
        $fila = DB::table('solicitud as so')
            ->join('persona as p', 'p.pers_id_persona', '=', 'so.soli_id_persona')
            ->join('evaluacion as e', 'e.eval_id_evaluacion', '=', 'so.soli_id_evaluacion')
            ->join('sede as s', 's.sede_id_sede', '=', 'e.eval_id_sede')
            ->where('p.pers_id_usuario', $idUsuario)
            ->orderByDesc('so.soli_id_solicitud')
            ->select([
                's.sede_nombre',
                's.sede_direccion',
                'e.eval_fecha_inicio',
                'e.eval_hora_inicio',
                'e.eval_fecha_fin',
                'e.eval_hora_fin',
            ])
            ->first();

        if (!$fila) {
            return null;
        }

        return [
            'nombre' => $fila->sede_nombre,
            'direccion' => $fila->sede_direccion,
            'fecha_inicio' => (string) $fila->eval_fecha_inicio,
            'hora_inicio' => substr((string) $fila->eval_hora_inicio, 0, 5),
            'fecha_fin' => (string) $fila->eval_fecha_fin,
            'hora_fin' => substr((string) $fila->eval_hora_fin, 0, 5),
        ];
    }

    public function seleccionarParaUsuario(int $idUsuario, int $idEvaluacion): void
    {
        DB::transaction(function () use ($idUsuario, $idEvaluacion): void {
            $referencia = DB::table('evaluacion')
                ->where('eval_id_evaluacion', $idEvaluacion)
                ->select('eval_id_sede')
                ->first();

            if (!$referencia) {
                throw new DomainException('Selecciona una sede válida.');
            }

            $sede = Sede::query()->lockForUpdate()->find($referencia->eval_id_sede);
            $evaluacion = Evaluacion::query()
                ->where('eval_id_evaluacion', $idEvaluacion)
                ->where('eval_id_sede', $referencia->eval_id_sede)
                ->lockForUpdate()
                ->first();

            if (!$sede || !$evaluacion) {
                throw new DomainException('La sede seleccionada ya no está disponible.');
            }

            $solicitud = DB::table('solicitud as so')
                ->join('persona as p', 'p.pers_id_persona', '=', 'so.soli_id_persona')
                ->where('p.pers_id_usuario', $idUsuario)
                ->orderByDesc('so.soli_id_solicitud')
                ->select('so.soli_id_solicitud', 'so.soli_id_pago', 'so.soli_id_evaluacion')
                ->lockForUpdate()
                ->first();

            if (!$solicitud) {
                throw new DomainException('No se encontró una solicitud vigente para asignar la sede.');
            }

            if ((int) $solicitud->soli_id_evaluacion === $idEvaluacion) {
                return;
            }

            if ($solicitud->soli_id_evaluacion !== null) {
                throw new DomainException('Tu sede ya fue confirmada y no puede modificarse.');
            }

            if (!$this->pagoValidado($solicitud->soli_id_pago)) {
                throw new DomainException('La selección de sede se habilita cuando tu pago ha sido validado.');
            }

            $ocupados = $this->ocupados($idEvaluacion);
            if ($ocupados >= $sede->sede_cupo) {
                throw new DomainException('La sede seleccionada ya no tiene lugares disponibles.');
            }

            DB::table('solicitud')
                ->where('soli_id_solicitud', $solicitud->soli_id_solicitud)
                ->update(['soli_id_evaluacion' => $idEvaluacion]);

            $sede->sede_estado = ($ocupados + 1) < $sede->sede_cupo;
            $sede->save();
        }, 3);
    }

    private function filasCatalogo(): Collection
    {
        $ocupaciones = DB::table('solicitud')
            ->selectRaw('soli_id_evaluacion, COUNT(*) AS ocupados')
            ->whereNotNull('soli_id_evaluacion')
            ->groupBy('soli_id_evaluacion');

        return DB::table('sede as s')
            ->leftJoin('evaluacion as e', 'e.eval_id_sede', '=', 's.sede_id_sede')
            ->leftJoinSub($ocupaciones, 'o', 'o.soli_id_evaluacion', '=', 'e.eval_id_evaluacion')
            ->orderBy('s.sede_nombre')
            ->select([
                's.sede_id_sede',
                's.sede_nombre',
                's.sede_direccion',
                's.sede_cupo',
                's.sede_estado',
                'e.eval_id_evaluacion',
                'e.eval_fecha_inicio',
                'e.eval_hora_inicio',
                'e.eval_fecha_fin',
                'e.eval_hora_fin',
                DB::raw('COALESCE(o.ocupados, 0) AS ocupados'),
            ])
            ->get()
            ->map(function (object $fila): array {
                $programada = $fila->eval_id_evaluacion !== null;
                $ocupados = (int) $fila->ocupados;
                $disponibles = $programada
                    ? max((int) $fila->sede_cupo - $ocupados, 0)
                    : 0;
                $conCupo = $programada && $disponibles > 0;

                return [
                    'id' => (int) $fila->sede_id_sede,
                    'evaluacion_id' => $programada ? (int) $fila->eval_id_evaluacion : null,
                    'nombre' => $fila->sede_nombre,
                    'direccion' => $fila->sede_direccion,
                    'cupo' => (int) $fila->sede_cupo,
                    'ocupados' => $ocupados,
                    'disponibles' => $disponibles,
                    'programada' => $programada,
                    'con_cupo' => $conCupo,
                    'estado_clave' => !$programada ? 'pendiente' : ($conCupo ? 'con-cupo' : 'sin-cupo'),
                    'estado' => !$programada ? 'Programación pendiente' : ($conCupo ? 'Con cupo' : 'Sin cupo'),
                    'fecha_inicio' => $programada ? (string) $fila->eval_fecha_inicio : null,
                    'hora_inicio' => $programada ? substr((string) $fila->eval_hora_inicio, 0, 5) : null,
                    'fecha_fin' => $programada ? (string) $fila->eval_fecha_fin : null,
                    'hora_fin' => $programada ? substr((string) $fila->eval_hora_fin, 0, 5) : null,
                ];
            });
    }

    private function datosEvaluacion(int $idSede, array $datos): array
    {
        return [
            'eval_id_sede' => $idSede,
            'eval_fecha_inicio' => $datos['fecha_inicio'],
            'eval_hora_inicio' => $datos['hora_inicio'],
            'eval_fecha_fin' => $datos['fecha_fin'],
            'eval_hora_fin' => $datos['hora_fin'],
            'eval_resultado' => null,
        ];
    }

    private function ocupados(int $idEvaluacion): int
    {
        return DB::table('solicitud')
            ->where('soli_id_evaluacion', $idEvaluacion)
            ->count();
    }

    private function pagoValidado(?int $idPago): bool
    {
        if (!$idPago) {
            return false;
        }

        return DB::table('estado_pago as ep')
            ->join('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->where('ep.espa_id_pago', $idPago)
            ->orderByDesc('ep.espa_id_estado_pago')
            ->value('cep.esta_estado_pago') === 'Completado';
    }
}
