<?php

namespace App\Servicios;

use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Sede;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Una sede aplica el examen una o más veces. Cada aplicación es un GRUPO con
 * su propio horario y su propia EVALUACION, y SEDE_CUPO es el aforo de cada
 * aplicación, no el total de la sede.
 */
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

    /**
     * Lista plana de horarios para el sondeo de cupos del participante.
     */
    public function disponibilidadParticipante(): array
    {
        return $this->catalogoParticipante()
            ->flatMap(fn (array $sede): array => array_map(
                fn (array $horario): array => [
                    'evaluacion_id' => $horario['evaluacion_id'],
                    'disponibles' => $horario['disponibles'],
                    'con_cupo' => $horario['con_cupo'],
                ],
                $sede['horarios']
            ))
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

            $this->sincronizarHorarios($sede->sede_id_sede, $datos['horarios']);

            $sede->sede_estado = $this->sedeConCupo($sede->sede_id_sede, (int) $datos['cupo']);
            $sede->save();

            return $sede;
        });
    }

    public function actualizar(int $id, array $datos): Sede
    {
        return DB::transaction(function () use ($id, $datos): Sede {
            $sede = Sede::query()->lockForUpdate()->findOrFail($id);
            $ocupacion_mayor = $this->ocupacionMayor($id);

            if ((int) $datos['cupo'] < $ocupacion_mayor) {
                throw new DomainException(
                    "El aforo no puede ser menor que los {$ocupacion_mayor} lugares ya asignados en una de las aplicaciones."
                );
            }

            $sede->fill([
                'sede_nombre' => $datos['nombre'],
                'sede_direccion' => $datos['direccion'],
                'sede_cupo' => $datos['cupo'],
            ])->save();

            $this->sincronizarHorarios($id, $datos['horarios']);

            $sede->sede_estado = $this->sedeConCupo($id, (int) $datos['cupo']);
            $sede->save();

            return $sede;
        });
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $sede = Sede::query()->lockForUpdate()->findOrFail($id);
            $grupos = Grupo::query()
                ->where('sede_id_sede', $sede->sede_id_sede)
                ->lockForUpdate()
                ->get();

            foreach ($grupos as $grupo) {
                $evaluacion = $this->evaluacionDeGrupo($grupo->grup_id_grupo);

                if ($evaluacion && $this->ocupados($evaluacion->eval_id_evaluacion) > 0) {
                    throw new DomainException(
                        'No es posible eliminar la sede porque tiene participantes asignados.'
                    );
                }
            }

            foreach ($grupos as $grupo) {
                $this->eliminarGrupo($grupo);
            }

            $sede->delete();
        });
    }

    public function sedeSeleccionadaPorUsuario(int $idUsuario): ?array
    {
        $fila = DB::table('solicitud as so')
            ->join('persona as p', 'p.pers_id_persona', '=', 'so.soli_id_persona')
            ->join('evaluacion as e', 'e.eval_id_evaluacion', '=', 'so.soli_id_evaluacion')
            ->join('grupo as g', 'g.grup_id_grupo', '=', 'e.grup_id_grupo')
            ->join('sede as s', 's.sede_id_sede', '=', 'g.sede_id_sede')
            ->where('p.pers_id_usuario', $idUsuario)
            ->orderByDesc('so.soli_id_solicitud')
            ->select([
                's.sede_nombre',
                's.sede_direccion',
                'g.grup_fecha_inicio',
                'g.grup_hora_inicio',
                'g.grup_fecha_fin',
                'g.grup_hora_fin',
            ])
            ->first();

        if (!$fila) {
            return null;
        }

        return [
            'nombre' => $fila->sede_nombre,
            'direccion' => $fila->sede_direccion,
            'fecha_inicio' => (string) $fila->grup_fecha_inicio,
            'hora_inicio' => substr((string) $fila->grup_hora_inicio, 0, 5),
            'fecha_fin' => (string) $fila->grup_fecha_fin,
            'hora_fin' => substr((string) $fila->grup_hora_fin, 0, 5),
        ];
    }

    public function seleccionarParaUsuario(int $idUsuario, int $idEvaluacion): void
    {
        DB::transaction(function () use ($idUsuario, $idEvaluacion): void {
            $referencia = DB::table('evaluacion as e')
                ->join('grupo as g', 'g.grup_id_grupo', '=', 'e.grup_id_grupo')
                ->where('e.eval_id_evaluacion', $idEvaluacion)
                ->select('g.sede_id_sede')
                ->first();

            if (!$referencia) {
                throw new DomainException('Selecciona un horario válido.');
            }

            $sede = Sede::query()->lockForUpdate()->find($referencia->sede_id_sede);

            if (!$sede) {
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

            if ($this->ocupados($idEvaluacion) >= $sede->sede_cupo) {
                throw new DomainException('El horario seleccionado ya no tiene lugares disponibles.');
            }

            DB::table('solicitud')
                ->where('soli_id_solicitud', $solicitud->soli_id_solicitud)
                ->update(['soli_id_evaluacion' => $idEvaluacion]);

            $sede->sede_estado = $this->sedeConCupo($sede->sede_id_sede, (int) $sede->sede_cupo);
            $sede->save();
        }, 3);
    }

    /**
     * Deja los grupos de la sede exactamente como vienen en el formulario:
     * actualiza los que conservan su identificador, agrega los nuevos y
     * elimina los que se quitaron.
     */
    private function sincronizarHorarios(int $idSede, array $horarios): void
    {
        $existentes = Grupo::query()
            ->where('sede_id_sede', $idSede)
            ->lockForUpdate()
            ->get()
            ->keyBy('grup_id_grupo');

        $conservados = [];

        foreach ($horarios as $horario) {
            $id_grupo = (int) ($horario['grupo_id'] ?? 0);
            $grupo = $id_grupo ? $existentes->get($id_grupo) : null;

            if ($grupo) {
                $grupo->fill($this->datosGrupo($idSede, $horario))->save();
                $conservados[] = $grupo->grup_id_grupo;
            } else {
                $grupo = Grupo::query()->create($this->datosGrupo($idSede, $horario));
                $conservados[] = $grupo->grup_id_grupo;
            }

            if (!$this->evaluacionDeGrupo($grupo->grup_id_grupo)) {
                Evaluacion::query()->create([
                    'grup_id_grupo' => $grupo->grup_id_grupo,
                    'eval_resultado' => null,
                ]);
            }
        }

        foreach ($existentes as $grupo) {
            if (in_array($grupo->grup_id_grupo, $conservados, true)) {
                continue;
            }

            $evaluacion = $this->evaluacionDeGrupo($grupo->grup_id_grupo);

            if ($evaluacion && $this->ocupados($evaluacion->eval_id_evaluacion) > 0) {
                throw new DomainException(
                    'No es posible quitar una aplicación que ya tiene participantes asignados.'
                );
            }

            $this->eliminarGrupo($grupo);
        }
    }

    private function eliminarGrupo(Grupo $grupo): void
    {
        $evaluacion = $this->evaluacionDeGrupo($grupo->grup_id_grupo);

        if ($evaluacion) {
            $evaluacion->delete();
        }

        $grupo->delete();
    }

    private function filasCatalogo(): Collection
    {
        $ocupaciones = DB::table('solicitud')
            ->selectRaw('soli_id_evaluacion, COUNT(*) AS ocupados')
            ->whereNotNull('soli_id_evaluacion')
            ->groupBy('soli_id_evaluacion');

        return DB::table('sede as s')
            ->leftJoin('grupo as g', 'g.sede_id_sede', '=', 's.sede_id_sede')
            ->leftJoin('evaluacion as e', 'e.grup_id_grupo', '=', 'g.grup_id_grupo')
            ->leftJoinSub($ocupaciones, 'o', 'o.soli_id_evaluacion', '=', 'e.eval_id_evaluacion')
            ->orderBy('s.sede_nombre')
            ->orderBy('g.grup_fecha_inicio')
            ->orderBy('g.grup_hora_inicio')
            ->select([
                's.sede_id_sede',
                's.sede_nombre',
                's.sede_direccion',
                's.sede_cupo',
                's.sede_estado',
                'g.grup_id_grupo',
                'e.eval_id_evaluacion',
                'g.grup_fecha_inicio',
                'g.grup_hora_inicio',
                'g.grup_fecha_fin',
                'g.grup_hora_fin',
                DB::raw('COALESCE(o.ocupados, 0) AS ocupados'),
            ])
            ->get()
            ->groupBy('sede_id_sede')
            ->map(function (Collection $filas): array {
                $sede = $filas->first();
                $cupo = (int) $sede->sede_cupo;

                $horarios = $filas
                    ->filter(fn (object $fila): bool => $fila->eval_id_evaluacion !== null)
                    ->map(function (object $fila) use ($cupo): array {
                        $ocupados = (int) $fila->ocupados;
                        $disponibles = max($cupo - $ocupados, 0);

                        return [
                            'evaluacion_id' => (int) $fila->eval_id_evaluacion,
                            'grupo_id' => (int) $fila->grup_id_grupo,
                            'fecha_inicio' => (string) $fila->grup_fecha_inicio,
                            'hora_inicio' => substr((string) $fila->grup_hora_inicio, 0, 5),
                            'fecha_fin' => (string) $fila->grup_fecha_fin,
                            'hora_fin' => substr((string) $fila->grup_hora_fin, 0, 5),
                            'ocupados' => $ocupados,
                            'disponibles' => $disponibles,
                            'con_cupo' => $disponibles > 0,
                        ];
                    })
                    ->values()
                    ->all();

                $programada = $horarios !== [];
                $con_cupo = (bool) array_filter($horarios, fn (array $horario): bool => $horario['con_cupo']);

                return [
                    'id' => (int) $sede->sede_id_sede,
                    'nombre' => $sede->sede_nombre,
                    'direccion' => $sede->sede_direccion,
                    'cupo' => $cupo,
                    'ocupados' => array_sum(array_column($horarios, 'ocupados')),
                    'disponibles' => array_sum(array_column($horarios, 'disponibles')),
                    'programada' => $programada,
                    'con_cupo' => $con_cupo,
                    'estado_clave' => !$programada ? 'pendiente' : ($con_cupo ? 'con-cupo' : 'sin-cupo'),
                    'estado' => !$programada ? 'Pendiente' : ($con_cupo ? 'Con cupo' : 'Sin cupo'),
                    'horarios' => $horarios,
                ];
            })
            ->sortBy('nombre')
            ->values();
    }

    private function datosGrupo(int $idSede, array $horario): array
    {
        return [
            'sede_id_sede' => $idSede,
            'grup_fecha_inicio' => $horario['fecha_inicio'],
            'grup_hora_inicio' => $horario['hora_inicio'],
            'grup_fecha_fin' => $horario['fecha_fin'],
            'grup_hora_fin' => $horario['hora_fin'],
        ];
    }

    private function evaluacionDeGrupo(int $idGrupo): ?Evaluacion
    {
        return Evaluacion::query()
            ->where('grup_id_grupo', $idGrupo)
            ->lockForUpdate()
            ->first();
    }

    private function ocupados(int $idEvaluacion): int
    {
        return DB::table('solicitud')
            ->where('soli_id_evaluacion', $idEvaluacion)
            ->count();
    }

    /**
     * Lugares asignados en la aplicación más llena de la sede. El aforo no
     * puede quedar por debajo de esa cifra.
     */
    private function ocupacionMayor(int $idSede): int
    {
        return (int) DB::table('grupo as g')
            ->join('evaluacion as e', 'e.grup_id_grupo', '=', 'g.grup_id_grupo')
            ->leftJoin('solicitud as so', 'so.soli_id_evaluacion', '=', 'e.eval_id_evaluacion')
            ->where('g.sede_id_sede', $idSede)
            ->groupBy('e.eval_id_evaluacion')
            ->selectRaw('COUNT(so.soli_id_solicitud) AS ocupados')
            ->pluck('ocupados')
            ->max();
    }

    /**
     * Una sede ofrece cupo mientras al menos una de sus aplicaciones tenga
     * lugares libres.
     */
    private function sedeConCupo(int $idSede, int $cupo): bool
    {
        $ocupaciones = DB::table('grupo as g')
            ->join('evaluacion as e', 'e.grup_id_grupo', '=', 'g.grup_id_grupo')
            ->leftJoin('solicitud as so', 'so.soli_id_evaluacion', '=', 'e.eval_id_evaluacion')
            ->where('g.sede_id_sede', $idSede)
            ->groupBy('e.eval_id_evaluacion')
            ->selectRaw('COUNT(so.soli_id_solicitud) AS ocupados')
            ->pluck('ocupados');

        return $ocupaciones->contains(fn (mixed $ocupados): bool => (int) $ocupados < $cupo);
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
