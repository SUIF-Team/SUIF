<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\Admin\ConsultaPagos;
use App\Support\Admin\ConsultaPersonasRegistradas;
use App\Support\Admin\ConsultaPreRegistros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

class ConsultaPersonasRegistradasTest extends TestCase
{
    use SiembraAdministradores;

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
        $this->assertNull($resumen['certificados_pendientes']);

        /* Los pagos los aporta ConsultaPagos, no este resumen. */
        $this->assertArrayNotHasKey('pagos_pendientes', $resumen);
    }

    public function test_cuenta_los_pagos_que_siguen_esperando_decision(): void
    {
        $this->assertSame(2, app(ConsultaPagos::class)->totalPorValidar());
    }

    public function test_el_filtro_solo_ofrece_los_tres_estados_de_revision(): void
    {
        $esperados = ['En revisión', 'Aprobada', 'Rechazada'];

        $this->assertSame($esperados, app(ConsultaPersonasRegistradas::class)->estados());
        $this->assertSame($esperados, app(ConsultaPreRegistros::class)->estados());
    }

    public function test_dashboard_y_bandeja_renderizan_datos_reales_sin_expediente_general(): void
    {
        /* El usuario 3 es el Superusuario: es el único que ve el tablero completo. */
        $superusuario = Usuario::findOrFail(3);

        $this->actingAs($superusuario)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Personas registradas')
            ->assertSee('Solicitudes en revisión')
            ->assertSee('Pagos por validar')
            /* Sólo certificados sigue sin datos. */
            ->assertSee('Sin datos persistidos')
            /* El cierre de sesión vive únicamente en el navbar. */
            ->assertDontSee('admin-dashboard-salida', false)
            ->assertSeeInOrder([
                'Pre-registro',
                'Personas registradas',
                'Subir referencias bancarias',
                'Referencias bancarias',
                'Pagos',
                'Sedes',
                'Grupos',
                'Administradores',
                'Certificados',
            ]);

        $this->actingAs($superusuario)
            ->get(route('admin.personas.registradas.index'))
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
            $table->boolean('usua_activo')->default(true);
        });
        $this->crearTablasDePrivilegios();

        Schema::create('persona', function (Blueprint $table): void {
            $table->integer('pers_id_persona')->primary();
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
            $table->string('pers_rfc', 13)->nullable();
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
            $table->integer('soli_id_pago')->nullable();
        });

        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_c_estado_solicitud')->primary();
            $table->string('esso_estado_solicitud', 40);
        });

        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_estado_solicitud')->primary();
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
        });

        /* El dashboard cuenta los pagos por validar con ConsultaPagos. */
        Schema::create('pago', function (Blueprint $table): void {
            $table->integer('pago_id_pago')->primary();
            $table->string('pago_comprobante_path', 200)->nullable();
            $table->decimal('pago_monto_pagado', 10, 4)->nullable();
            $table->string('pago_referencia_bancaria', 20)->nullable();
            $table->date('pago_fecha_pago')->nullable();
            $table->time('pago_hora_pago')->nullable();
        });

        /* ConsultaPagos cruza PAGO con el catálogo para mostrar, junto al monto
           declarado, el que se cobró. */
        Schema::create('referencia_bancaria', function (Blueprint $table): void {
            $table->increments('reba_id_referencia_bancaria');
            $table->integer('reba_id_pago')->nullable();
            $table->string('reba_referencia', 20);
            $table->decimal('reba_monto', 10, 4)->nullable();
            $table->date('reba_vigencia')->nullable();
        });

        Schema::create('c_estado_pago', function (Blueprint $table): void {
            $table->integer('espa_id_c_estado_pago')->primary();
            $table->string('esta_estado_pago', 15);
        });

        Schema::create('estado_pago', function (Blueprint $table): void {
            $table->integer('espa_id_estado_pago')->primary();
            $table->integer('espa_id_pago');
            $table->integer('espa_id_c_estado_pago');
            $table->date('espa_fecha')->nullable();
            $table->time('espa_hora')->nullable();
            $table->text('espa_comentario')->nullable();
        });
    }

    private function cargarDatos(): void
    {
        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Superusuario'],
            ['rol_id_rol' => 3, 'rol_tipo_rol' => 'Candidato'],
        ]);

        /* El tablero sólo pinta lo que el privilegio de quien mira permite. */
        $this->concederPrivilegiosAlRol(2, $this->privilegiosDeSuperusuario());

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
            ['esso_id_c_estado_solicitud' => 1, 'esso_estado_solicitud' => 'Pre-registro'],
            ['esso_id_c_estado_solicitud' => 2, 'esso_estado_solicitud' => 'Documentación'],
            ['esso_id_c_estado_solicitud' => 3, 'esso_estado_solicitud' => 'En revisión'],
            ['esso_id_c_estado_solicitud' => 4, 'esso_estado_solicitud' => 'Aprobada'],
            ['esso_id_c_estado_solicitud' => 5, 'esso_estado_solicitud' => 'Rechazada'],
            ['esso_id_c_estado_solicitud' => 6, 'esso_estado_solicitud' => 'Cancelada'],
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
            ['soli_id_solicitud' => 10, 'soli_id_persona' => 1, 'soli_id_convocatoria' => 2, 'soli_id_pago' => 5],
            ['soli_id_solicitud' => 11, 'soli_id_persona' => 1, 'soli_id_convocatoria' => 1, 'soli_id_pago' => 1],
            ['soli_id_solicitud' => 20, 'soli_id_persona' => 2, 'soli_id_convocatoria' => 1, 'soli_id_pago' => 2],
            ['soli_id_solicitud' => 30, 'soli_id_persona' => 3, 'soli_id_convocatoria' => 1, 'soli_id_pago' => 4],
            ['soli_id_solicitud' => 50, 'soli_id_persona' => 5, 'soli_id_convocatoria' => 1, 'soli_id_pago' => 3],
            ['soli_id_solicitud' => 60, 'soli_id_persona' => 6, 'soli_id_convocatoria' => 1, 'soli_id_pago' => null],
        ]);

        /*
         * Sólo los pagos 1 y 2 cuentan como «por validar»: el 3 ya se resolvió,
         * el 4 es de una cuenta administrativa y el 5 no tiene comprobante.
         */
        DB::table('pago')->insert([
            ['pago_id_pago' => 1, 'pago_comprobante_path' => 'solicitudes/11/comprobante.pdf'],
            ['pago_id_pago' => 2, 'pago_comprobante_path' => 'solicitudes/20/comprobante.pdf'],
            ['pago_id_pago' => 3, 'pago_comprobante_path' => 'solicitudes/50/comprobante.pdf'],
            ['pago_id_pago' => 4, 'pago_comprobante_path' => 'solicitudes/30/comprobante.pdf'],
            ['pago_id_pago' => 5, 'pago_comprobante_path' => ''],
        ]);

        DB::table('referencia_bancaria')->insert([
            ['reba_id_pago' => 1, 'reba_referencia' => 'REF-0001', 'reba_monto' => 7000],
            ['reba_id_pago' => 2, 'reba_referencia' => 'REF-0002', 'reba_monto' => 7000],
        ]);

        DB::table('c_estado_pago')->insert([
            ['espa_id_c_estado_pago' => 1, 'esta_estado_pago' => 'Pendiente'],
            ['espa_id_c_estado_pago' => 2, 'esta_estado_pago' => 'Completado'],
        ]);

        /* El pago 1 no tiene ningún estado: la bandeja lo trata como pendiente. */
        DB::table('estado_pago')->insert([
            ['espa_id_estado_pago' => 1, 'espa_id_pago' => 2, 'espa_id_c_estado_pago' => 1],
            ['espa_id_estado_pago' => 2, 'espa_id_pago' => 3, 'espa_id_c_estado_pago' => 1],
            ['espa_id_estado_pago' => 3, 'espa_id_pago' => 3, 'espa_id_c_estado_pago' => 2],
            ['espa_id_estado_pago' => 4, 'espa_id_pago' => 4, 'espa_id_c_estado_pago' => 1],
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
