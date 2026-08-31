<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\ComprobanteFiscal;
use App\Support\Admin\ConsultaPagos;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El comprobante que la persona pide de su pago —ticket o CFDI— y, cuando
 * pide CFDI, los datos con los que se le factura.
 *
 * Pedirlo es opcional y elegirlo es definitivo: las dos reglas se comprueban
 * contra el servicio y contra las pantallas.
 */
class ComprobanteFiscalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaTemporal();
        $this->cargarDatos();
        Storage::fake('comprobantes');
        Storage::disk('comprobantes')->put('solicitudes/100/recibo.pdf', 'PDF de prueba');
    }

    /* ── El selector ──────────────────────────────────────────────────── */

    public function test_el_selector_aparece_cuando_el_pago_esta_validado(): void
    {
        $this->validarPago(1);

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.pago.index'))
            ->assertOk()
            ->assertSee('Quiero ticket')
            ->assertSee('Quiero CFDI')
            ->assertSee('no es obligatorio');
    }

    public function test_el_selector_no_aparece_mientras_el_pago_sigue_en_revision(): void
    {
        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.pago.index'))
            ->assertOk()
            ->assertDontSee('Quiero ticket')
            ->assertDontSee('Quiero CFDI');
    }

    public function test_elegir_ticket_guarda_falso_en_pago_uso_cfdi(): void
    {
        $this->validarPago(1);

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.pago.tipo-comprobante'), ['tipo' => 'ticket'])
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHas('success');

        $this->assertFalse($this->usoCfdi(1));
    }

    public function test_elegir_cfdi_guarda_verdadero_y_ofrece_llenar_el_formulario(): void
    {
        $this->validarPago(1);

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.pago.tipo-comprobante'), ['tipo' => 'cfdi'])
            ->assertRedirect(route('persona.pago.index'));

        $this->assertTrue($this->usoCfdi(1));

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.pago.index'))
            ->assertOk()
            ->assertSee('Llenar formulario')
            ->assertSee('Te lo haremos llegar por correo electrónico.');
    }

    public function test_la_eleccion_no_se_puede_cambiar(): void
    {
        $this->validarPago(1);
        app(ComprobanteFiscal::class)->registrarEleccion(1, 'ticket');

        /* La regla vive en el servidor, no en los botones ocultos: un POST a
           mano con la otra opción tampoco la mueve. */
        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.pago.tipo-comprobante'), ['tipo' => 'cfdi'])
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHas('warning', 'Ya elegiste el tipo de comprobante y no puede modificarse.');

        $this->assertFalse($this->usoCfdi(1));
    }

    public function test_repetir_la_misma_eleccion_no_falla(): void
    {
        $this->validarPago(1);

        /* Un doble clic o el reenvío del formulario no deben reventar. */
        app(ComprobanteFiscal::class)->registrarEleccion(1, 'cfdi');
        app(ComprobanteFiscal::class)->registrarEleccion(1, 'cfdi');

        $this->assertTrue($this->usoCfdi(1));
    }

    public function test_no_se_puede_elegir_si_el_pago_no_esta_validado(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El tipo de comprobante se elige cuando tu pago ha sido validado.');

        try {
            app(ComprobanteFiscal::class)->registrarEleccion(1, 'ticket');
        } finally {
            $this->assertNull($this->usoCfdi(1));
        }
    }

    public function test_la_ruta_de_eleccion_exige_sesion_iniciada(): void
    {
        $this->post(route('persona.pago.tipo-comprobante'), ['tipo' => 'ticket'])
            ->assertRedirect(route('login'));

        $this->assertNull($this->usoCfdi(1));
    }

    /* ── Los datos fiscales ───────────────────────────────────────────── */

    public function test_el_formulario_de_facturacion_exige_haber_elegido_cfdi(): void
    {
        $persona = Usuario::findOrFail(1);

        $this->actingAs($persona)
            ->get(route('persona.facturacion.index'))
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHas('warning');

        $this->validarPago(1);
        app(ComprobanteFiscal::class)->registrarEleccion(1, 'ticket');

        $this->actingAs($persona)
            ->get(route('persona.facturacion.index'))
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHas('warning');
    }

    public function test_el_formulario_de_facturacion_se_abre_con_cfdi_elegido(): void
    {
        $this->prepararCfdi();

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.facturacion.index'))
            ->assertOk()
            ->assertSee('Datos para tu CFDI')
            ->assertSee('626 - RESICO');
    }

    public function test_guardar_datos_fiscales_crea_el_renglon_y_lo_liga_al_pago(): void
    {
        $this->prepararCfdi();

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.facturacion.store'), $this->datosFiscalesValidos())
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('dato_fiscal', [
            'dafi_id_dato_fiscal' => 1,
            'dafi_id_regimen_fiscal' => 4,
            'dafi_id_codigo_postal' => '01000',
            'dafi_razon_social' => 'Ana Candidata Prueba',
            'dafi_rfc' => 'CAPA900101AB1',
            'dafi_persona_moral' => false,
            'dafi_uso_cfdi' => true,
        ]);

        $this->assertDatabaseHas('pago', [
            'pago_id_pago' => 1,
            'pago_id_dato_fiscal' => 1,
        ]);
    }

    public function test_el_codigo_postal_se_da_de_alta_si_no_existe_en_el_catalogo(): void
    {
        $this->prepararCfdi();

        $this->assertDatabaseMissing('codigo_postal', ['copo_id_codigo_postal' => '03100']);

        $datos = $this->datosFiscalesValidos();
        $datos['codigo_postal'] = '03100';

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.facturacion.store'), $datos)
            ->assertSessionHas('success');

        /* DAFI_ID_CODIGO_POSTAL tiene llave foránea contra el catálogo: sin
           esta alta el renglón no podría existir. */
        $this->assertDatabaseHas('codigo_postal', ['copo_id_codigo_postal' => '03100']);
        $this->assertDatabaseHas('dato_fiscal', ['dafi_id_codigo_postal' => '03100']);
    }

    public function test_el_correo_de_facturacion_se_guarda_en_comunicacion(): void
    {
        $this->prepararCfdi();

        $datos = $this->datosFiscalesValidos();
        $datos['correo_cfdi'] = 'Facturacion@Ejemplo.MX';

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.facturacion.store'), $datos)
            ->assertSessionHas('success');

        $correo = DB::table('comunicacion as co')
            ->join('tipo_comunicacion as tc', 'tc.tico_id_tipo_comunicacion', '=', 'co.comu_id_tipo_comunicacion')
            ->where('co.comu_id_persona', 1)
            ->where('tc.tico_tipo_comunicacion', 'Correo facturación')
            ->value('co.comu_descripcion');

        $this->assertSame('facturacion@ejemplo.mx', $correo);

        /* El correo principal no se toca: son dos contactos distintos. */
        $this->assertDatabaseHas('comunicacion', [
            'comu_id_persona' => 1,
            'comu_id_tipo_comunicacion' => 1,
            'comu_descripcion' => 'ana@ejemplo.mx',
        ]);
    }

    public function test_los_datos_fiscales_se_validan(): void
    {
        $this->prepararCfdi();

        $this->actingAs(Usuario::findOrFail(1))
            ->from(route('persona.facturacion.index'))
            ->post(route('persona.facturacion.store'), [
                'razon_social' => str_repeat('A', 40),
                'persona_moral' => '0',
                'regimen_fiscal' => 99,
                'codigo_postal' => '1234',
                'rfc' => 'ABC',
                'correo_cfdi' => 'no-es-correo',
            ])
            ->assertSessionHasErrors(['razon_social', 'regimen_fiscal', 'codigo_postal', 'rfc', 'correo_cfdi']);

        $this->assertDatabaseCount('dato_fiscal', 0);
    }

    public function test_el_rfc_moral_admite_doce_caracteres_y_el_fisico_trece(): void
    {
        $this->prepararCfdi();

        $datos = $this->datosFiscalesValidos();
        $datos['persona_moral'] = '0';
        $datos['rfc'] = 'ABC900101AB1';

        /* Doce caracteres es el RFC de una moral: para una física falta uno. */
        $this->actingAs(Usuario::findOrFail(1))
            ->from(route('persona.facturacion.index'))
            ->post(route('persona.facturacion.store'), $datos)
            ->assertSessionHasErrors('rfc');

        $datos['persona_moral'] = '1';

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.facturacion.store'), $datos)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('dato_fiscal', [
            'dafi_rfc' => 'ABC900101AB1',
            'dafi_persona_moral' => true,
        ]);
    }

    public function test_los_datos_fiscales_no_se_pueden_capturar_dos_veces(): void
    {
        $this->prepararCfdi();

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.facturacion.store'), $this->datosFiscalesValidos())
            ->assertSessionHas('success');

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.facturacion.store'), $this->datosFiscalesValidos())
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHas('warning', 'Tus datos de facturación ya fueron registrados y no pueden modificarse.');

        $this->assertDatabaseCount('dato_fiscal', 1);
    }

    public function test_no_se_pueden_capturar_datos_fiscales_con_ticket_elegido(): void
    {
        $this->validarPago(1);
        app(ComprobanteFiscal::class)->registrarEleccion(1, 'ticket');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Elige la opción CFDI antes de capturar tus datos de facturación.');

        app(ComprobanteFiscal::class)->guardarDatosFiscales(1, $this->datosFiscalesValidos());
    }

    /* ── Lo que ve el administrador ───────────────────────────────────── */

    public function test_el_administrador_ve_el_comprobante_solicitado_y_los_datos_fiscales(): void
    {
        $this->prepararCfdi();

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.facturacion.store'), $this->datosFiscalesValidos());

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('Comprobante solicitado')
            ->assertSee('Datos para el CFDI')
            ->assertSee('CAPA900101AB1')
            ->assertSee('626 - RESICO')
            ->assertSee('facturacion@ejemplo.mx');
    }

    public function test_el_administrador_ve_sin_solicitar_cuando_la_persona_no_eligio(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('Sin solicitar');

        $this->assertSame('Sin solicitar', app(ConsultaPagos::class)->pago(1)['comprobante_solicitado']);
        $this->assertNull(app(ConsultaPagos::class)->pago(1)['datos_fiscales']);
    }

    public function test_el_administrador_sabe_cuando_el_cfdi_todavia_no_tiene_datos(): void
    {
        $this->prepararCfdi();

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('CFDI')
            ->assertSee('todavía no captura sus datos de facturación');
    }

    /* ── Apoyos ───────────────────────────────────────────────────────── */

    /**
     * @return array<string, string>
     */
    private function datosFiscalesValidos(): array
    {
        return [
            'razon_social' => 'Ana Candidata Prueba',
            'persona_moral' => '0',
            'regimen_fiscal' => '4',
            'codigo_postal' => '01000',
            'rfc' => 'CAPA900101AB1',
            'correo_cfdi' => 'facturacion@ejemplo.mx',
        ];
    }

    private function prepararCfdi(): void
    {
        $this->validarPago(1);
        app(ComprobanteFiscal::class)->registrarEleccion(1, 'cfdi');
    }

    private function validarPago(int $id_pago): void
    {
        DB::table('estado_pago')->insert([
            'espa_id_pago' => $id_pago,
            'espa_id_c_estado_pago' => 2,
            'espa_fecha' => '2026-08-03',
            'espa_hora' => '09:00:00',
            'espa_comentario' => null,
        ]);
    }

    private function usoCfdi(int $id_pago): ?bool
    {
        return ComprobanteFiscal::normalizarUsoCfdi(
            DB::table('pago')->where('pago_id_pago', $id_pago)->value('pago_uso_cfdi')
        );
    }

    private function crearEsquemaTemporal(): void
    {
        foreach ([
            'privilegio_rol',
            'privilegio',
            'comunicacion',
            'tipo_comunicacion',
            'estado_pago',
            'c_estado_pago',
            'referencia_bancaria',
            'convocatoria',
            'pago',
            'dato_fiscal',
            'regimen_fiscal',
            'codigo_postal',
            'estado_solicitud',
            'c_estado_solicitud',
            'estado_documento',
            'c_estado_documento',
            'documento',
            'tipo_documento',
            'solicitud',
            'entidad_federativa',
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
            $table->string('pers_clave_inegi', 3)->nullable();
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
            $table->string('pers_nombre', 45);
            $table->string('pers_apellido_paterno', 45)->nullable();
            $table->string('pers_apellido_materno', 45);
            $table->date('pers_fecha_registro');
        });

        Schema::create('entidad_federativa', function (Blueprint $table): void {
            $table->string('enfe_clave_inegi', 3)->primary();
            $table->string('enfe_entidad_federativa', 45);
        });

        /* El correo del CFDI y el principal viven aquí, con tipos distintos. */
        Schema::create('tipo_comunicacion', function (Blueprint $table): void {
            $table->integer('tico_id_tipo_comunicacion')->primary();
            $table->string('tico_tipo_comunicacion', 25);
        });

        Schema::create('comunicacion', function (Blueprint $table): void {
            $table->increments('comu_id_comunicacion');
            $table->integer('comu_id_persona');
            $table->integer('comu_id_tipo_comunicacion');
            $table->string('comu_descripcion', 65);
        });

        Schema::create('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_solicitud')->primary();
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_convocatoria')->nullable();
            $table->integer('soli_id_pago')->nullable();
            $table->integer('soli_id_evaluacion')->nullable();
        });

        Schema::create('convocatoria', function (Blueprint $table): void {
            $table->integer('conv_id_convocatoria')->primary();
            $table->string('conv_monto_recuperacion')->nullable();
        });

        Schema::create('referencia_bancaria', function (Blueprint $table): void {
            $table->increments('reba_id_referencia_bancaria');
            $table->integer('reba_id_pago')->nullable();
            $table->string('reba_referencia', 20);
            $table->decimal('reba_monto', 10, 4)->nullable();
            $table->date('reba_vigencia')->nullable();
        });

        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_c_estado_solicitud')->primary();
            $table->string('esso_estado_solicitud', 40);
        });

        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_estado_solicitud');
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
            $table->date('esso_fecha');
            $table->time('esso_hora');
            $table->string('esso_motivo_rechazo', 255)->nullable();
        });

        /* AvancePersona consulta estas cuatro en cada pantalla de persona,
           porque la barra lateral pinta el avance documental. */
        Schema::create('tipo_documento', function (Blueprint $table): void {
            $table->integer('tido_id_tipo_documento')->primary();
            $table->string('tido_tipo_documento', 60);
        });

        Schema::create('documento', function (Blueprint $table): void {
            $table->integer('docu_id_documento')->primary();
            $table->integer('tido_id_tipo_documento');
            $table->integer('soli_id_solicitud');
        });

        Schema::create('c_estado_documento', function (Blueprint $table): void {
            $table->integer('esdo_id_c_estado_documento')->primary();
            $table->string('esdo_estado_documento', 45);
        });

        Schema::create('estado_documento', function (Blueprint $table): void {
            $table->increments('esdo_id_estado_documento');
            $table->integer('esdo_id_c_estado_documento');
            $table->integer('esdo_id_documento');
        });

        Schema::create('codigo_postal', function (Blueprint $table): void {
            $table->string('copo_id_codigo_postal', 5)->primary();
        });

        Schema::create('regimen_fiscal', function (Blueprint $table): void {
            $table->integer('refi_id_regimen_fiscal')->primary();
            $table->string('refi_regimen_fiscal', 35);
        });

        Schema::create('dato_fiscal', function (Blueprint $table): void {
            $table->increments('dafi_id_dato_fiscal');
            $table->integer('dafi_id_regimen_fiscal');
            $table->string('dafi_id_codigo_postal', 5);
            $table->string('dafi_razon_social', 35);
            $table->string('dafi_rfc', 13);
            $table->boolean('dafi_persona_moral');
            $table->boolean('dafi_uso_cfdi');
        });

        Schema::create('pago', function (Blueprint $table): void {
            $table->integer('pago_id_pago')->primary();
            $table->string('pago_comprobante_path', 200);
            $table->decimal('pago_monto_pagado', 10, 4);
            $table->string('pago_referencia_bancaria', 20);
            $table->string('pago_referencia_bancaria_path', 200)->nullable();
            $table->date('pago_fecha_pago');
            $table->time('pago_hora_pago');
            $table->boolean('pago_uso_cfdi')->nullable();
            $table->integer('pago_id_dato_fiscal')->nullable();
            /* Marca del pago compartido de una referencia especial. */
            $table->integer('pago_no_empleado')->nullable();
        });

        Schema::create('c_estado_pago', function (Blueprint $table): void {
            $table->integer('espa_id_c_estado_pago')->primary();
            $table->string('esta_estado_pago', 15);
        });

        Schema::create('estado_pago', function (Blueprint $table): void {
            $table->increments('espa_id_estado_pago');
            $table->integer('espa_id_pago');
            $table->integer('espa_id_c_estado_pago');
            $table->date('espa_fecha')->nullable();
            $table->time('espa_hora');
            $table->text('espa_comentario')->nullable();
        });

        Schema::create('privilegio', function (Blueprint $table): void {
            $table->integer('priv_id_privilegio')->primary();
            $table->string('priv_privilegio', 45);
        });

        Schema::create('privilegio_rol', function (Blueprint $table): void {
            $table->increments('ropr_id_privilegio_rol');
            $table->integer('ropr_id_privilegio');
            $table->integer('ropr_id_rol');
        });
    }

    private function cargarDatos(): void
    {
        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Candidato'],
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Administrador'],
        ]);
        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => 'hash-persona'],
            ['usua_id_usuario' => 2, 'usua_id_rol' => 2, 'usua_clave_acceso' => 'hash-admin'],
        ]);
        DB::table('persona')->insert([
            [
                'pers_id_persona' => 1,
                'pers_clave_inegi' => '009',
                'pers_id_usuario' => 1,
                'pers_curp' => 'CAND900101MDFPRB01',
                'pers_nombre' => 'Ana',
                'pers_apellido_paterno' => 'Candidata',
                'pers_apellido_materno' => 'Prueba',
                'pers_fecha_registro' => '2026-08-01',
            ],
            [
                'pers_id_persona' => 2,
                'pers_clave_inegi' => '009',
                'pers_id_usuario' => 2,
                'pers_curp' => 'ADMN900101MDFPRB02',
                'pers_nombre' => 'Cuenta',
                'pers_apellido_paterno' => 'Administrativa',
                'pers_apellido_materno' => 'Prueba',
                'pers_fecha_registro' => '2026-08-01',
            ],
        ]);
        DB::table('entidad_federativa')->insert([
            ['enfe_clave_inegi' => '009', 'enfe_entidad_federativa' => 'Ciudad de México'],
        ]);
        DB::table('tipo_comunicacion')->insert([
            ['tico_id_tipo_comunicacion' => 1, 'tico_tipo_comunicacion' => 'Correo principal'],
            ['tico_id_tipo_comunicacion' => 4, 'tico_tipo_comunicacion' => 'Correo facturación'],
        ]);
        DB::table('comunicacion')->insert([
            ['comu_id_persona' => 1, 'comu_id_tipo_comunicacion' => 1, 'comu_descripcion' => 'ana@ejemplo.mx'],
        ]);
        DB::table('convocatoria')->insert([
            ['conv_id_convocatoria' => 1, 'conv_monto_recuperacion' => '$7,000.00'],
        ]);
        DB::table('solicitud')->insert([
            ['soli_id_solicitud' => 100, 'soli_id_persona' => 1, 'soli_id_convocatoria' => 1, 'soli_id_pago' => 1],
        ]);
        DB::table('referencia_bancaria')->insert([
            ['reba_id_pago' => 1, 'reba_referencia' => 'REF-100', 'reba_monto' => 7000],
        ]);
        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_estado_solicitud' => 'Aprobada'],
        ]);
        DB::table('estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_id_solicitud' => 100, 'esso_fecha' => '2026-08-01', 'esso_hora' => '10:00:00'],
        ]);
        /* Sólo un código sembrado, para que la prueba del alta signifique algo. */
        DB::table('codigo_postal')->insert([
            ['copo_id_codigo_postal' => '01000'],
        ]);
        DB::table('regimen_fiscal')->insert([
            ['refi_id_regimen_fiscal' => 1, 'refi_regimen_fiscal' => '601 - General de Ley P. Morales'],
            ['refi_id_regimen_fiscal' => 2, 'refi_regimen_fiscal' => '605 - Sueldos y Salarios'],
            ['refi_id_regimen_fiscal' => 3, 'refi_regimen_fiscal' => '612 - Personas Físicas Act. Emp.'],
            ['refi_id_regimen_fiscal' => 4, 'refi_regimen_fiscal' => '626 - RESICO'],
        ]);
        DB::table('pago')->insert([
            [
                'pago_id_pago' => 1,
                'pago_comprobante_path' => 'solicitudes/100/recibo.pdf',
                'pago_monto_pagado' => 7000,
                'pago_referencia_bancaria' => 'REF-100',
                'pago_fecha_pago' => '2026-08-02',
                'pago_hora_pago' => '10:30:00',
                'pago_uso_cfdi' => null,
                'pago_id_dato_fiscal' => null,
            ],
        ]);
        DB::table('c_estado_pago')->insert([
            ['espa_id_c_estado_pago' => 1, 'esta_estado_pago' => 'Pendiente'],
            ['espa_id_c_estado_pago' => 2, 'esta_estado_pago' => 'Completado'],
            ['espa_id_c_estado_pago' => 3, 'esta_estado_pago' => 'Declinado'],
        ]);
        DB::table('estado_pago')->insert([
            [
                'espa_id_pago' => 1,
                'espa_id_c_estado_pago' => 1,
                'espa_fecha' => '2026-08-02',
                'espa_hora' => '10:30:00',
                'espa_comentario' => null,
            ],
        ]);
        DB::table('privilegio')->insert([
            ['priv_id_privilegio' => 1, 'priv_privilegio' => 'Gestionar Pagos'],
        ]);
        DB::table('privilegio_rol')->insert([
            ['ropr_id_privilegio' => 1, 'ropr_id_rol' => 2],
        ]);
    }
}
