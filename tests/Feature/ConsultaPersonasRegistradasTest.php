<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\Admin\ConsultaPagos;
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
        $this->assertNull($resumen['certificados_pendientes']);

        /* Los pagos los aporta ConsultaPagos, no este resumen. */
        $this->assertArrayNotHasKey('pagos_pendientes', $resumen);
    }

    public function test_cuenta_los_pagos_que_siguen_esperando_decision(): void
    {
        $this->assertSame(2, app(ConsultaPagos::class)->totalPorValidar());
    }

    /**
     * Las dos bandejas filtran por los mismos tres estados. El catálogo tiene
     * seis, pero «Pre-registro» y «Documentación» son etapas de captura que no
     * llegan a la bandeja, y «Cancelada» dejó de ofrecerse cuando se retiró la
     * acción de cancelar un trámite: sólo quedan expedientes históricos.
     */
    public function test_cada_bandeja_ofrece_los_estados_que_le_corresponden(): void
    {
        $this->assertSame(
            ['En revisión', 'Aprobada', 'Rechazada'],
            app(ConsultaPersonasRegistradas::class)->estados()
        );

        $this->assertSame(
            ['En revisión', 'Aprobada', 'Rechazada'],
            app(ConsultaPreRegistros::class)->estados()
        );
    }

    public function test_dashboard_y_bandeja_renderizan_datos_reales_sin_expediente_general(): void
    {
        /* La zona administrativa exige sesión: sin ella todo /admin redirige
           al login. El usuario 3 es el Superusuario, que ve el tablero entero. */
        $this->actingAs(Usuario::findOrFail(3));

        $this->get(route('admin.dashboard'))
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
                'Gestión de usuarios',
                'Certificados',
            ]);

        $this->get(route('admin.personas.registradas.index'))
            ->assertOk()
            /* Apellido paterno, materno y nombre(s): el orden de toda la zona
               administrativa. La bandeja sigue llegando con lo más reciente
               arriba y el alfabético se elige en el selector de orden.
               La segunda persona se busca por CURP y no por su nombre porque
               el renglón lo pinta Vue: el nombre sólo llega al HTML dentro del
               @json de data-vista, y ahí «Histórica» se escribe Hist\u00f3rica.
               Un assertSee con el acento no lo encontraría. */
            ->assertSee('Lovelace Byron Ada')
            ->assertSee('LEGA900101MDFABC05')
            ->assertSee('id="bandeja-personas-registradas-orden"', false)
            ->assertSee('Aprobada')
            ->assertDontSee('Hopper Murray Grace')
            ->assertDontSee('Ver expediente');
    }

    /**
     * La barra de filtros se quedó sin botón: la bandeja se acota mientras se
     * escribe, así que no quedaba nada que pulsar. Y la región viva pasó a ser
     * el conteo en lugar de la lista, porque releerla entera en cada pausa de
     * tecleo no le sirve a quien usa lector de pantalla.
     *
     * Es la única bandeja Vue con una prueba que renderice el partial, así que
     * aquí se afirma el markup que las tres comparten.
     */
    public function test_la_barra_de_filtros_no_tiene_boton_y_la_region_viva_es_el_conteo(): void
    {
        $this->actingAs(Usuario::findOrFail(3))
            ->get(route('admin.personas.registradas.index'))
            ->assertOk()
            ->assertDontSee('admin-bandeja-preregistros-boton-filtrar', false)
            ->assertSee('<div class="admin-bandeja-preregistros-lista">', false)
            ->assertSee('<p class="visually-hidden" role="status" v-if="personasFiltradas.length">', false)
            ->assertSee('id="bandeja-personas-registradas-termino"', false);
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

        /* Los permisos del tablero se resuelven contra PRIVILEGIO_ROL, así que
           la zona administrativa no responde sin estas dos tablas. */
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
            $table->string('esso_motivo_rechazo', 255)->nullable();
        });

        /* El dashboard cuenta los pagos por validar con ConsultaPagos. */
        Schema::create('pago', function (Blueprint $table): void {
            $table->integer('pago_id_pago')->primary();
            $table->string('pago_comprobante_path', 200)->nullable();
            $table->decimal('pago_monto_pagado', 10, 4)->nullable();
            $table->string('pago_referencia_bancaria', 20)->nullable();
            $table->date('pago_fecha_pago')->nullable();
            $table->time('pago_hora_pago')->nullable();
            $table->boolean('pago_uso_cfdi')->nullable();
            $table->integer('pago_id_dato_fiscal')->nullable();
            /* Marca del pago compartido de una referencia especial. */
            $table->integer('pago_no_empleado')->nullable();
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

        /* El usuario 3 es el Superusuario con el que se abre el tablero: tiene
           el catálogo completo, así que ve todas las tarjetas. */
        DB::table('privilegio')->insert([
            ['priv_id_privilegio' => 1, 'priv_privilegio' => 'Validación Registro'],
            ['priv_id_privilegio' => 2, 'priv_privilegio' => 'Gestionar Pagos'],
            ['priv_id_privilegio' => 3, 'priv_privilegio' => 'Generación Reportes'],
            ['priv_id_privilegio' => 4, 'priv_privilegio' => 'Gestionar usuarios'],
            ['priv_id_privilegio' => 5, 'priv_privilegio' => 'Gestionar Referencias'],
            ['priv_id_privilegio' => 6, 'priv_privilegio' => 'Gestionar Sedes'],
        ]);

        DB::table('privilegio_rol')->insert([
            ['ropr_id_privilegio' => 1, 'ropr_id_rol' => 2],
            ['ropr_id_privilegio' => 2, 'ropr_id_rol' => 2],
            ['ropr_id_privilegio' => 3, 'ropr_id_rol' => 2],
            ['ropr_id_privilegio' => 4, 'ropr_id_rol' => 2],
            ['ropr_id_privilegio' => 5, 'ropr_id_rol' => 2],
            ['ropr_id_privilegio' => 6, 'ropr_id_rol' => 2],
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
