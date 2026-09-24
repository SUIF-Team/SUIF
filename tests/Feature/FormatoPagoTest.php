<?php

namespace Tests\Feature;

use App\Consultas\ConsultaPagos;
use App\Models\Usuario;
use App\Servicios\FormatoPagoDec;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * El formato de pago con que la DEC emite el CFDI o el ticket: cuándo se
 * ofrece, qué escribe en cada campo y a quién deja como responsable del pago.
 *
 * La descarga es un PDF, que no se puede leer de vuelta: lo que lleva cada
 * campo se comprueba en FormatoPagoDec::datos(), y la vista, con esos datos,
 * para la casilla marcada y el escapado.
 */
class FormatoPagoTest extends TestCase
{
    use SiembraAdministradores;

    /* La única casilla con «X» en la vista del formato. */
    private const MARCADA = '<div>X</div>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->crearEsquemaDeReportes();
        $this->sembrarRolesYPrivilegios();
        Storage::fake('comprobantes');

        $this->crearCuenta(1, self::ROL_PERSONA, 'CAND900101MDFPRB01', 'Ana', 'Candidata', 'Prueba');
        $this->crearCuenta(3, self::ROL_ADMIN_UIF, 'UIFA900101MDFABC03', 'Ulises', 'Registro', 'Prueba');
        $this->crearCuenta(4, self::ROL_ADMIN_DEC, 'DECA900101MDFABC04', 'Delia', 'Pagos', 'Prueba');

        $this->sembrarPago();
    }

    public function test_el_formato_no_se_ofrece_mientras_el_pago_sigue_en_revision(): void
    {
        $this->estadoPago('Pendiente');

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertDontSee('Generar comprobante');

        $this->actingAs(Usuario::findOrFail(4))
            ->post(route('admin.pagos.formato', 1), ['responsable' => 1])
            ->assertRedirect(route('admin.pagos.show', ['id' => 1]))
            ->assertSessionHas('warning', 'El formato se genera cuando el pago está validado.');

        $this->assertNull(DB::table('pago')->where('pago_id_pago', 1)->value('pago_id_responsable'));
    }

    public function test_un_cfdi_sin_datos_fiscales_todavia_no_se_puede_generar(): void
    {
        $this->estadoPago('Completado');
        DB::table('pago')->where('pago_id_pago', 1)->update(['pago_uso_cfdi' => true]);

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('Generar comprobante')
            ->assertDontSee('Descargar formato');

        $this->actingAs(Usuario::findOrFail(4))
            ->post(route('admin.pagos.formato', 1), ['responsable' => 1])
            ->assertRedirect(route('admin.pagos.show', ['id' => 1]))
            ->assertSessionHas('warning', 'La persona eligió CFDI y todavía no captura sus datos de facturación.');
    }

    public function test_el_ticket_llena_participante_evento_forma_de_pago_y_responsable(): void
    {
        $this->estadoPago('Completado');

        $respuesta = $this->actingAs(Usuario::findOrFail(4))
            ->post(route('admin.pagos.formato', 1), ['responsable' => 2])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="formato-pago-1234567890.pdf"')
            ->assertSessionHas('formato_pago.responsable', 2);

        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertSame(2, (int) DB::table('pago')->where('pago_id_pago', 1)->value('pago_id_responsable'));

        $datos = $this->datos('Tomás Ibarra Luna');

        $this->assertSame('SIN EFECTOS FISCALES', $datos['uso']);
        $this->assertSame('ANA CANDIDATA PRUEBA', $datos['participante']);
        $this->assertSame('CERTIFICACIÓN UIF 2026', $datos['evento']);
        $this->assertSame('$7,000.00', $datos['importe']);
        $this->assertSame('1234567890', $datos['referencia']);
        $this->assertSame('TOMÁS IBARRA LUNA', $datos['atendido_por']);

        /* Con ticket la sección de facturación sale con guiones, y con
           transferencia no hay banco que anotar. */
        foreach (['nombre_fiscal', 'rfc', 'codigo_postal', 'regimen', 'banco'] as $campo) {
            $this->assertSame('—', $datos[$campo], $campo);
        }

        $vista = $this->view('pdf.pago.formato', $datos)
            ->assertSeeInOrder(['TRANSFERENCIA', self::MARCADA], false);

        $this->assertSame(1, substr_count((string) $vista, self::MARCADA), 'Sólo una casilla puede ir marcada.');
    }

    public function test_el_cfdi_llena_la_seccion_de_facturacion_y_el_banco_de_la_tarjeta(): void
    {
        $this->estadoPago('Completado');
        $this->datosFiscales('Ana Candidata Prueba');
        DB::table('pago')->where('pago_id_pago', 1)->update([
            'pago_uso_cfdi' => true,
            'pago_id_metodo_pago' => 2,
            'pago_id_banco' => 1,
        ]);

        $datos = $this->datos('Rocío Durán Vega');

        $this->assertSame('ANA CANDIDATA PRUEBA', $datos['nombre_fiscal']);
        $this->assertSame('CAPA900101AB1', $datos['rfc']);
        $this->assertSame('GASTOS EN GENERAL', $datos['uso']);
        /* El código postal conserva el cero inicial. */
        $this->assertSame('01000', $datos['codigo_postal']);
        $this->assertSame('626 RÉGIMEN SIMPLIFICADO DE CONFIANZA', $datos['regimen']);
        $this->assertSame('BBVA', $datos['banco']);

        $vista = $this->view('pdf.pago.formato', $datos)
            ->assertSeeInOrder(['DÉBITO', self::MARCADA, 'TRANSFERENCIA'], false);

        $this->assertSame(1, substr_count((string) $vista, self::MARCADA), 'Sólo una casilla puede ir marcada.');
    }

    public function test_en_un_pago_grupal_el_participante_es_quien_paga(): void
    {
        $this->estadoPago('Completado');
        $this->datosFiscales('Grupo Ruiz & Asociados');
        DB::table('pago')->where('pago_id_pago', 1)->update([
            'pago_uso_cfdi' => true,
            'pago_no_empleado' => 3,
        ]);

        $datos = $this->datos('Rocío Durán Vega');

        $this->assertSame('GRUPO RUIZ & ASOCIADOS', $datos['participante']);
        $this->assertSame('GRUPO RUIZ & ASOCIADOS', $datos['nombre_fiscal']);

        /* assertSee escapa lo que busca: el & tiene que llegar como &amp;. */
        $this->view('pdf.pago.formato', $datos)->assertSee('GRUPO RUIZ & ASOCIADOS');
    }

    public function test_no_acepta_un_responsable_dado_de_baja(): void
    {
        $this->estadoPago('Completado');

        $this->actingAs(Usuario::findOrFail(4))
            ->from(route('admin.pagos.show', 1))
            ->post(route('admin.pagos.formato', 1), ['responsable' => 3])
            ->assertRedirect(route('admin.pagos.show', 1))
            ->assertSessionHasErrors('responsable');

        $this->assertNull(DB::table('pago')->where('pago_id_pago', 1)->value('pago_id_responsable'));
    }

    public function test_el_expediente_preselecciona_al_ultimo_responsable_elegido(): void
    {
        $this->estadoPago('Completado');

        $this->actingAs(Usuario::findOrFail(4))
            ->withSession(['formato_pago.responsable' => 2])
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('Generar comprobante')
            ->assertSee('<option value="2" selected>', false)
            /* Quien está dado de baja no se ofrece. */
            ->assertDontSee('Inactiva Baja Prueba');
    }

    public function test_generar_el_formato_exige_gestionar_pagos(): void
    {
        $this->estadoPago('Completado');

        $this->actingAs(Usuario::findOrFail(3))
            ->post(route('admin.pagos.formato', 1), ['responsable' => 1])
            ->assertForbidden();

        $this->assertNull(DB::table('pago')->where('pago_id_pago', 1)->value('pago_id_responsable'));
    }

    public function test_el_resultado_del_pago_aprobado_ofrece_generar_el_comprobante(): void
    {
        $this->estadoPago('Completado');

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.pagos.resultado', 1))
            ->assertOk()
            ->assertSeeInOrder(['PAGO APROBADO', 'Generar comprobante', 'Corregir la resolución'])
            ->assertSee('Descargar formato');
    }

    /* ── Apoyos ───────────────────────────────────────────────────────── */

    private function sembrarPago(): void
    {
        DB::table('convocatoria')->insert([
            'conv_id_convocatoria' => 1,
            'conv_fecha_inicio_registro' => '2026-08-01',
            'conv_fecha_fin' => '2026-12-31',
            'conv_nombre' => 'Certificación UIF 2026',
        ]);
        DB::table('solicitud')->insert([
            'soli_id_solicitud' => 100,
            'soli_id_persona' => 1,
            'soli_id_convocatoria' => 1,
            'soli_id_pago' => 1,
        ]);
        DB::table('c_estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => 1,
            'esso_estado_solicitud' => 'Aprobada',
        ]);
        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => 1,
            'esso_id_solicitud' => 100,
        ]);
        DB::table('metodo_pago')->insert([
            ['mepa_id_metodo_pago' => 1, 'mepa_metodo_pago' => 'Tarjeta de crédito'],
            ['mepa_id_metodo_pago' => 2, 'mepa_metodo_pago' => 'Tarjeta de débito'],
            ['mepa_id_metodo_pago' => 3, 'mepa_metodo_pago' => 'Depósito bancario'],
            ['mepa_id_metodo_pago' => 4, 'mepa_metodo_pago' => 'Transferencia'],
        ]);
        DB::table('banco')->insert([
            'banc_id_banco' => 1,
            'banc_banco' => 'BBVA',
        ]);
        DB::table('regimen_fiscal')->insert([
            'refi_id_regimen_fiscal' => 4,
            'refi_regimen_fiscal' => '626 RÉGIMEN SIMPLIFICADO DE CONFIANZA',
        ]);
        DB::table('c_estado_pago')->insert([
            ['espa_id_c_estado_pago' => 1, 'esta_estado_pago' => 'Pendiente'],
            ['espa_id_c_estado_pago' => 2, 'esta_estado_pago' => 'Completado'],
            ['espa_id_c_estado_pago' => 3, 'esta_estado_pago' => 'Declinado'],
        ]);
        DB::table('pago')->insert([
            'pago_id_pago' => 1,
            'pago_comprobante_path' => 'solicitudes/100/recibo.pdf',
            'pago_monto_pagado' => 7000,
            'pago_referencia_bancaria' => '1234567890',
            'pago_fecha_pago' => '2026-09-01',
            'pago_hora_pago' => '10:30:00',
            'pago_uso_cfdi' => false,
            'pago_id_metodo_pago' => 4,
        ]);
        DB::table('responsable')->insert([
            ['resp_id_responsable' => 1, 'resp_nombre' => 'Rocío', 'resp_apellido_paterno' => 'Durán', 'resp_apellido_materno' => 'Vega', 'resp_activo' => true],
            ['resp_id_responsable' => 2, 'resp_nombre' => 'Tomás', 'resp_apellido_paterno' => 'Ibarra', 'resp_apellido_materno' => 'Luna', 'resp_activo' => true],
            ['resp_id_responsable' => 3, 'resp_nombre' => 'Inactiva', 'resp_apellido_paterno' => 'Baja', 'resp_apellido_materno' => 'Prueba', 'resp_activo' => false],
        ]);
    }

    private function estadoPago(string $estado): void
    {
        DB::table('estado_pago')->insert([
            'espa_id_pago' => 1,
            'espa_id_c_estado_pago' => ['Pendiente' => 1, 'Completado' => 2, 'Declinado' => 3][$estado],
            'espa_fecha' => '2026-09-02',
            'espa_hora' => '09:00:00',
        ]);
    }

    private function datosFiscales(string $razon_social): void
    {
        DB::table('dato_fiscal')->insert([
            'dafi_id_dato_fiscal' => 1,
            'dafi_id_regimen_fiscal' => 4,
            'dafi_id_codigo_postal' => '01000',
            'dafi_razon_social' => $razon_social,
            'dafi_rfc' => 'CAPA900101AB1',
            'dafi_persona_moral' => false,
            'dafi_uso_cfdi' => true,
        ]);

        DB::table('pago')->where('pago_id_pago', 1)->update(['pago_id_dato_fiscal' => 1]);
    }

    /**
     * Lo que el formato escribe en cada campo para el pago sembrado, con el
     * pago tal como lo arma el expediente.
     *
     * @return array<string, string|null>
     */
    private function datos(string $atendido_por): array
    {
        return app(FormatoPagoDec::class)->datos(app(ConsultaPagos::class)->pago(1), $atendido_por);
    }
}
