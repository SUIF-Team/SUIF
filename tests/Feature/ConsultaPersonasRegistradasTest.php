<?php

namespace Tests\Feature;

use App\Support\Admin\ConsultaPersonasRegistradas;
use App\Support\Admin\ConsultaPreRegistros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsultaPersonasRegistradasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaTemporal();
        $this->cargarDatos();
    }

    public function test_lista_personas_con_una_solicitud_aprobada_aunque_tengan_otra_mas_reciente(): void
    {
        $personas = app(ConsultaPersonasRegistradas::class)->personas();

        $this->assertCount(2, $personas);
        $this->assertSame(['5', '1'], array_column($personas, 'id'));
        $this->assertSame('Aprobada', $personas[0]['estado']);
        $this->assertSame(
            ['id', 'nombre', 'primer_apellido', 'segundo_apellido', 'nombre_completo', 'curp', 'fecha_registro', 'estado', 'clase_estado'],
            array_keys($personas[0])
        );
        $this->assertArrayNotHasKey('ruta_expediente', $personas[0]);
    }

    public function test_resumen_cuenta_personas_y_solicitudes_segun_su_ultimo_estado(): void
    {
        $resumen = app(ConsultaPersonasRegistradas::class)->resumenDashboard();

        $this->assertSame(2, $resumen['personas_registradas']);
        $this->assertSame(1, $resumen['solicitudes_en_revision']);
        $this->assertNull($resumen['pagos_pendientes']);
        $this->assertNull($resumen['certificados_pendientes']);
    }

    public function test_estados_del_filtro_provienen_del_catalogo(): void
    {
        $this->assertSame(
            ['Pre-registro', 'Documentación', 'En revisión', 'Aprobada', 'Rechazada', 'Cancelada'],
            app(ConsultaPersonasRegistradas::class)->estados()
        );
    }

    public function test_dashboard_y_bandeja_renderizan_datos_reales_sin_expediente_general(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Personas registradas')
            ->assertSee('Solicitudes en revisión')
            ->assertSee('Sin datos persistidos');

        $this->get(route('admin.personas.registradas.index'))
            ->assertOk()
            ->assertSee('Ada Lovelace')
            ->assertSee('Cuenta Candidata')
            ->assertSee('Aprobada')
            ->assertDontSee('Grace Hopper')
            ->assertDontSee('Ver expediente');
    }

    public function test_bandeja_de_preregistros_incluye_a_toda_persona_con_clave_sin_importar_el_estado(): void
    {
        $personas = app(ConsultaPreRegistros::class)->bandeja();

        $this->assertCount(3, $personas);
        $this->assertSame(['50', '20', '11'], array_column($personas, 'id'));
        $this->assertSame(['Aprobada', 'Pre-registro', 'En revisión'], array_column($personas, 'estado_bandeja'));
    }

    private function crearEsquemaTemporal(): void
    {
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
            $table->string('pers_apellido_materno', 45);
            $table->date('pers_fecha_registro');
            $table->string('pers_clave_inegi')->nullable();
        });

        Schema::create('convocatoria', function (Blueprint $table): void {
            $table->integer('conv_id_convocatoria')->primary();
            $table->date('conv_fecha_inicio_registro');
            $table->date('conv_fecha_fin');
        });

        Schema::create('entidad_federativa', function (Blueprint $table): void {
            $table->string('enfe_clave_inegi')->primary();
            $table->string('enfe_entidad_federativa');
        });

        Schema::create('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_solicitud')->primary();
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_convocatoria');
        });

        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_c_estado_solicitud')->primary();
            $table->string('esso_estatus_solicitud', 40);
        });

        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_estado_solicitud')->primary();
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
        });
    }

    private function cargarDatos(): void
    {
        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Administrador'],
            ['rol_id_rol' => 3, 'rol_tipo_rol' => 'Candidato'],
        ]);

        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => 'hash-1'],
            ['usua_id_usuario' => 2, 'usua_id_rol' => 1, 'usua_clave_acceso' => 'hash-2'],
            ['usua_id_usuario' => 3, 'usua_id_rol' => 2, 'usua_clave_acceso' => 'hash-3'],
            ['usua_id_usuario' => 4, 'usua_id_rol' => 1, 'usua_clave_acceso' => 'hash-4'],
            ['usua_id_usuario' => 5, 'usua_id_rol' => 3, 'usua_clave_acceso' => 'hash-5'],
            ['usua_id_usuario' => 6, 'usua_id_rol' => 1, 'usua_clave_acceso' => null],
        ]);

        DB::table('persona')->insert([
            [
                'pers_id_persona' => 1,
                'pers_id_usuario' => 1,
                'pers_curp' => 'LOVA151210MDFABC01',
                'pers_nombre' => 'Ada',
                'pers_apellido_paterno' => 'Lovelace',
                'pers_apellido_materno' => 'Byron',
                'pers_fecha_registro' => '2026-08-01',
            ],
            [
                'pers_id_persona' => 2,
                'pers_id_usuario' => 2,
                'pers_curp' => 'HOPG061209MDFABC02',
                'pers_nombre' => 'Grace',
                'pers_apellido_paterno' => 'Hopper',
                'pers_apellido_materno' => 'Murray',
                'pers_fecha_registro' => '2026-08-02',
            ],
            [
                'pers_id_persona' => 3,
                'pers_id_usuario' => 3,
                'pers_curp' => 'ADMA800101MDFABC03',
                'pers_nombre' => 'Cuenta',
                'pers_apellido_paterno' => 'Administrativa',
                'pers_apellido_materno' => 'SUIF',
                'pers_fecha_registro' => '2026-08-03',
            ],
            [
                'pers_id_persona' => 4,
                'pers_id_usuario' => 4,
                'pers_curp' => 'SINS900101MDFABC04',
                'pers_nombre' => 'Sin',
                'pers_apellido_paterno' => 'Solicitud',
                'pers_apellido_materno' => 'Registrada',
                'pers_fecha_registro' => '2026-08-04',
            ],
            [
                'pers_id_persona' => 5,
                'pers_id_usuario' => 5,
                'pers_curp' => 'LEGA900101MDFABC05',
                'pers_nombre' => 'Cuenta',
                'pers_apellido_paterno' => 'Candidata',
                'pers_apellido_materno' => 'Histórica',
                'pers_fecha_registro' => '2026-08-05',
            ],
            [
                'pers_id_persona' => 6,
                'pers_id_usuario' => 6,
                'pers_curp' => 'INCO900101MDFABC06',
                'pers_nombre' => 'Registro',
                'pers_apellido_paterno' => 'Sin',
                'pers_apellido_materno' => 'Clave',
                'pers_fecha_registro' => '2026-08-05',
            ],
        ]);

        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_estatus_solicitud' => 'Pre-registro'],
            ['esso_id_c_estado_solicitud' => 2, 'esso_estatus_solicitud' => 'Documentación'],
            ['esso_id_c_estado_solicitud' => 3, 'esso_estatus_solicitud' => 'En revisión'],
            ['esso_id_c_estado_solicitud' => 4, 'esso_estatus_solicitud' => 'Aprobada'],
            ['esso_id_c_estado_solicitud' => 5, 'esso_estatus_solicitud' => 'Rechazada'],
            ['esso_id_c_estado_solicitud' => 6, 'esso_estatus_solicitud' => 'Cancelada'],
        ]);

        DB::table('convocatoria')->insert([
            [
                'conv_id_convocatoria' => 1,
                'conv_fecha_inicio_registro' => now()->subDay()->toDateString(),
                'conv_fecha_fin' => now()->addYear()->toDateString(),
            ],
            [
                'conv_id_convocatoria' => 2,
                'conv_fecha_inicio_registro' => now()->subYears(2)->toDateString(),
                'conv_fecha_fin' => now()->subYear()->toDateString(),
            ],
        ]);

        DB::table('solicitud')->insert([
            ['soli_id_solicitud' => 10, 'soli_id_persona' => 1, 'soli_id_convocatoria' => 2],
            ['soli_id_solicitud' => 11, 'soli_id_persona' => 1, 'soli_id_convocatoria' => 1],
            ['soli_id_solicitud' => 20, 'soli_id_persona' => 2, 'soli_id_convocatoria' => 1],
            ['soli_id_solicitud' => 30, 'soli_id_persona' => 3, 'soli_id_convocatoria' => 1],
            ['soli_id_solicitud' => 50, 'soli_id_persona' => 5, 'soli_id_convocatoria' => 1],
            ['soli_id_solicitud' => 60, 'soli_id_persona' => 6, 'soli_id_convocatoria' => 1],
        ]);

        DB::table('estado_solicitud')->insert([
            ['esso_id_estado_solicitud' => 1, 'esso_id_c_estado_solicitud' => 4, 'esso_id_solicitud' => 10],
            ['esso_id_estado_solicitud' => 2, 'esso_id_c_estado_solicitud' => 1, 'esso_id_solicitud' => 11],
            ['esso_id_estado_solicitud' => 3, 'esso_id_c_estado_solicitud' => 3, 'esso_id_solicitud' => 11],
            ['esso_id_estado_solicitud' => 4, 'esso_id_c_estado_solicitud' => 1, 'esso_id_solicitud' => 20],
            ['esso_id_estado_solicitud' => 6, 'esso_id_c_estado_solicitud' => 3, 'esso_id_solicitud' => 30],
            ['esso_id_estado_solicitud' => 7, 'esso_id_c_estado_solicitud' => 4, 'esso_id_solicitud' => 50],
            ['esso_id_estado_solicitud' => 8, 'esso_id_c_estado_solicitud' => 3, 'esso_id_solicitud' => 60],
        ]);
    }
}
