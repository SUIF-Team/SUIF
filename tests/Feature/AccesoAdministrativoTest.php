<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * Quién entra a dónde.
 *
 * Cada administrador aterriza en su bandeja y no ve los módulos ajenos, y la
 * zona administrativa dejó de responder sin sesión iniciada.
 */
class AccesoAdministrativoTest extends TestCase
{
    private const SUPERUSUARIO = 1;

    private const ADMIN_UIF = 2;

    private const ADMIN_DEC = 3;

    private const PERSONA = 4;

    use SiembraAdministradores;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaTemporal();
        $this->cargarDatos();
    }

    public function test_el_admin_uif_solo_alcanza_el_preregistro_y_la_documentacion(): void
    {
        $uif = Usuario::findOrFail(self::ADMIN_UIF);

        $this->actingAs($uif)->get(route('admin.personas.registradas.index'))->assertOk();

        $this->actingAs($uif)->get(route('admin.pagos.index'))->assertForbidden();
        $this->actingAs($uif)->get(route('admin.referencias.index'))->assertForbidden();
        $this->actingAs($uif)->get(route('admin.sedes.index'))->assertForbidden();
        $this->actingAs($uif)->get(route('admin.administradores.index'))->assertForbidden();
        /* Reanudar un pago es del área que revisa el dinero, no de la suya. */
        $this->actingAs($uif)->post(route('admin.pagos.reanudar', 1))->assertForbidden();
    }

    public function test_el_admin_dec_solo_alcanza_los_pagos_y_las_referencias(): void
    {
        $dec = Usuario::findOrFail(self::ADMIN_DEC);

        $this->actingAs($dec)->get(route('admin.pagos.index'))->assertOk();

        $this->actingAs($dec)->get(route('admin.personas.index'))->assertForbidden();
        $this->actingAs($dec)->get(route('admin.personas.registradas.index'))->assertForbidden();
        $this->actingAs($dec)->get(route('admin.sedes.index'))->assertForbidden();
        $this->actingAs($dec)->get(route('admin.administradores.index'))->assertForbidden();
        /* Cancelar un trámite es del área que valida el registro. */
        $this->actingAs($dec)->post(route('admin.documentos.cancelar', 1))->assertForbidden();
    }

    public function test_cada_quien_aterriza_en_su_bandeja_al_iniciar_sesion(): void
    {
        $this->acceder('UIFA800101HDFRRN02')->assertRedirect(route('admin.personas.index'));
        $this->post(route('logout'));

        $this->acceder('DECA800101HDFRRN03')->assertRedirect(route('admin.pagos.index'));
        $this->post(route('logout'));

        /* Al Superusuario el tablero sí le sirve: es el único que lo ve entero. */
        $this->acceder('SUPE800101HDFRRN01')->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'));

        $this->acceder('PERS900101MDFPRB04')->assertRedirect(route('persona.dashboard'));
    }

    public function test_el_tablero_solo_pinta_los_modulos_de_quien_lo_mira(): void
    {
        $this->actingAs(Usuario::findOrFail(self::ADMIN_UIF))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pre-registro')
            ->assertSee('Personas registradas')
            ->assertDontSee('Referencias bancarias')
            ->assertDontSee('Administradores')
            ->assertDontSee('Pagos por validar');

        $this->actingAs(Usuario::findOrFail(self::ADMIN_DEC))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pagos')
            ->assertSee('Referencias bancarias')
            ->assertSee('Pagos por validar')
            ->assertDontSee('Administradores')
            ->assertDontSee('Solicitudes en revisión');
    }

    public function test_la_zona_administrativa_ya_no_responde_sin_sesion(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.personas.index'))->assertRedirect(route('login'));
        $this->get(route('admin.personas.registradas.index'))->assertRedirect(route('login'));
        $this->post(route('admin.documentos.validar', 1))->assertRedirect(route('login'));
        $this->post(route('admin.documentos.interrumpir', 1))->assertRedirect(route('login'));
        $this->get(route('admin.resultados.index'))->assertRedirect(route('login'));
    }

    /**
     * La baja tiene que surtir efecto de inmediato: comprobarla sólo en el
     * login dejaría trabajando a quien ya tuviera la sesión abierta.
     */
    public function test_la_baja_corta_la_sesion_que_ya_estaba_abierta(): void
    {
        $this->actingAs(Usuario::findOrFail(self::ADMIN_UIF))
            ->get(route('admin.personas.registradas.index'))
            ->assertOk();

        DB::table('usuario')
            ->where('usua_id_usuario', self::ADMIN_UIF)
            ->update(['usua_activo' => false]);

        $this->actingAs(Usuario::findOrFail(self::ADMIN_UIF))
            ->get(route('admin.personas.registradas.index'))
            ->assertForbidden();
    }

    public function test_una_persona_solicitante_no_pisa_la_zona_administrativa(): void
    {
        $this->actingAs(Usuario::findOrFail(self::PERSONA))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    private function acceder(string $curp)
    {
        return $this->post(route('login.post'), [
            'curp' => $curp,
            'clave' => 'ClaveSegura1',
        ]);
    }

    private function crearEsquemaTemporal(): void
    {
        foreach ([
            'referencia_bancaria',
            'estado_pago',
            'c_estado_pago',
            'pago',
            'estado_solicitud',
            'c_estado_solicitud',
            'solicitud',
            'privilegio_rol',
            'privilegio',
            'entidad_federativa',
            'persona',
            'usuario',
            'rol',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('rol', function (Blueprint $table): void {
            $table->increments('rol_id_rol');
            $table->string('rol_tipo_rol', 15);
        });
        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('usua_id_usuario');
            $table->integer('usua_id_rol');
            $table->string('usua_clave_acceso')->nullable();
            $table->boolean('usua_activo')->default(true);
        });
        Schema::create('persona', function (Blueprint $table): void {
            $table->increments('pers_id_persona');
            $table->string('pers_clave_inegi', 3)->nullable();
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
            $table->string('pers_nombre', 45);
            $table->string('pers_apellido_paterno', 45)->nullable();
            $table->string('pers_apellido_materno', 45)->nullable();
            $table->date('pers_fecha_registro');
        });
        Schema::create('entidad_federativa', function (Blueprint $table): void {
            $table->string('enfe_clave_inegi', 3)->primary();
            $table->string('enfe_entidad_federativa', 60);
        });

        /* Las bandejas que estas pruebas abren consultan estas tablas aunque
           estén vacías: sin ellas la pantalla revienta antes de llegar al
           permiso, que es lo que aquí se está comprobando. */
        Schema::create('solicitud', function (Blueprint $table): void {
            $table->increments('soli_id_solicitud');
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_pago')->nullable();
            $table->integer('soli_id_evaluacion')->nullable();
            $table->integer('soli_id_convocatoria')->nullable();
        });
        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_c_estado_solicitud');
            $table->string('esso_estado_solicitud', 45);
        });
        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_estado_solicitud');
            $table->integer('esso_id_solicitud');
            $table->integer('esso_id_c_estado_solicitud');
            $table->string('esso_motivo_rechazo', 255)->nullable();
        });
        Schema::create('pago', function (Blueprint $table): void {
            $table->increments('pago_id_pago');
            $table->string('pago_comprobante_path')->nullable();
            $table->decimal('pago_monto_pagado', 10, 2)->nullable();
            $table->date('pago_fecha_pago')->nullable();
            $table->time('pago_hora_pago')->nullable();
        });
        Schema::create('c_estado_pago', function (Blueprint $table): void {
            $table->increments('espa_id_c_estado_pago');
            $table->string('esta_estado_pago', 35);
        });
        Schema::create('estado_pago', function (Blueprint $table): void {
            $table->increments('espa_id_estado_pago');
            $table->integer('espa_id_pago');
            $table->integer('espa_id_c_estado_pago');
            $table->date('espa_fecha')->nullable();
            $table->time('espa_hora')->nullable();
            $table->text('espa_comentario')->nullable();
        });
        Schema::create('referencia_bancaria', function (Blueprint $table): void {
            $table->increments('reba_id_referencia_bancaria');
            $table->integer('reba_id_pago')->nullable();
            $table->string('reba_referencia', 20);
            $table->string('reba_path', 200)->nullable();
            $table->decimal('reba_monto', 10, 4)->nullable();
            $table->date('reba_vigencia')->nullable();
            $table->date('reba_fecha_emision')->nullable();
            $table->date('reba_fecha_carga');
            $table->time('reba_hora_carga');
            $table->date('reba_fecha_asignacion')->nullable();
            $table->time('reba_hora_asignacion')->nullable();
        });

        $this->crearTablasDePrivilegios();
    }

    private function cargarDatos(): void
    {
        DB::table('entidad_federativa')->insert([
            ['enfe_clave_inegi' => '009', 'enfe_entidad_federativa' => 'Ciudad de México'],
        ]);

        $this->sembrarRolesAdministrativos([
            'Superusuario' => 1,
            'Admin UIF' => 2,
            'Admin DEC' => 3,
        ]);
        DB::table('rol')->insert(['rol_id_rol' => 4, 'rol_tipo_rol' => 'Persona']);

        $clave = Hash::make('ClaveSegura1');

        DB::table('usuario')->insert([
            ['usua_id_usuario' => self::SUPERUSUARIO, 'usua_id_rol' => 1, 'usua_clave_acceso' => $clave, 'usua_activo' => true],
            ['usua_id_usuario' => self::ADMIN_UIF, 'usua_id_rol' => 2, 'usua_clave_acceso' => $clave, 'usua_activo' => true],
            ['usua_id_usuario' => self::ADMIN_DEC, 'usua_id_rol' => 3, 'usua_clave_acceso' => $clave, 'usua_activo' => true],
            ['usua_id_usuario' => self::PERSONA, 'usua_id_rol' => 4, 'usua_clave_acceso' => $clave, 'usua_activo' => true],
        ]);

        $personas = [
            [self::SUPERUSUARIO, 'SUPE800101HDFRRN01', 'Ana', 'Mando'],
            [self::ADMIN_UIF, 'UIFA800101HDFRRN02', 'Beto', 'Revisor'],
            [self::ADMIN_DEC, 'DECA800101HDFRRN03', 'Cira', 'Tesorera'],
            [self::PERSONA, 'PERS900101MDFPRB04', 'Dora', 'Solicitante'],
        ];

        DB::table('persona')->insert(array_map(
            fn (array $datos, int $indice): array => [
                'pers_id_persona' => $indice + 1,
                'pers_clave_inegi' => '009',
                'pers_id_usuario' => $datos[0],
                'pers_curp' => $datos[1],
                'pers_nombre' => $datos[2],
                'pers_apellido_paterno' => $datos[3],
                'pers_apellido_materno' => 'Prueba',
                'pers_fecha_registro' => '2026-08-01',
            ],
            $personas,
            array_keys($personas)
        ));
    }
}
