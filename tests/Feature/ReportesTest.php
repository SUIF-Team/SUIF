<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\GestionSedes;
use App\Support\Admin\ConsultaPagos;
use App\Support\Admin\ConsultaPreRegistros;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * Los reportes descargables: quién puede bajar cada uno y qué trae dentro.
 *
 * La autorización se prueba por HTTP, porque es ahí donde vive; el contenido
 * se prueba llamando a los servicios de consulta, porque abrir el .xlsx para
 * contar renglones probaría a PhpSpreadsheet y no a SUIF. De la descarga sólo
 * se comprueba que sea un XLSX y que no se pueda cachear.
 */
class ReportesTest extends TestCase
{
    use SiembraAdministradores;

    private const ESTADO_PENDIENTE = 1;

    private const ESTADO_COMPLETADO = 2;

    private const ESTADO_DECLINADO = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->crearEsquemaDeReportes();
        $this->sembrarRolesYPrivilegios();

        $this->crearCuenta(1, self::ROL_PERSONA, 'PERS900101MDFABC01', 'Persona', 'Solicitante', 'Prueba');
        $this->crearCuenta(2, self::ROL_SUPERUSUARIO, 'SUPE900101MDFABC02', 'Sofía', 'Superusuaria', 'Prueba');
        $this->crearCuenta(3, self::ROL_ADMIN_UIF, 'UIFA900101MDFABC03', 'Ulises', 'Registro', 'Prueba');
        $this->crearCuenta(4, self::ROL_ADMIN_DEC, 'DECA900101MDFABC04', 'Delia', 'Pagos', 'Prueba');

        $this->sembrarTramite();
    }

    /* ── Autorización ─────────────────────────────────────────────────── */

    public function test_los_reportes_no_responden_sin_sesion(): void
    {
        foreach ($this->rutas() as $ruta) {
            $this->get($ruta)->assertRedirect(route('login'));
        }
    }

    public function test_una_persona_solicitante_no_abre_los_reportes(): void
    {
        $this->actingAs(Usuario::findOrFail(1));

        foreach ($this->rutas() as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
    }

    public function test_cada_area_solo_descarga_los_reportes_de_su_modulo(): void
    {
        /* La UIF valida registros: baja el padrón y nada más. */
        $this->actingAs(Usuario::findOrFail(3));
        $this->get(route('admin.reportes.registros'))->assertOk();
        $this->get(route('admin.reportes.pagos'))->assertForbidden();
        $this->get(route('admin.reportes.cfdi'))->assertForbidden();
        $this->get(route('admin.reportes.grupos', ['grupo' => 1]))->assertForbidden();

        /* La DEC resuelve pagos: baja lo que cobró y lo que factura. */
        $this->actingAs(Usuario::findOrFail(4));
        $this->get(route('admin.reportes.pagos'))->assertOk();
        $this->get(route('admin.reportes.cfdi'))->assertOk();
        $this->get(route('admin.reportes.registros'))->assertForbidden();
        $this->get(route('admin.reportes.grupos', ['grupo' => 1]))->assertForbidden();
    }

    public function test_el_indice_solo_pinta_las_tarjetas_que_se_pueden_descargar(): void
    {
        $this->actingAs(Usuario::findOrFail(3))
            ->get(route('admin.reportes.index'))
            ->assertOk()
            ->assertSee('Registros totales al sistema')
            ->assertDontSee('Referencias bancarias pagadas')
            ->assertDontSee('Solicitudes de CFDI')
            ->assertDontSee('Lista de asistencia por grupo');

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.reportes.index'))
            ->assertOk()
            ->assertSee('Referencias bancarias pagadas')
            ->assertSee('Solicitudes de CFDI')
            ->assertDontSee('Registros totales al sistema');
    }

    /**
     * El botón de regreso es un componente compartido y sus clases viven en la
     * hoja base de la zona administrativa. Sin ella el icono se dibujaba como
     * un triángulo negro que ocupaba media pantalla: el path se rellena por
     * omisión y el svg no trae medidas propias.
     */
    public function test_la_pantalla_carga_la_hoja_base_de_la_zona_administrativa(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.reportes.index'))
            ->assertOk()
            ->assertSee('assets/css/pages/admin-preregistro.css', false)
            ->assertSee('assets/css/pages/admin-reportes.css', false);
    }

    public function test_el_tablero_anuncia_los_reportes_a_quien_puede_abrirlos(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.reportes.index'));
    }

    /**
     * Un administrador que sólo gestiona convocatorias entra a la zona
     * administrativa, pero ninguno de los cuatro reportes es suyo: ni ve la
     * tarjeta en el tablero ni puede abrir la pantalla escribiendo la URL.
     */
    public function test_un_administrador_sin_reportes_no_ve_ni_abre_la_pantalla(): void
    {
        $this->crearRolDeConvocatorias();

        $this->actingAs(Usuario::findOrFail(10))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.reportes.index'));

        $this->actingAs(Usuario::findOrFail(10))
            ->get(route('admin.reportes.index'))
            ->assertForbidden();
    }

    /* ── Entrega del archivo ──────────────────────────────────────────── */

    public function test_los_reportes_se_entregan_como_xlsx_y_no_se_cachean(): void
    {
        $this->actingAs(Usuario::findOrFail(2));

        foreach ([
            route('admin.reportes.pagos'),
            route('admin.reportes.cfdi'),
            route('admin.reportes.registros'),
            route('admin.reportes.grupos', ['grupo' => 1]),
        ] as $ruta) {
            $respuesta = $this->get($ruta);

            $respuesta->assertOk();
            $respuesta->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
            $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
            /* Un XLSX es un ZIP: su firma son estos cuatro bytes. */
            $this->assertStringStartsWith("PK\x03\x04", $respuesta->getContent());

            /* El nombre viaja en Content-Disposition: sin saltos de línea ni
               comillas no hay forma de partir el encabezado en dos. */
            $disposicion = $respuesta->headers->get('Content-Disposition');
            $this->assertMatchesRegularExpression(
                '/^attachment; filename="[A-Za-z0-9 ._-]+\.xlsx"$/',
                $disposicion
            );
        }
    }

    public function test_las_dos_listas_del_grupo_comparten_nombre_y_no_arrastran_acentos(): void
    {
        $this->actingAs(Usuario::findOrFail(2));

        $base = 'lista-asistencia-centro-de-aplicacion-copilco-2026-11-20';

        /* La sede se llama «Centro de aplicación Copilco»: la acentuada se
           translitera, no se borra dejando un hueco. Y el Excel y el PDF se
           llaman igual, porque son el mismo documento en dos formatos. */
        $this->assertStringContainsString(
            $base.'.pdf',
            $this->get(route('admin.reportes.grupos.lista', ['grupo' => 1]))
                ->headers->get('Content-Disposition')
        );

        $this->assertStringContainsString(
            $base.'.xlsx',
            $this->get(route('admin.reportes.grupos', ['grupo' => 1]))
                ->headers->get('Content-Disposition')
        );
    }

    public function test_la_lista_de_firmas_se_entrega_en_pdf(): void
    {
        $respuesta = $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.reportes.grupos.lista', ['grupo' => 1]));

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_un_grupo_inexistente_o_ausente_responde_404(): void
    {
        $this->actingAs(Usuario::findOrFail(2));

        $this->get(route('admin.reportes.grupos', ['grupo' => 9999]))->assertNotFound();
        $this->get(route('admin.reportes.grupos'))->assertNotFound();
        $this->get(route('admin.reportes.grupos.lista', ['grupo' => 9999]))->assertNotFound();
    }

    /* ── Contenido ────────────────────────────────────────────────────── */

    public function test_el_reporte_de_pagos_solo_trae_los_validados(): void
    {
        $filas = app(ConsultaPagos::class)->pagadas();

        /* Los tres con último estado Completado. Quedan fuera el que sigue en
           revisión y el rechazado. Que aparezca quien pidió ticket es
           deliberado: este reporte es de dinero cobrado, no de facturación. */
        $this->assertEqualsCanonicalizing(
            ['PAGA900101MDFABC05', 'ZPAG900101MDFABC08', 'TICK900101MDFABC09'],
            array_column($filas, 'curp')
        );
    }

    public function test_el_reporte_de_pagos_distingue_lo_cobrado_de_lo_declarado(): void
    {
        $filas = array_column(app(ConsultaPagos::class)->pagadas(1), null, 'curp');
        $fila = $filas['PAGA900101MDFABC05'];

        $this->assertSame(7000.0, $fila['monto_cobrado']);
        $this->assertSame(6500.0, $fila['monto_declarado']);
        $this->assertSame('Convocatoria 2026', $fila['convocatoria']);
        $this->assertSame('2026-02-10', $fila['fecha_pago']);
        /* La fecha de validación es la del renglón Completado de la bitácora,
           no la que declaró la persona. */
        $this->assertSame('2026-02-12 10:15:00', $fila['fecha_validacion']);
        $this->assertSame('Centro de aplicación Copilco', $fila['sede']);
    }

    public function test_el_filtro_por_convocatoria_acota_el_reporte(): void
    {
        $consulta = app(ConsultaPagos::class);

        $this->assertCount(3, $consulta->pagadas());
        $this->assertCount(2, $consulta->pagadas(1));
        $this->assertCount(1, $consulta->pagadas(2));
    }

    public function test_el_reporte_de_cfdi_incluye_a_quien_no_ha_capturado_sus_datos(): void
    {
        $filas = app(ConsultaPagos::class)->solicitudesCfdi();

        $this->assertCount(2, $filas);

        $porCurp = array_column($filas, null, 'curp');

        $this->assertSame('Completa', $porCurp['PAGA900101MDFABC05']['captura']);
        $this->assertSame('CONSTRUCTORA DEL VALLE', $porCurp['PAGA900101MDFABC05']['razon_social']);
        $this->assertSame('CVA010203AB1', $porCurp['PAGA900101MDFABC05']['rfc_fiscal']);
        $this->assertSame('Moral', $porCurp['PAGA900101MDFABC05']['tipo_persona']);
        /* La columna es CHAR(5); el reporte no debe arrastrar el relleno. */
        $this->assertSame('06600', $porCurp['PAGA900101MDFABC05']['codigo_postal']);
        $this->assertSame('facturas@ejemplo.mx', $porCurp['PAGA900101MDFABC05']['correo_facturacion']);

        $this->assertSame('Pendiente de capturar', $porCurp['ZPAG900101MDFABC08']['captura']);
        $this->assertSame('', $porCurp['ZPAG900101MDFABC08']['rfc_fiscal']);
    }

    public function test_el_reporte_de_cfdi_deja_fuera_a_quien_pidio_ticket(): void
    {
        $curps = array_column(app(ConsultaPagos::class)->solicitudesCfdi(), 'curp');

        $this->assertNotContains('TICK900101MDFABC09', $curps);
    }

    public function test_los_registros_totales_cuentan_una_fila_por_solicitud(): void
    {
        $filas = app(ConsultaPreRegistros::class)->todasLasSolicitudes();

        /* Seis solicitudes de cinco personas: la primera se registró en las dos
           convocatorias y aparece dos veces, que es justo lo que significa
           "registros totales". */
        $this->assertCount(6, $filas);
        $this->assertSame(2, count(array_filter(
            $filas,
            fn (array $fila): bool => $fila['curp'] === 'PAGA900101MDFABC05'
        )));
    }

    public function test_los_registros_totales_no_llevan_datos_de_contacto(): void
    {
        $filas = app(ConsultaPreRegistros::class)->todasLasSolicitudes(1);

        $this->assertNotEmpty($filas);
        $this->assertSame(
            ['folio', 'curp', 'nombre_completo', 'rfc', 'entidad_federativa',
                'fecha_registro', 'convocatoria', 'sede', 'fecha_grupo', 'horario'],
            array_keys($filas[0])
        );
    }

    public function test_la_lista_de_grupo_trae_a_las_personas_citadas_en_orden(): void
    {
        $lista = app(GestionSedes::class)->listaDeGrupo(1);

        $this->assertSame('Centro de aplicación Copilco', $lista['grupo']['sede_nombre']);
        $this->assertCount(2, $lista['personas']);

        /* Ordenadas por apellido paterno, que es como se pasa lista. */
        $this->assertSame('1', $lista['personas'][0]['numero']);
        $this->assertSame('PAGA900101MDFABC05', $lista['personas'][0]['curp']);
        $this->assertSame('ZPAG900101MDFABC08', $lista['personas'][1]['curp']);
    }

    /* ── Siembra ──────────────────────────────────────────────────────── */

    /**
     * @return array<int, string>
     */
    private function rutas(): array
    {
        return [
            route('admin.reportes.index'),
            route('admin.reportes.pagos'),
            route('admin.reportes.cfdi'),
            route('admin.reportes.registros'),
            route('admin.reportes.grupos', ['grupo' => 1]),
            route('admin.reportes.grupos.lista', ['grupo' => 1]),
        ];
    }

    /**
     * Un rol administrativo que no toca ninguno de los cuatro reportes.
     *
     * El reparto del trait no trae ninguno así, y es justo el caso que separa
     * «entrar a /admin» de «poder descargar un reporte».
     */
    private function crearRolDeConvocatorias(): void
    {
        DB::table('rol')->insert(['rol_id_rol' => 5, 'rol_tipo_rol' => 'Admin Conv']);

        /* Privilegio 7 = Gestionar Convocatorias, según el reparto del trait. */
        DB::table('privilegio_rol')->insert(['ropr_id_privilegio' => 7, 'ropr_id_rol' => 5]);

        $this->crearCuenta(10, 5, 'CONV900101MDFABC10', 'Carmen', 'Ortega', 'Convocatorias');
    }

    /**
     * Un trámite mínimo con los casos que los reportes tienen que distinguir:
     * un pago validado con CFDI capturado, uno validado con CFDI pendiente,
     * uno en revisión, uno rechazado y uno que pidió ticket.
     */
    private function sembrarTramite(): void
    {
        DB::table('c_estado_pago')->insert([
            ['espa_id_c_estado_pago' => self::ESTADO_PENDIENTE, 'esta_estado_pago' => 'Pendiente'],
            ['espa_id_c_estado_pago' => self::ESTADO_COMPLETADO, 'esta_estado_pago' => 'Completado'],
            ['espa_id_c_estado_pago' => self::ESTADO_DECLINADO, 'esta_estado_pago' => 'Declinado'],
        ]);

        DB::table('convocatoria')->insert([
            [
                'conv_id_convocatoria' => 1,
                'conv_nombre' => 'Convocatoria 2026',
                'conv_fecha_inicio_registro' => '2026-01-01',
                'conv_fecha_fin' => '2026-12-31',
            ],
            [
                'conv_id_convocatoria' => 2,
                'conv_nombre' => 'Convocatoria 2025',
                'conv_fecha_inicio_registro' => '2025-01-01',
                'conv_fecha_fin' => '2025-12-31',
            ],
        ]);

        DB::table('sede')->insert([
            'sede_id_sede' => 1,
            'sede_nombre' => 'Centro de aplicación Copilco',
            'sede_direccion' => 'Av. Universidad 3000, Coyoacán',
            'sede_cupo' => 30,
            'sede_estado' => true,
        ]);

        DB::table('grupo')->insert([
            'grup_id_grupo' => 1,
            'sede_id_sede' => 1,
            'grup_fecha_inicio' => '2026-11-20',
            'grup_fecha_fin' => '2026-11-20',
            'grup_hora_inicio' => '09:00:00',
            'grup_hora_fin' => '13:00:00',
        ]);

        DB::table('evaluacion')->insert(['eval_id_evaluacion' => 1, 'grup_id_grupo' => 1]);

        DB::table('regimen_fiscal')->insert([
            'refi_id_regimen_fiscal' => 1,
            'refi_regimen_fiscal' => '601 - General de Ley P. Morales',
        ]);

        DB::table('codigo_postal')->insert(['copo_id_codigo_postal' => '06600']);

        DB::table('tipo_comunicacion')->insert([
            ['tico_id_tipo_comunicacion' => 1, 'tico_tipo_comunicacion' => 'Correo Electrónico'],
            ['tico_id_tipo_comunicacion' => 4, 'tico_tipo_comunicacion' => 'Correo facturación'],
        ]);

        DB::table('dato_fiscal')->insert([
            'dafi_id_dato_fiscal' => 1,
            'dafi_id_regimen_fiscal' => 1,
            'dafi_id_codigo_postal' => '06600',
            'dafi_razon_social' => 'CONSTRUCTORA DEL VALLE',
            'dafi_rfc' => 'CVA010203AB1',
            'dafi_persona_moral' => true,
            'dafi_uso_cfdi' => true,
        ]);

        $pagada = $this->crearCuenta(5, self::ROL_PERSONA, 'PAGA900101MDFABC05', 'Ana', 'Alvarez', 'Pagada');
        $revision = $this->crearCuenta(6, self::ROL_PERSONA, 'REVI900101MDFABC06', 'Beto', 'Barrios', 'Revisión');
        $rechazada = $this->crearCuenta(7, self::ROL_PERSONA, 'RECH900101MDFABC07', 'Carla', 'Cortés', 'Rechazada');
        $sinDatos = $this->crearCuenta(8, self::ROL_PERSONA, 'ZPAG900101MDFABC08', 'Diego', 'Zamora', 'Pendiente');
        $ticket = $this->crearCuenta(9, self::ROL_PERSONA, 'TICK900101MDFABC09', 'Elena', 'Estrada', 'Ticket');

        /* Pago validado con CFDI ya capturado, inscrito al grupo 1. */
        $this->crearPago(1, '1000000001', 7000, 6500, self::ESTADO_COMPLETADO, true, 1);
        $this->crearSolicitud($pagada, 1, 1, 1);
        /* La misma persona en la convocatoria anterior: sin pago, para que los
           registros totales tengan que contarla dos veces. */
        $this->crearSolicitud($pagada, 2, null, null);

        $this->crearPago(2, '1000000002', 7000, 7000, self::ESTADO_PENDIENTE, null, null);
        $this->crearSolicitud($revision, 1, 2, null);

        $this->crearPago(3, '1000000003', 7000, 100, self::ESTADO_DECLINADO, null, null);
        $this->crearSolicitud($rechazada, 1, 3, null);

        /* Validado y con CFDI elegido, pero sin datos fiscales capturados. */
        $this->crearPago(4, '1000000004', 7000, 7000, self::ESTADO_COMPLETADO, true, null);
        $this->crearSolicitud($sinDatos, 2, 4, 1);

        $this->crearPago(5, '1000000005', 7000, 7000, self::ESTADO_COMPLETADO, false, null);
        $this->crearSolicitud($ticket, 1, 5, null);

        DB::table('comunicacion')->insert([
            'comu_id_persona' => $this->personaDe($pagada),
            'comu_id_tipo_comunicacion' => 4,
            'comu_descripcion' => 'facturas@ejemplo.mx',
        ]);
    }

    private function crearPago(
        int $id,
        string $referencia,
        float $cobrado,
        float $declarado,
        int $estado,
        ?bool $usoCfdi,
        ?int $idDatoFiscal
    ): void {
        DB::table('pago')->insert([
            'pago_id_pago' => $id,
            /* consultaBase() sólo mira pagos con comprobante enviado. */
            'pago_comprobante_path' => 'comprobante-'.$id.'.pdf',
            'pago_monto_pagado' => $declarado,
            'pago_referencia_bancaria' => $referencia,
            'pago_fecha_pago' => '2026-02-10',
            'pago_hora_pago' => '11:30:00',
            'pago_uso_cfdi' => $usoCfdi,
            'pago_id_dato_fiscal' => $idDatoFiscal,
        ]);

        DB::table('referencia_bancaria')->insert([
            'reba_id_pago' => $id,
            'reba_referencia' => $referencia,
            'reba_monto' => $cobrado,
        ]);

        /* El estado vigente es el último renglón de la bitácora, no una
           columna: primero nace Pendiente y después se resuelve. */
        DB::table('estado_pago')->insert([
            'espa_id_pago' => $id,
            'espa_id_c_estado_pago' => self::ESTADO_PENDIENTE,
            'espa_fecha' => '2026-02-11',
            'espa_hora' => '09:00:00',
        ]);

        if ($estado !== self::ESTADO_PENDIENTE) {
            DB::table('estado_pago')->insert([
                'espa_id_pago' => $id,
                'espa_id_c_estado_pago' => $estado,
                'espa_fecha' => '2026-02-12',
                'espa_hora' => '10:15:00',
            ]);
        }
    }

    private function crearSolicitud(int $idUsuario, int $idConvocatoria, ?int $idPago, ?int $idEvaluacion): void
    {
        DB::table('solicitud')->insert([
            'soli_id_persona' => $this->personaDe($idUsuario),
            'soli_id_convocatoria' => $idConvocatoria,
            'soli_id_pago' => $idPago,
            'soli_id_evaluacion' => $idEvaluacion,
        ]);
    }

    /**
     * El id de persona no es el de usuario: se consulta en lugar de suponerlo.
     */
    private function personaDe(int $idUsuario): int
    {
        return (int) DB::table('persona')
            ->where('pers_id_usuario', $idUsuario)
            ->value('pers_id_persona');
    }
}
