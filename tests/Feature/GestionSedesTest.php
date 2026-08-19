<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GestionSedesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaTemporal();
        $this->cargarUsuarios();
    }

    public function test_crud_requiere_administrador_y_guarda_nombre_independiente_de_direccion(): void
    {
        $this->get(route('admin.sedes.index'))->assertRedirect(route('login'));

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('admin.sedes.index'))
            ->assertForbidden();

        $respuesta = $this->actingAs(Usuario::findOrFail(2))->post(route('admin.sedes.store'), [
            'nombre' => 'Sede Universidad',
            'direccion' => 'Circuito Exterior 1, Coyoacán',
            'cupo' => 2,
            'horarios' => [
                [
                    'fecha_inicio' => '2026-10-15',
                    'hora_inicio' => '09:00',
                    'fecha_fin' => '2026-10-15',
                    'hora_fin' => '13:00',
                ],
            ],
        ]);

        $idSede = (int) DB::table('sede')->value('sede_id_sede');
        $respuesta
            ->assertRedirect(route('admin.sedes.index'))
            ->assertSessionHas('success', 'La sede se creó correctamente.');
        $this->assertDatabaseHas('sede', [
            'sede_id_sede' => $idSede,
            'sede_nombre' => 'Sede Universidad',
            'sede_direccion' => 'Circuito Exterior 1, Coyoacán',
            'sede_cupo' => 2,
            'sede_estado' => 1,
        ]);
        $this->assertDatabaseHas('grupo', [
            'sede_id_sede' => $idSede,
            'grup_fecha_inicio' => '2026-10-15',
        ]);
        $this->assertDatabaseHas('evaluacion', [
            'grup_id_grupo' => (int) DB::table('grupo')->value('grup_id_grupo'),
            'eval_resultado' => null,
        ]);
    }

    public function test_edicion_programa_una_sede_pendiente_y_valida_el_intervalo(): void
    {
        $idSede = DB::table('sede')->insertGetId([
            'sede_nombre' => 'Sede pendiente',
            'sede_direccion' => 'Dirección pendiente',
            'sede_cupo' => 10,
            'sede_estado' => false,
        ], 'sede_id_sede');

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.index', ['estado' => 'pendiente']))
            ->assertOk()
            ->assertSee('Sede pendiente')
            ->assertSee('Pendiente');

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.sedes.update', $idSede), [
                'nombre' => 'Sede corregida',
                'direccion' => 'Dirección corregida',
                'cupo' => 12,
                'horarios' => [
                    [
                        'fecha_inicio' => '2026-10-15',
                        'hora_inicio' => '13:00',
                        'fecha_fin' => '2026-10-15',
                        'hora_fin' => '09:00',
                    ],
                ],
            ])
            ->assertSessionHasErrors('horarios.0.fecha_fin');

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.sedes.update', $idSede), [
                'nombre' => 'Sede corregida',
                'direccion' => 'Dirección corregida',
                'cupo' => 12,
                'horarios' => [
                    [
                        'fecha_inicio' => '2026-10-15',
                        'hora_inicio' => '09:00',
                        'fecha_fin' => '2026-10-15',
                        'hora_fin' => '13:00',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.sedes.index'))
            ->assertSessionHas('success', 'La sede se actualizó correctamente.');

        $this->assertDatabaseHas('sede', [
            'sede_id_sede' => $idSede,
            'sede_nombre' => 'Sede corregida',
            'sede_estado' => 1,
        ]);
        $this->assertSame(1, DB::table('grupo')->where('sede_id_sede', $idSede)->count());
    }

    public function test_formularios_de_sede_muestran_horario_fechas_y_aforo_en_el_orden_solicitado(): void
    {
        $orden = [
            'Hora de inicio *',
            'Fecha de inicio *',
            'Hora de fin *',
            'Fecha de fin *',
            'Aforo máximo por aplicación *',
        ];

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.create'))
            ->assertOk()
            ->assertSeeInOrder($orden);

        [$idSede] = $this->crearSedeProgramada(2);

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.edit', $idSede))
            ->assertOk()
            ->assertSeeInOrder($orden);
    }

    public function test_no_elimina_ni_reduce_una_sede_con_participantes_asignados(): void
    {
        [$idSede, $idEvaluacion] = $this->crearSedeProgramada(1);
        DB::table('solicitud')->where('soli_id_solicitud', 100)->update([
            'soli_id_evaluacion' => $idEvaluacion,
        ]);

        $datos = [
            'nombre' => 'Sede ocupada',
            'direccion' => 'Dirección ocupada',
            'cupo' => 1,
            'horarios' => [
                [
                    'fecha_inicio' => '2026-10-15',
                    'hora_inicio' => '09:00',
                    'fecha_fin' => '2026-10-15',
                    'hora_fin' => '13:00',
                ],
            ],
        ];

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.sedes.update', $idSede), array_merge($datos, ['cupo' => 0]))
            ->assertSessionHasErrors('cupo');

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.sedes.destroy', $idSede))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('sede', ['sede_id_sede' => $idSede]);

        DB::table('solicitud')->where('soli_id_solicitud', 100)->update([
            'soli_id_evaluacion' => null,
        ]);

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.sedes.destroy', $idSede))
            ->assertRedirect(route('admin.sedes.index'));

        $this->assertDatabaseMissing('sede', ['sede_id_sede' => $idSede]);
        $this->assertDatabaseMissing('evaluacion', ['eval_id_evaluacion' => $idEvaluacion]);
        $this->assertDatabaseMissing('grupo', ['sede_id_sede' => $idSede]);
    }

    public function test_participante_ve_cupos_y_persiste_una_eleccion_idempotente(): void
    {
        [$idSede, $idEvaluacion] = $this->crearSedeProgramada(1);

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.index'))
            ->assertOk()
            ->assertSee('Sede de prueba')
            ->assertSeeText('1 de 1 disponibles');

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.sede.seleccionar'), ['evaluacion_id' => $idEvaluacion])
            ->assertRedirect(route('persona.sede.index'));

        $this->assertDatabaseHas('solicitud', [
            'soli_id_solicitud' => 100,
            'soli_id_evaluacion' => $idEvaluacion,
        ]);
        $this->assertDatabaseHas('sede', [
            'sede_id_sede' => $idSede,
            'sede_estado' => 0,
        ]);

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.sede.seleccionar'), ['evaluacion_id' => $idEvaluacion])
            ->assertRedirect(route('persona.sede.index'));

        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad'))
            ->assertOk()
            ->assertJsonFragment([
                'evaluacion_id' => $idEvaluacion,
                'disponibles' => 0,
                'con_cupo' => false,
            ]);

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.index'))
            ->assertOk()
            ->assertSee('¡Sede confirmada!')
            ->assertSee('Sede de prueba');
    }

    public function test_una_sede_ofrece_varios_horarios_y_el_participante_elige_uno(): void
    {
        [$idSede, $idPrimero] = $this->crearSedeProgramada(1);
        $idSegundo = $this->agregarHorario($idSede, '2026-10-16', '16:00:00', '2026-10-16', '20:00:00');

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.index'))
            ->assertOk()
            ->assertSee('Sede de prueba')
            ->assertSeeText('Horarios disponibles · 2')
            ->assertSeeText('09:00–13:00 h')
            ->assertSeeText('16:00–20:00 h');

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.sede.seleccionar'), ['evaluacion_id' => $idSegundo])
            ->assertRedirect(route('persona.sede.index'));

        $this->assertDatabaseHas('solicitud', [
            'soli_id_solicitud' => 100,
            'soli_id_evaluacion' => $idSegundo,
        ]);
        $this->assertNotSame($idPrimero, $idSegundo);
    }

    public function test_llenar_un_horario_no_bloquea_los_demas_de_la_sede(): void
    {
        [$idSede, $idPrimero] = $this->crearSedeProgramada(1);
        $idSegundo = $this->agregarHorario($idSede, '2026-10-16', '16:00:00', '2026-10-16', '20:00:00');

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.sede.seleccionar'), ['evaluacion_id' => $idPrimero])
            ->assertRedirect(route('persona.sede.index'));

        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad'))
            ->assertOk()
            ->assertJsonFragment(['evaluacion_id' => $idPrimero, 'disponibles' => 0, 'con_cupo' => false])
            ->assertJsonFragment(['evaluacion_id' => $idSegundo, 'disponibles' => 1, 'con_cupo' => true]);

        $this->assertDatabaseHas('sede', [
            'sede_id_sede' => $idSede,
            'sede_estado' => 1,
        ]);
    }

    public function test_la_edicion_sincroniza_altas_y_bajas_de_horarios(): void
    {
        [$idSede, $idEvaluacion] = $this->crearSedeProgramada(5);
        $idGrupo = (int) DB::table('evaluacion')
            ->where('eval_id_evaluacion', $idEvaluacion)
            ->value('grup_id_grupo');

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.sedes.update', $idSede), [
                'nombre' => 'Sede de prueba',
                'direccion' => 'Dirección de prueba',
                'cupo' => 5,
                'horarios' => [
                    [
                        'grupo_id' => $idGrupo,
                        'fecha_inicio' => '2026-10-15',
                        'hora_inicio' => '10:00',
                        'fecha_fin' => '2026-10-15',
                        'hora_fin' => '14:00',
                    ],
                    [
                        'fecha_inicio' => '2026-10-16',
                        'hora_inicio' => '16:00',
                        'fecha_fin' => '2026-10-16',
                        'hora_fin' => '20:00',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.sedes.index'));

        $this->assertSame(2, DB::table('grupo')->where('sede_id_sede', $idSede)->count());
        $this->assertSame(2, DB::table('evaluacion')->count());
        $this->assertDatabaseHas('grupo', [
            'grup_id_grupo' => $idGrupo,
            'grup_hora_inicio' => '10:00:00',
        ]);

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.sedes.update', $idSede), [
                'nombre' => 'Sede de prueba',
                'direccion' => 'Dirección de prueba',
                'cupo' => 5,
                'horarios' => [
                    [
                        'grupo_id' => $idGrupo,
                        'fecha_inicio' => '2026-10-15',
                        'hora_inicio' => '10:00',
                        'fecha_fin' => '2026-10-15',
                        'hora_fin' => '14:00',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.sedes.index'));

        $this->assertSame(1, DB::table('grupo')->where('sede_id_sede', $idSede)->count());
    }

    public function test_no_admite_dos_aplicaciones_empalmadas_en_la_misma_sede(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.sedes.store'), [
                'nombre' => 'Sede empalmada',
                'direccion' => 'Dirección de prueba',
                'cupo' => 5,
                'horarios' => [
                    [
                        'fecha_inicio' => '2026-10-15',
                        'hora_inicio' => '09:00',
                        'fecha_fin' => '2026-10-15',
                        'hora_fin' => '13:00',
                    ],
                    [
                        'fecha_inicio' => '2026-10-15',
                        'hora_inicio' => '12:00',
                        'fecha_fin' => '2026-10-15',
                        'hora_fin' => '16:00',
                    ],
                ],
            ])
            ->assertSessionHasErrors('horarios.1.fecha_inicio');

        $this->assertSame(0, DB::table('sede')->where('sede_nombre', 'Sede empalmada')->count());
    }

    private function crearEsquemaTemporal(): void
    {
        foreach ([
            'estado_documento',
            'c_estado_documento',
            'documento',
            'tipo_documento',
            'estado_solicitud',
            'c_estado_solicitud',
            'estado_pago',
            'c_estado_pago',
            'pago',
            'solicitud',
            'evaluacion',
            'sede',
            'persona',
            'usuario',
            'rol',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('rol', function (Blueprint $table): void {
            $table->integer('rol_id_rol')->primary();
            $table->string('rol_tipo_rol', 15);
        });
        Schema::create('usuario', function (Blueprint $table): void {
            $table->integer('usua_id_usuario')->primary();
            $table->integer('usua_id_rol');
            $table->string('usua_clave_acceso')->nullable();
        });
        Schema::create('persona', function (Blueprint $table): void {
            $table->integer('pers_id_persona')->primary();
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
            $table->string('pers_nombre', 45);
            $table->string('pers_apellido_paterno', 45)->nullable();
            $table->string('pers_apellido_materno', 45)->nullable();
            $table->date('pers_fecha_registro')->nullable();
        });
        Schema::create('sede', function (Blueprint $table): void {
            $table->increments('sede_id_sede');
            $table->string('sede_nombre', 150);
            $table->text('sede_direccion');
            $table->integer('sede_cupo');
            $table->boolean('sede_estado');
        });
        Schema::create('grupo', function (Blueprint $table): void {
            $table->increments('grup_id_grupo');
            $table->integer('sede_id_sede');
            $table->date('grup_fecha_inicio');
            $table->time('grup_hora_inicio');
            $table->date('grup_fecha_fin');
            $table->time('grup_hora_fin');
        });
        Schema::create('evaluacion', function (Blueprint $table): void {
            $table->increments('eval_id_evaluacion');
            $table->integer('grup_id_grupo')->unique();
            $table->integer('eval_resultado')->nullable();
        });
        Schema::create('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_solicitud')->primary();
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_pago')->nullable();
            $table->integer('soli_id_evaluacion')->nullable();
        });
        Schema::create('pago', function (Blueprint $table): void {
            $table->integer('pago_id_pago')->primary();
            $table->string('pago_comprobante_path')->nullable();
            $table->decimal('pago_monto_pagado', 10, 2)->nullable();
        });
        Schema::create('c_estado_pago', function (Blueprint $table): void {
            $table->integer('espa_id_c_estado_pago')->primary();
            $table->string('esta_estado_pago', 15);
        });
        Schema::create('estado_pago', function (Blueprint $table): void {
            $table->increments('espa_id_estado_pago');
            $table->integer('espa_id_pago');
            $table->integer('espa_id_c_estado_pago');
            $table->text('espa_comentario')->nullable();
        });
        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_c_estado_solicitud')->primary();
            $table->string('esso_estado_solicitud', 40);
        });
        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_estado_solicitud');
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
        });
        Schema::create('tipo_documento', function (Blueprint $table): void {
            $table->integer('tido_id_tipo_documento')->primary();
            $table->string('tido_tipo_documento');
        });
        Schema::create('documento', function (Blueprint $table): void {
            $table->increments('docu_id_documento');
            $table->integer('tido_id_tipo_documento');
            $table->integer('soli_id_solicitud');
        });
        Schema::create('c_estado_documento', function (Blueprint $table): void {
            $table->integer('esdo_id_c_estado_documento')->primary();
            $table->string('esdo_estado_documento');
        });
        Schema::create('estado_documento', function (Blueprint $table): void {
            $table->increments('esdo_id_estado_documento');
            $table->integer('esdo_id_c_estado_documento');
            $table->integer('esdo_id_documento');
        });
    }

    private function cargarUsuarios(): void
    {
        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Administrador'],
        ]);
        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => 'persona'],
            ['usua_id_usuario' => 2, 'usua_id_rol' => 2, 'usua_clave_acceso' => 'admin'],
        ]);
        DB::table('persona')->insert([
            [
                'pers_id_persona' => 1,
                'pers_id_usuario' => 1,
                'pers_curp' => 'PERS900101MDFPRB01',
                'pers_nombre' => 'Persona',
                'pers_apellido_paterno' => 'Prueba',
                'pers_apellido_materno' => null,
                'pers_fecha_registro' => '2026-08-01',
            ],
            [
                'pers_id_persona' => 2,
                'pers_id_usuario' => 2,
                'pers_curp' => 'ADMN900101MDFPRB02',
                'pers_nombre' => 'Administradora',
                'pers_apellido_paterno' => 'Prueba',
                'pers_apellido_materno' => null,
                'pers_fecha_registro' => '2026-08-01',
            ],
        ]);
        DB::table('pago')->insert([
            'pago_id_pago' => 10,
            'pago_comprobante_path' => 'comprobantes/pago.pdf',
            'pago_monto_pagado' => 7000,
        ]);
        DB::table('c_estado_pago')->insert([
            'espa_id_c_estado_pago' => 1,
            'esta_estado_pago' => 'Completado',
        ]);
        DB::table('estado_pago')->insert([
            'espa_id_pago' => 10,
            'espa_id_c_estado_pago' => 1,
        ]);
        DB::table('solicitud')->insert([
            'soli_id_solicitud' => 100,
            'soli_id_persona' => 1,
            'soli_id_pago' => 10,
            'soli_id_evaluacion' => null,
        ]);
    }

    private function crearSedeProgramada(int $cupo): array
    {
        $idSede = DB::table('sede')->insertGetId([
            'sede_nombre' => 'Sede de prueba',
            'sede_direccion' => 'Dirección de prueba',
            'sede_cupo' => $cupo,
            'sede_estado' => true,
        ], 'sede_id_sede');
        $idEvaluacion = $this->agregarHorario($idSede, '2026-10-15', '09:00:00', '2026-10-15', '13:00:00');

        return [$idSede, $idEvaluacion];
    }

    /**
     * Una sede aplica el examen una o más veces; cada aplicación es un grupo
     * con su propia evaluación.
     */
    private function agregarHorario(
        int $idSede,
        string $fechaInicio,
        string $horaInicio,
        string $fechaFin,
        string $horaFin
    ): int
    {
        $idGrupo = DB::table('grupo')->insertGetId([
            'sede_id_sede' => $idSede,
            'grup_fecha_inicio' => $fechaInicio,
            'grup_hora_inicio' => $horaInicio,
            'grup_fecha_fin' => $fechaFin,
            'grup_hora_fin' => $horaFin,
        ], 'grup_id_grupo');

        return (int) DB::table('evaluacion')->insertGetId([
            'grup_id_grupo' => $idGrupo,
            'eval_resultado' => null,
        ], 'eval_id_evaluacion');
    }
}
