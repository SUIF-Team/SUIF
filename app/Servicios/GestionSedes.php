<?php

namespace App\Servicios;

use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Sede;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Una sede aplica el examen una o más veces. Cada aplicación es un GRUPO con
 * su propio horario y su propia EVALUACION, y SEDE_CUPO es el aforo de cada
 * aplicación, no el total de la sede.
 *
 * El lugar y su programación se capturan por separado: la sede se da de alta
 * sola y queda «Por programar» hasta que el módulo de grupos le registra su
 * primera aplicación. Hasta entonces no se le muestra a la persona.
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

    /**
     * La sede nace sin programación, así que todavía no ofrece cupo.
     */
    public function crear(array $datos): Sede
    {
        return Sede::query()->create([
            'sede_nombre' => $datos['nombre'],
            'sede_direccion' => $datos['direccion'],
            'sede_cupo' => $datos['cupo'],
            'sede_estado' => false,
        ]);
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
                'sede_estado' => $this->sedeConCupo($id, (int) $datos['cupo']),
            ])->save();

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

    /* ── Grupos: la programación de cada sede ───────────────────────────── */

    public function bandejaGrupos(array $filtros = []): array
    {
        $todos = $this->filasGrupos();
        $sede_filtro = trim((string) ($filtros['sede'] ?? ''));
        $estado_filtro = (string) ($filtros['estado'] ?? '');

        $filtrados = $todos->filter(function (array $grupo) use ($sede_filtro, $estado_filtro): bool {
            if ($sede_filtro !== '' && (string) $grupo['sede_id'] !== $sede_filtro) {
                return false;
            }

            return $estado_filtro === '' || $grupo['estado_clave'] === $estado_filtro;
        })->values();

        return [
            'grupos' => $filtrados,
            'resumen' => [
                'grupos_registrados' => $todos->count(),
                'lugares_disponibles' => $todos->sum('disponibles'),
            ],
        ];
    }

    /**
     * Sedes para el selector del formulario de grupo: ahí sí se ve el aforo y
     * el estatus de cada una, incluidas las que aún están por programar.
     */
    public function sedesParaGrupo(): Collection
    {
        return $this->filasCatalogo()
            ->map(fn (array $sede): array => [
                'id' => $sede['id'],
                'nombre' => $sede['nombre'],
                'direccion' => $sede['direccion'],
                'cupo' => $sede['cupo'],
                'grupos' => count($sede['horarios']),
                'disponibles' => $sede['disponibles'],
                'estado' => $sede['estado'],
                'estado_clave' => $sede['estado_clave'],
            ])
            ->values();
    }

    public function grupo(int $id): array
    {
        $grupo = $this->filasGrupos()->firstWhere('id', $id);

        if (!$grupo) {
            throw new DomainException('El grupo indicado no existe.');
        }

        return $grupo;
    }

    public function crearGrupo(array $datos): Grupo
    {
        return DB::transaction(function () use ($datos): Grupo {
            $sede = Sede::query()->lockForUpdate()->find($datos['sede_id']);

            if (!$sede) {
                throw new DomainException('La sede seleccionada ya no está disponible.');
            }

            $grupo = Grupo::query()->create($this->datosGrupo((int) $sede->sede_id_sede, $datos));

            /* Cada grupo tiene exactamente una evaluación: es contra ella que
               se cuenta el cupo y a la que apunta la solicitud de la persona. */
            Evaluacion::query()->create([
                'grup_id_grupo' => $grupo->grup_id_grupo,
                'eval_resultado' => null,
            ]);

            $this->refrescarEstadoSede($sede);

            return $grupo;
        });
    }

    public function actualizarGrupo(int $id, array $datos): Grupo
    {
        return DB::transaction(function () use ($id, $datos): Grupo {
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($id);
            $id_sede_anterior = (int) $grupo->sede_id_sede;
            $id_sede_nueva = (int) $datos['sede_id'];
            $evaluacion = $this->evaluacionDeGrupo($grupo->grup_id_grupo);
            $ocupados = $evaluacion ? $this->ocupados($evaluacion->eval_id_evaluacion) : 0;

            /* Las personas ya asignadas se enteraron de una sede; moverlas de
               lugar sin avisarles no es una edición, es otro trámite. */
            if ($id_sede_nueva !== $id_sede_anterior && $ocupados > 0) {
                throw new DomainException(
                    'No es posible cambiar de sede un grupo que ya tiene participantes asignados.'
                );
            }

            $sede = Sede::query()->lockForUpdate()->find($id_sede_nueva);

            if (!$sede) {
                throw new DomainException('La sede seleccionada ya no está disponible.');
            }

            $grupo->fill($this->datosGrupo($id_sede_nueva, $datos))->save();

            if (!$evaluacion) {
                Evaluacion::query()->create([
                    'grup_id_grupo' => $grupo->grup_id_grupo,
                    'eval_resultado' => null,
                ]);
            }

            $this->refrescarEstadoSede($sede);

            if ($id_sede_nueva !== $id_sede_anterior) {
                $anterior = Sede::query()->lockForUpdate()->find($id_sede_anterior);

                if ($anterior) {
                    $this->refrescarEstadoSede($anterior);
                }
            }

            return $grupo;
        });
    }

    public function eliminarGrupoPorId(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $grupo = Grupo::query()->lockForUpdate()->findOrFail($id);
            $evaluacion = $this->evaluacionDeGrupo($grupo->grup_id_grupo);

            if ($evaluacion && $this->ocupados($evaluacion->eval_id_evaluacion) > 0) {
                throw new DomainException(
                    'No es posible eliminar un grupo que ya tiene participantes asignados.'
                );
            }

            $sede = Sede::query()->lockForUpdate()->find($grupo->sede_id_sede);

            $this->eliminarGrupo($grupo);

            if ($sede) {
                $this->refrescarEstadoSede($sede);
            }
        });
    }

    /**
     * Dos aplicaciones de la misma sede no pueden traslaparse: la sala es una.
     */
    public function seEmpalma(int $idSede, Carbon $inicio, Carbon $fin, ?int $excluir = null): bool
    {
        return Grupo::query()
            ->where('sede_id_sede', $idSede)
            ->when($excluir, fn ($consulta) => $consulta->where('grup_id_grupo', '!=', $excluir))
            ->get()
            ->contains(function (Grupo $otro) use ($inicio, $fin): bool {
                $otro_inicio = $this->momento($otro->grup_fecha_inicio, $otro->grup_hora_inicio);
                $otro_fin = $this->momento($otro->grup_fecha_fin, $otro->grup_hora_fin);

                return $inicio->lessThan($otro_fin) && $otro_inicio->lessThan($fin);
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
                    'estado' => !$programada ? 'Por programar' : ($con_cupo ? 'Con cupo' : 'Sin cupo'),
                    'horarios' => $horarios,
                ];
            })
            ->sortBy('nombre')
            ->values();
    }

    /**
     * Un renglón por grupo, con la sede a la que pertenece y la ocupación de
     * su evaluación. El cupo del grupo es el aforo de su sede.
     */
    private function filasGrupos(): Collection
    {
        $ocupaciones = DB::table('solicitud')
            ->selectRaw('soli_id_evaluacion, COUNT(*) AS ocupados')
            ->whereNotNull('soli_id_evaluacion')
            ->groupBy('soli_id_evaluacion');

        return DB::table('grupo as g')
            ->join('sede as s', 's.sede_id_sede', '=', 'g.sede_id_sede')
            ->leftJoin('evaluacion as e', 'e.grup_id_grupo', '=', 'g.grup_id_grupo')
            ->leftJoinSub($ocupaciones, 'o', 'o.soli_id_evaluacion', '=', 'e.eval_id_evaluacion')
            ->orderBy('s.sede_nombre')
            ->orderBy('g.grup_fecha_inicio')
            ->orderBy('g.grup_hora_inicio')
            ->select([
                'g.grup_id_grupo',
                'g.sede_id_sede',
                'g.grup_fecha_inicio',
                'g.grup_hora_inicio',
                'g.grup_fecha_fin',
                'g.grup_hora_fin',
                's.sede_nombre',
                's.sede_direccion',
                's.sede_cupo',
                'e.eval_id_evaluacion',
                DB::raw('COALESCE(o.ocupados, 0) AS ocupados'),
            ])
            ->get()
            ->map(function (object $fila): array {
                $cupo = (int) $fila->sede_cupo;
                $ocupados = (int) $fila->ocupados;
                $disponibles = max($cupo - $ocupados, 0);

                return [
                    'id' => (int) $fila->grup_id_grupo,
                    'evaluacion_id' => $fila->eval_id_evaluacion === null
                        ? null
                        : (int) $fila->eval_id_evaluacion,
                    'sede_id' => (int) $fila->sede_id_sede,
                    'sede_nombre' => $fila->sede_nombre,
                    'sede_direccion' => $fila->sede_direccion,
                    'cupo' => $cupo,
                    'fecha_inicio' => substr((string) $fila->grup_fecha_inicio, 0, 10),
                    'hora_inicio' => substr((string) $fila->grup_hora_inicio, 0, 5),
                    'fecha_fin' => substr((string) $fila->grup_fecha_fin, 0, 10),
                    'hora_fin' => substr((string) $fila->grup_hora_fin, 0, 5),
                    'ocupados' => $ocupados,
                    'disponibles' => $disponibles,
                    'con_cupo' => $disponibles > 0,
                    'estado_clave' => $disponibles > 0 ? 'con-cupo' : 'sin-cupo',
                    'estado' => $disponibles > 0 ? 'Con cupo' : 'Sin cupo',
                ];
            });
    }

    private function datosGrupo(int $idSede, array $horario): array
    {
        return [
            'sede_id_sede' => $idSede,
            'grup_fecha_inicio' => $horario['fecha_inicio'],
            'grup_hora_inicio' => $this->horaCompleta($horario['hora_inicio']),
            'grup_fecha_fin' => $horario['fecha_fin'],
            'grup_hora_fin' => $this->horaCompleta($horario['hora_fin']),
        ];
    }

    /**
     * El formulario manda H:i. PostgreSQL completa los segundos al guardar en
     * TIME, pero SQLite —el motor de las pruebas— almacena la cadena tal cual;
     * normalizar aquí deja el mismo dato en los dos.
     */
    private function horaCompleta(string $hora): string
    {
        return substr($hora, 0, 5).':00';
    }

    private function refrescarEstadoSede(Sede $sede): void
    {
        $sede->sede_estado = $this->sedeConCupo(
            (int) $sede->sede_id_sede,
            (int) $sede->sede_cupo
        );
        $sede->save();
    }

    /**
     * GRUPO guarda fecha y hora por separado; para comparar intervalos hay
     * que volver a juntarlas.
     */
    private function momento(mixed $fecha, mixed $hora): Carbon
    {
        $dia = $fecha instanceof \DateTimeInterface
            ? $fecha->format('Y-m-d')
            : substr((string) $fecha, 0, 10);

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $dia.' '.substr((string) $hora, 0, 5),
            config('app.timezone')
        );
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
