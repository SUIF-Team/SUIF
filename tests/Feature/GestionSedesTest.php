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
            'fecha_inicio' => '2026-10-15',
            'hora_inicio' => '09:00',
            'fecha_fin' => '2026-10-15',
            'hora_fin' => '13:00',
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
        $this->assertDatabaseHas('evaluacion', [
            'eval_id_sede' => $idSede,
            'eval_fecha_inicio' => '2026-10-15',
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
                'fecha_inicio' => '2026-10-15',
                'hora_inicio' => '13:00',
                'fecha_fin' => '2026-10-15',
                'hora_fin' => '09:00',
            ])
            ->assertSessionHasErrors('fecha_fin');

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.sedes.update', $idSede), [
                'nombre' => 'Sede corregida',
                'direccion' => 'Dirección corregida',
                'cupo' => 12,
                'fecha_inicio' => '2026-10-15',
                'hora_inicio' => '09:00',
                'fecha_fin' => '2026-10-15',
                'hora_fin' => '13:00',
            ])
            ->assertRedirect(route('admin.sedes.index'))
            ->assertSessionHas('success', 'La sede se actualizó correctamente.');

        $this->assertDatabaseHas('sede', [
            'sede_id_sede' => $idSede,
            'sede_nombre' => 'Sede corregida',
            'sede_estado' => 1,
        ]);
        $this->assertSame(1, DB::table('evaluacion')->where('eval_id_sede', $idSede)->count());
    }

    public function test_formularios_de_sede_muestran_horario_fechas_y_aforo_en_el_orden_solicitado(): void
    {
        $orden = [
            'Hora de inicio *',
            'Fecha de inicio *',
            'Hora de fin *',
            'Fecha de fin *',
            'Aforo máximo *',
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
            'fecha_inicio' => '2026-10-15',
            'hora_inicio' => '09:00',
            'fecha_fin' => '2026-10-15',
            'hora_fin' => '13:00',
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
        Schema::create('evaluacion', function (Blueprint $table): void {
            $table->increments('eval_id_evaluacion');
            $table->integer('eval_id_sede')->unique();
            $table->date('eval_fecha_inicio');
            $table->time('eval_hora_inicio');
            $table->date('eval_fecha_fin');
            $table->time('eval_hora_fin');
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
            $table->string('esso_estatus_solicitud', 40);
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
        $idEvaluacion = DB::table('evaluacion')->insertGetId([
            'eval_id_sede' => $idSede,
            'eval_fecha_inicio' => '2026-10-15',
            'eval_hora_inicio' => '09:00:00',
            'eval_fecha_fin' => '2026-10-15',
            'eval_hora_fin' => '13:00:00',
            'eval_resultado' => null,
        ], 'eval_id_evaluacion');

        return [$idSede, $idEvaluacion];
    }
}
