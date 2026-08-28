<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
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
            'sede_estado' => 0,
        ]);

        /* La sede nace sin programación: eso se registra desde el módulo de grupos. */
        $this->assertSame(0, DB::table('grupo')->count());
    }

    public function test_la_sede_por_programar_no_se_muestra_al_participante_hasta_que_tiene_un_grupo(): void
    {
        $this->actingAs(Usuario::findOrFail(2))->post(route('admin.sedes.store'), [
            'nombre' => 'Sede sin programar',
            'direccion' => 'Dirección sin programar',
            'cupo' => 3,
        ]);

        $idSede = (int) DB::table('sede')->value('sede_id_sede');

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.index'))
            ->assertOk()
            ->assertSeeInOrder(['Sede sin programar', 'Por programar']);

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.index'))
            ->assertOk()
            ->assertDontSee('Sede sin programar');

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.grupos.store'), [
                'sede_id' => $idSede,
                'fecha_inicio' => $this->fechaFutura(30),
                'hora_inicio' => '09:00',
                'fecha_fin' => $this->fechaFutura(30),
                'hora_fin' => '13:00',
            ])
            ->assertRedirect(route('admin.grupos.index'))
            ->assertSessionHas('success', 'El grupo se creó correctamente.');

        $this->assertDatabaseHas('sede', ['sede_id_sede' => $idSede, 'sede_estado' => 1]);

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.index'))
            ->assertOk()
            ->assertSee('Sede sin programar');

        /* El marcado lo genera Vue, así que el horario se comprueba sobre el
           catálogo que consume, no sobre el HTML. */
        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad'))
            ->assertOk()
            ->assertJsonPath('sedes.0.horarios.0.hora_inicio', '09:00')
            ->assertJsonPath('sedes.0.horarios.0.hora_fin', '13:00');
    }

    public function test_el_listado_de_sedes_cuenta_los_grupos_registrados(): void
    {
        [$idSede] = $this->crearSedeProgramada(4);
        $this->agregarHorario($idSede, '2026-10-16', '16:00:00', '2026-10-16', '20:00:00');

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.index'))
            ->assertOk()
            ->assertSee('Grupos')
            ->assertSee('<td>2</td>', false);
    }

    public function test_la_edicion_de_la_sede_no_toca_su_programacion(): void
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
            ->assertSeeInOrder(['Sede pendiente', 'Por programar']);

        $idEvaluacion = $this->agregarHorario($idSede, '2026-10-15', '09:00:00', '2026-10-15', '13:00:00');

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.sedes.update', $idSede), [
                'nombre' => 'Sede corregida',
                'direccion' => 'Dirección corregida',
                'cupo' => 12,
            ])
            ->assertRedirect(route('admin.sedes.index'))
            ->assertSessionHas('success', 'La sede se actualizó correctamente.');

        $this->assertDatabaseHas('sede', [
            'sede_id_sede' => $idSede,
            'sede_nombre' => 'Sede corregida',
            'sede_estado' => 1,
        ]);

        /* Editar el lugar no puede borrar ni alterar sus aplicaciones. */
        $this->assertSame(1, DB::table('grupo')->where('sede_id_sede', $idSede)->count());
        $this->assertDatabaseHas('evaluacion', ['eval_id_evaluacion' => $idEvaluacion]);
    }

    public function test_el_formulario_de_sede_captura_solo_el_lugar(): void
    {
        $orden = [
            'Nombre de sede *',
            'Dirección completa *',
            'Aforo máximo por aplicación *',
        ];

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.create'))
            ->assertOk()
            ->assertSeeInOrder($orden)
            ->assertDontSee('Hora de inicio *');

        [$idSede] = $this->crearSedeProgramada(2);

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.edit', $idSede))
            ->assertOk()
            ->assertSeeInOrder($orden)
            ->assertDontSee('Hora de inicio *');
    }

    public function test_el_formulario_de_grupo_muestra_la_sede_y_los_datos_de_aplicacion(): void
    {
        $this->crearSedeProgramada(7);

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.grupos.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Sede *',
                'Hora de inicio *',
                'Fecha de inicio *',
                'Hora de fin *',
                'Fecha de fin *',
            ])
            /* El selector de sede muestra su aforo y su estatus. */
            ->assertSeeText('Sede de prueba · aforo 7 · 1 grupo · Con cupo');
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
            ->assertSee('Sede de prueba');

        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad'))
            ->assertOk()
            ->assertJsonPath('sedes.0.horarios.0.disponibles', 1)
            ->assertJsonPath('sedes.0.horarios.0.con_cupo', true);

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
        $idSegundo = $this->agregarHorario(
            $idSede,
            $this->fechaFutura(31),
            '16:00:00',
            $this->fechaFutura(31),
            '20:00:00'
        );

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.index'))
            ->assertOk()
            ->assertSee('Sede de prueba');

        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad'))
            ->assertOk()
            ->assertJsonCount(2, 'sedes.0.horarios')
            ->assertJsonPath('sedes.0.horarios.0.hora_inicio', '09:00')
            ->assertJsonPath('sedes.0.horarios.1.hora_inicio', '16:00');

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
        $idSegundo = $this->agregarHorario(
            $idSede,
            $this->fechaFutura(31),
            '16:00:00',
            $this->fechaFutura(31),
            '20:00:00'
        );

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

    public function test_alta_edicion_y_baja_de_grupos_desde_su_modulo(): void
    {
        $idSede = DB::table('sede')->insertGetId([
            'sede_nombre' => 'Sede con grupos',
            'sede_direccion' => 'Dirección con grupos',
            'sede_cupo' => 5,
            'sede_estado' => false,
        ], 'sede_id_sede');

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('admin.grupos.index'))
            ->assertForbidden();

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.grupos.store'), [
                'sede_id' => $idSede,
                'fecha_inicio' => '2026-10-15',
                'hora_inicio' => '09:00',
                'fecha_fin' => '2026-10-15',
                'hora_fin' => '13:00',
            ])
            ->assertRedirect(route('admin.grupos.index'));

        $idGrupo = (int) DB::table('grupo')->where('sede_id_sede', $idSede)->value('grup_id_grupo');

        /* Cada grupo nace con su evaluación: es contra ella que se cuenta el cupo. */
        $this->assertDatabaseHas('evaluacion', ['grup_id_grupo' => $idGrupo, 'eval_resultado' => null]);
        $this->assertDatabaseHas('sede', ['sede_id_sede' => $idSede, 'sede_estado' => 1]);

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.grupos.index'))
            ->assertOk()
            ->assertSee('15/10/2026')
            ->assertSeeText('0 / 5')
            ->assertSee('admin-sedes-estado--con-cupo', false);

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.grupos.update', $idGrupo), [
                'sede_id' => $idSede,
                'fecha_inicio' => '2026-10-16',
                'hora_inicio' => '10:00',
                'fecha_fin' => '2026-10-16',
                'hora_fin' => '14:00',
            ])
            ->assertRedirect(route('admin.grupos.index'))
            ->assertSessionHas('success', 'El grupo se actualizó correctamente.');

        $this->assertDatabaseHas('grupo', [
            'grup_id_grupo' => $idGrupo,
            'grup_fecha_inicio' => '2026-10-16',
            'grup_hora_inicio' => '10:00:00',
        ]);
        $this->assertSame(1, DB::table('grupo')->where('sede_id_sede', $idSede)->count());

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.grupos.destroy', $idGrupo))
            ->assertRedirect(route('admin.grupos.index'))
            ->assertSessionHas('success', 'El grupo se eliminó correctamente.');

        $this->assertDatabaseMissing('grupo', ['grup_id_grupo' => $idGrupo]);
        $this->assertDatabaseMissing('evaluacion', ['grup_id_grupo' => $idGrupo]);

        /* Sin programación la sede vuelve a quedar «Por programar». */
        $this->assertDatabaseHas('sede', ['sede_id_sede' => $idSede, 'sede_estado' => 0]);
    }

    public function test_no_admite_dos_grupos_empalmados_en_la_misma_sede(): void
    {
        [$idSede] = $this->crearSedeProgramada(5);

        /* El mismo día que ocupa crearSedeProgramada(), de 09:00 a 13:00. */
        $dia = $this->fechaFutura(30);

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.grupos.store'), [
                'sede_id' => $idSede,
                'fecha_inicio' => $dia,
                'hora_inicio' => '12:00',
                'fecha_fin' => $dia,
                'hora_fin' => '16:00',
            ])
            ->assertSessionHasErrors('fecha_inicio');

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.grupos.store'), [
                'sede_id' => $idSede,
                'fecha_inicio' => $dia,
                'hora_inicio' => '16:00',
                'fecha_fin' => $dia,
                'hora_fin' => '13:00',
            ])
            ->assertSessionHasErrors('fecha_fin');

        $this->assertSame(1, DB::table('grupo')->where('sede_id_sede', $idSede)->count());

        /* Fuera del intervalo ocupado sí entra. */
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.grupos.store'), [
                'sede_id' => $idSede,
                'fecha_inicio' => $dia,
                'hora_inicio' => '13:00',
                'fecha_fin' => $dia,
                'hora_fin' => '17:00',
            ])
            ->assertRedirect(route('admin.grupos.index'));

        $this->assertSame(2, DB::table('grupo')->where('sede_id_sede', $idSede)->count());
    }

    public function test_no_elimina_ni_mueve_de_sede_un_grupo_con_participantes(): void
    {
        [$idSede, $idEvaluacion] = $this->crearSedeProgramada(3);
        $idGrupo = (int) DB::table('evaluacion')
            ->where('eval_id_evaluacion', $idEvaluacion)
            ->value('grup_id_grupo');
        $idOtraSede = DB::table('sede')->insertGetId([
            'sede_nombre' => 'Otra sede',
            'sede_direccion' => 'Otra dirección',
            'sede_cupo' => 3,
            'sede_estado' => false,
        ], 'sede_id_sede');

        DB::table('solicitud')->where('soli_id_solicitud', 100)->update([
            'soli_id_evaluacion' => $idEvaluacion,
        ]);

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.grupos.update', $idGrupo), [
                'sede_id' => $idOtraSede,
                'fecha_inicio' => '2026-10-15',
                'hora_inicio' => '09:00',
                'fecha_fin' => '2026-10-15',
                'hora_fin' => '13:00',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('grupo', ['grup_id_grupo' => $idGrupo, 'sede_id_sede' => $idSede]);

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.grupos.destroy', $idGrupo))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('grupo', ['grup_id_grupo' => $idGrupo]);

        /* Liberado el lugar, la baja procede. */
        DB::table('solicitud')->where('soli_id_solicitud', 100)->update([
            'soli_id_evaluacion' => null,
        ]);

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.grupos.destroy', $idGrupo))
            ->assertRedirect(route('admin.grupos.index'));

        $this->assertDatabaseMissing('grupo', ['grup_id_grupo' => $idGrupo]);
    }

    public function test_el_participante_no_ve_ni_puede_elegir_una_aplicacion_que_ya_termino(): void
    {
        [$idSede, $idVigente] = $this->crearSedeProgramada(2);
        $idVencida = $this->agregarHorario(
            $idSede,
            $this->fechaPasada(2),
            '09:00:00',
            $this->fechaPasada(2),
            '13:00:00'
        );

        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad'))
            ->assertOk()
            ->assertJsonCount(1, 'sedes.0.horarios')
            ->assertJsonPath('sedes.0.horarios.0.evaluacion_id', $idVigente);

        /* El administrador conserva las dos: su bandeja es el historial. */
        $this->assertSame(2, DB::table('grupo')->where('sede_id_sede', $idSede)->count());

        /* Quien dejó la pestaña abierta todavía puede mandar el id viejo. */
        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.sede.seleccionar'), ['evaluacion_id' => $idVencida])
            ->assertRedirect(route('persona.sede.index'))
            ->assertSessionHasErrors('sede');

        $this->assertDatabaseHas('solicitud', [
            'soli_id_solicitud' => 100,
            'soli_id_evaluacion' => null,
        ]);
    }

    public function test_la_sede_sin_aplicaciones_vigentes_sale_del_catalogo_pero_no_de_la_bandeja(): void
    {
        $idSede = DB::table('sede')->insertGetId([
            'sede_nombre' => 'Sede vencida',
            'sede_direccion' => 'Dirección vencida',
            'sede_cupo' => 5,
            'sede_estado' => true,
        ], 'sede_id_sede');

        $this->agregarHorario(
            $idSede,
            $this->fechaPasada(5),
            '09:00:00',
            $this->fechaPasada(5),
            '13:00:00'
        );

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.index'))
            ->assertOk()
            ->assertDontSee('Sede vencida');

        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad'))
            ->assertOk()
            ->assertJsonCount(0, 'sedes');

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.sedes.index'))
            ->assertOk()
            ->assertSee('Sede vencida');
    }

    public function test_el_sondeo_respeta_el_filtro_de_busqueda_vigente(): void
    {
        $this->crearSedeProgramada(1);

        $idOtra = DB::table('sede')->insertGetId([
            'sede_nombre' => 'Sede Norte',
            'sede_direccion' => 'Avenida Norte',
            'sede_cupo' => 4,
            'sede_estado' => true,
        ], 'sede_id_sede');

        $this->agregarHorario(
            $idOtra,
            $this->fechaFutura(40),
            '09:00:00',
            $this->fechaFutura(40),
            '13:00:00'
        );

        $this->actingAs(Usuario::findOrFail(1))
            ->getJson(route('persona.sede.disponibilidad', ['buscar' => 'Norte']))
            ->assertOk()
            ->assertJsonCount(1, 'sedes')
            ->assertJsonPath('sedes.0.nombre', 'Sede Norte');
    }

    public function test_el_comprobante_de_sede_se_entrega_en_pdf_solo_con_la_sede_confirmada(): void
    {
        [, $idEvaluacion] = $this->crearSedeProgramada(1);

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.comprobante'))
            ->assertRedirect(route('persona.sede.index'))
            ->assertSessionHasErrors('sede');

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.sede.seleccionar'), ['evaluacion_id' => $idEvaluacion])
            ->assertRedirect(route('persona.sede.index'));

        $respuesta = $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.sede.comprobante'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
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
            'privilegio_rol',
            'privilegio',
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
            $table->boolean('usua_activo')->default(true);
        });
        /* El permiso de sedes se resuelve contra PRIVILEGIO_ROL y ya no contra
           el nombre del rol: sin estas dos tablas ninguna ruta admin responde. */
        Schema::create('privilegio', function (Blueprint $table): void {
            $table->integer('priv_id_privilegio')->primary();
            $table->string('priv_privilegio', 35);
        });
        Schema::create('privilegio_rol', function (Blueprint $table): void {
            $table->increments('ropr_id_privilegio_rol');
            $table->integer('ropr_id_privilegio');
            $table->integer('ropr_id_rol');
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
            $table->boolean('pago_uso_cfdi')->nullable();
            $table->integer('pago_id_dato_fiscal')->nullable();
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
            $table->string('esso_motivo_rechazo', 255)->nullable();
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
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Superusuario'],
        ]);
        /* Sedes y grupos son del Superusuario: es quien tiene "Gestionar
           Sedes". El usuario 1 no tiene ninguno, y por eso recibe 403. */
        DB::table('privilegio')->insert([
            ['priv_id_privilegio' => 1, 'priv_privilegio' => 'Gestionar Sedes'],
        ]);
        DB::table('privilegio_rol')->insert([
            ['ropr_id_privilegio' => 1, 'ropr_id_rol' => 2],
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
        $idEvaluacion = $this->agregarHorario(
            $idSede,
            $this->fechaFutura(30),
            '09:00:00',
            $this->fechaFutura(30),
            '13:00:00'
        );

        return [$idSede, $idEvaluacion];
    }

    /**
     * Las fechas de las aplicaciones que ve el participante son relativas a
     * hoy: desde que el catálogo oculta las que ya terminaron, una fecha fija
     * haría que estas pruebas empezaran a fallar solas al llegar ese día.
     */
    private function fechaFutura(int $dias): string
    {
        return Carbon::now()->addDays($dias)->toDateString();
    }

    private function fechaPasada(int $dias): string
    {
        return Carbon::now()->subDays($dias)->toDateString();
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
