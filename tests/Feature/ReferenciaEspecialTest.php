<?php

namespace Tests\Feature;

use App\Mail\ReferenciaEspecialEmitida;
use App\Models\Usuario;
use App\Servicios\ComprobanteFiscal;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * ReferenciaEspecialTest
 *
 * El camino especial reparte dinero entre varias personas a la vez: una sola
 * referencia liga a N solicitudes al mismo PAGO y con un solo comprobante se
 * dan todas por pagadas. Por eso lo que se cubre aquí es sobre todo a quién se
 * deja entrar en la lista —la CURP tiene que existir, estar aprobada y no
 * tener ya referencia— y que la emisión no reparta dos veces el mismo número.
 */
class ReferenciaEspecialTest extends TestCase
{
    use SiembraAdministradores;

    private const SOLICITANTE = 1;

    private const COMPANERA = 2;

    private const YA_PAGADA = 3;

    private const ADMIN_DEC = 10;

    private const ADMIN_UIF = 11;

    private const CURP_SOLICITANTE = 'HEGA000222HDFXYZ01';

    private const CURP_COMPANERA = 'HEJP890615HDFABC02';

    private const CURP_YA_PAGADA = 'MERI941130MDFDEF03';

    private const CONVOCATORIA = 1;

    private const REFERENCIA_GRUPAL = '4130323000146BK40259';

    private const REFERENCIA_INDIVIDUAL = '4130323000146BK40260';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->completarEsquema();
        $this->sembrarRolesYPrivilegios();

        $this->crearCuenta(self::SOLICITANTE, self::ROL_PERSONA, self::CURP_SOLICITANTE, 'Héctor', 'Gómez', 'Álvarez');
        $this->crearCuenta(self::COMPANERA, self::ROL_PERSONA, self::CURP_COMPANERA, 'Pablo', 'Hernández', 'Juárez');
        $this->crearCuenta(self::YA_PAGADA, self::ROL_PERSONA, self::CURP_YA_PAGADA, 'Ignacio', 'Merino', 'Rodríguez');
        $this->crearCuenta(self::ADMIN_DEC, self::ROL_ADMIN_DEC, 'ADEC900101HDFXYZ10', 'Ana', 'Dirección', 'Cobros');
        $this->crearCuenta(self::ADMIN_UIF, self::ROL_ADMIN_UIF, 'AUIF900101HDFXYZ11', 'Uriel', 'Unidad', 'Registro');

        DB::table('convocatoria')->insert([
            'conv_id_convocatoria' => self::CONVOCATORIA,
            'conv_fecha_inicio_registro' => '2026-01-01',
            'conv_fecha_fin' => '2026-12-31',
            'conv_monto_recuperacion' => '$7,000.00',
        ]);

        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_estado_solicitud' => 'Aprobada'],
            ['esso_id_c_estado_solicitud' => 2, 'esso_estado_solicitud' => 'En revisión'],
        ]);

        DB::table('regimen_fiscal')->insert([
            'refi_id_regimen_fiscal' => 1,
            'refi_regimen_fiscal' => '601 General de Ley Personas Morales',
        ]);

        DB::table('tipo_comunicacion')->insert([
            ['tico_id_tipo_comunicacion' => 1, 'tico_tipo_comunicacion' => 'Correo principal'],
            /* El correo del CFDI es otro tipo de comunicación: lo siembra
               suif_comprobante_fiscal.sql y sin él no se puede pedir la
               referencia especial. */
            ['tico_id_tipo_comunicacion' => 4, 'tico_tipo_comunicacion' => 'Correo facturación'],
        ]);

        $this->sembrarSolicitud(self::SOLICITANTE, 'Aprobada');
        $this->sembrarSolicitud(self::COMPANERA, 'Aprobada');
        $this->sembrarSolicitud(self::YA_PAGADA, 'Aprobada');
    }

    public function test_la_pantalla_precarga_a_quien_pide_la_referencia(): void
    {
        $respuesta = $this->actingAs($this->usuario(self::SOLICITANTE))
            ->get(route('persona.referencia.especial'));

        $respuesta->assertOk();
        $respuesta->assertSee(self::CURP_SOLICITANTE);
        $respuesta->assertSee(route('persona.referencia.especial.store'));
    }

    public function test_la_solicitud_cuelga_a_todos_del_mismo_pago(): void
    {
        $respuesta = $this->actingAs($this->usuario(self::SOLICITANTE))
            ->post(route('persona.referencia.especial.store'), $this->formulario());

        $respuesta->assertRedirect(route('persona.referencia.individual'));

        $pago = DB::table('pago')->first();

        $this->assertNotNull($pago);
        $this->assertSame(2, (int) $pago->pago_no_empleado);
        /* Dos participantes por la cuota de la convocatoria. */
        $this->assertEqualsWithDelta(14000, (float) $pago->pago_monto_pagado, 0.001);
        /* Nace sin referencia: la emite la DEC. */
        $this->assertSame('', trim((string) $pago->pago_referencia_bancaria));

        $this->assertSame(
            2,
            DB::table('solicitud')->where('soli_id_pago', $pago->pago_id_pago)->count()
        );

        $fiscal = DB::table('dato_fiscal')->first();

        $this->assertSame('Empresa de Ejemplo S.A.', $fiscal->dafi_razon_social);
        $this->assertSame((int) $fiscal->dafi_id_dato_fiscal, (int) $pago->pago_id_dato_fiscal);
    }

    /**
     * El CFDI se le manda al pagador, pero COMUNICACION cuelga de PERSONA. El
     * correo se guarda en la persona de la solicitud más antigua del pago, que
     * es por la que ConsultaPagos::solicitudesCfdi() representa al grupo: si
     * alguien cambia esa regla de representante, esta prueba truena antes de que
     * la DEC descubra en el cierre de mes que le faltan correos.
     */
    public function test_el_correo_del_cfdi_queda_en_la_solicitud_representante(): void
    {
        $id_pago = $this->solicitar();

        $representante = (int) DB::table('solicitud')
            ->where('soli_id_pago', $id_pago)
            ->min('soli_id_solicitud');

        $correos = DB::table('comunicacion')
            ->where('comu_id_tipo_comunicacion', 4)
            ->get();

        /* Un renglón por pago y no uno por participante: los demás conservan el
           correo de facturación que traigan de otra convocatoria. */
        $this->assertCount(1, $correos);
        $this->assertSame('facturas@empresa.mx', $correos[0]->comu_descripcion);
        $this->assertSame(
            (int) DB::table('solicitud')->where('soli_id_solicitud', $representante)->value('soli_id_persona'),
            (int) $correos[0]->comu_id_persona
        );
    }

    public function test_sin_correo_de_facturacion_no_se_registra_la_solicitud(): void
    {
        $datos = $this->formulario();
        unset($datos['correo_cfdi']);

        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->post(route('persona.referencia.especial.store'), $datos)
            ->assertSessionHasErrors('correo_cfdi');

        $this->assertSame(0, DB::table('pago')->count());
    }

    public function test_el_correo_del_cfdi_se_guarda_en_minusculas(): void
    {
        $datos = $this->formulario();
        $datos['correo_cfdi'] = 'Facturas@Empresa.MX';

        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->post(route('persona.referencia.especial.store'), $datos);

        $this->assertSame(
            'facturas@empresa.mx',
            DB::table('comunicacion')->where('comu_id_tipo_comunicacion', 4)->value('comu_descripcion')
        );
    }

    public function test_el_autollenado_devuelve_el_nombre_registrado(): void
    {
        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->getJson(route('persona.referencia.especial.persona', ['curp' => self::CURP_COMPANERA]))
            ->assertOk()
            ->assertJson([
                'encontrada' => true,
                'persona' => [
                    'curp' => self::CURP_COMPANERA,
                    'nombre' => 'Pablo',
                    'primer_apellido' => 'Hernández',
                    'segundo_apellido' => 'Juárez',
                ],
            ]);
    }

    /**
     * El motivo del rechazo es el trámite de otra persona: al teclear sólo se
     * dice que no aplica. El motivo exacto sigue apareciendo al enviar, cuando
     * quien lo lee responde por la lista completa.
     */
    public function test_el_autollenado_calla_el_motivo_del_rechazo(): void
    {
        /* Una CURP que no existe y una que ni siquiera tiene forma de CURP. */
        foreach (['XXXX000000XXXXXX00', 'no-es-una-curp'] as $curp) {
            $this->actingAs($this->usuario(self::SOLICITANTE))
                ->getJson(route('persona.referencia.especial.persona', ['curp' => $curp]))
                ->assertOk()
                ->assertExactJson(['encontrada' => false]);
        }

        /* Y una que existe pero ya tiene su referencia: se calla igual, sin
           decir en qué punto del trámite va esa persona. */
        $id_pago = DB::table('pago')->insertGetId([
            'pago_referencia_bancaria' => self::REFERENCIA_INDIVIDUAL,
            'pago_referencia_bancaria_path' => 'catalogo/individual.pdf',
            'pago_monto_pagado' => 7000,
        ], 'pago_id_pago');

        DB::table('solicitud')
            ->where('soli_id_persona', $this->idPersona(self::COMPANERA))
            ->update(['soli_id_pago' => $id_pago]);

        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->getJson(route('persona.referencia.especial.persona', ['curp' => self::CURP_COMPANERA]))
            ->assertOk()
            ->assertExactJson(['encontrada' => false]);
    }

    /**
     * Quien ya tiene su referencia no está armando ninguna lista, así que
     * tampoco tiene por qué preguntar nombres.
     */
    public function test_el_autollenado_se_cierra_con_la_referencia_ya_asignada(): void
    {
        $id_pago = DB::table('pago')->insertGetId([
            'pago_referencia_bancaria' => self::REFERENCIA_INDIVIDUAL,
            'pago_referencia_bancaria_path' => 'catalogo/individual.pdf',
            'pago_monto_pagado' => 7000,
        ], 'pago_id_pago');

        DB::table('solicitud')
            ->where('soli_id_persona', $this->idPersona(self::SOLICITANTE))
            ->update(['soli_id_pago' => $id_pago]);

        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->getJson(route('persona.referencia.especial.persona', ['curp' => self::CURP_COMPANERA]))
            ->assertOk()
            ->assertExactJson(['encontrada' => false]);
    }

    public function test_el_autollenado_exige_sesion(): void
    {
        $this->getJson(route('persona.referencia.especial.persona', ['curp' => self::CURP_COMPANERA]))
            ->assertUnauthorized();
    }

    /**
     * Quien paga por varios siempre pide CFDI: la factura se emite a nombre del
     * pagador. La elección nace hecha para que ninguno de los participantes
     * pueda entrar después al paso del comprobante y cambiarla por un ticket,
     * que es definitivo y dejaría a la empresa sin su factura.
     */
    public function test_el_pago_compartido_nace_con_el_cfdi_elegido(): void
    {
        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->post(route('persona.referencia.especial.store'), $this->formulario());

        $pago = DB::table('pago')->first();

        $this->assertTrue(ComprobanteFiscal::normalizarUsoCfdi($pago->pago_uso_cfdi));
        $this->assertSame(
            ComprobanteFiscal::CFDI,
            ComprobanteFiscal::tipoDesdeUsoCfdi($pago->pago_uso_cfdi)
        );
    }

    public function test_los_acentos_no_impiden_reconocer_a_un_participante(): void
    {
        /* En la base es «Hernández» y aquí se teclea «Hernandez»: es la misma
           persona y no puede rebotar por un acento. */
        $datos = $this->formulario();
        $datos['participantes'][1]['primer_apellido'] = 'Hernandez';
        $datos['participantes'][1]['segundo_apellido'] = 'Juarez';

        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->post(route('persona.referencia.especial.store'), $datos)
            ->assertRedirect(route('persona.referencia.individual'));

        $this->assertSame(1, DB::table('pago')->count());
    }

    public function test_una_curp_que_no_existe_no_se_puede_incluir(): void
    {
        $datos = $this->formulario();
        $datos['participantes'][1]['curp'] = 'XXXX000000XXXXXX00';

        $this->enviarYEsperarRechazo($datos, 'no tiene una solicitud registrada');
    }

    public function test_una_curp_sin_solicitud_aprobada_no_se_puede_incluir(): void
    {
        $this->cambiarEstado(self::COMPANERA, 'En revisión');

        $this->enviarYEsperarRechazo($this->formulario(), 'todavía no tiene su solicitud aprobada');
    }

    public function test_una_curp_con_referencia_asignada_no_se_puede_incluir(): void
    {
        $id_pago = DB::table('pago')->insertGetId([
            'pago_referencia_bancaria' => self::REFERENCIA_INDIVIDUAL,
            'pago_referencia_bancaria_path' => 'catalogo/individual.pdf',
            'pago_monto_pagado' => 7000,
        ], 'pago_id_pago');

        DB::table('solicitud')
            ->where('soli_id_persona', $this->idPersona(self::COMPANERA))
            ->update(['soli_id_pago' => $id_pago]);

        $this->enviarYEsperarRechazo($this->formulario(), 'ya tiene una referencia bancaria asignada');
    }

    public function test_una_curp_repetida_se_rechaza(): void
    {
        $datos = $this->formulario();
        $datos['participantes'][1] = $datos['participantes'][0];

        $this->enviarYEsperarRechazo($datos, 'repetida');
    }

    public function test_un_nombre_que_no_corresponde_se_rechaza(): void
    {
        $datos = $this->formulario();
        $datos['participantes'][1]['nombre'] = 'Otro';

        $this->enviarYEsperarRechazo($datos, 'no coincide');
    }

    public function test_quien_solicita_tiene_que_ir_en_la_lista(): void
    {
        $datos = $this->formulario();
        $datos['participantes'][0] = [
            'curp' => self::CURP_YA_PAGADA,
            'nombre' => 'Ignacio',
            'primer_apellido' => 'Merino',
            'segundo_apellido' => 'Rodríguez',
        ];

        $this->enviarYEsperarRechazo($datos, 'Tu CURP tiene que estar');
    }

    public function test_una_sola_persona_corresponde_al_camino_individual(): void
    {
        $datos = $this->formulario();
        $datos['participantes'] = [$datos['participantes'][0]];

        $this->enviarYEsperarRechazo($datos, 'al menos');
    }

    public function test_con_la_referencia_pendiente_el_pago_sigue_cerrado(): void
    {
        $this->solicitar();

        $referencia = $this->actingAs($this->usuario(self::COMPANERA))
            ->get(route('persona.referencia.individual'));

        $referencia->assertOk();
        $referencia->assertSee('en trámite');
        $referencia->assertSee('Empresa de Ejemplo S.A.');

        $pago = $this->actingAs($this->usuario(self::COMPANERA))->get(route('persona.pago.index'));

        $pago->assertOk();
        $pago->assertSee('todavía no ha sido emitida');
    }

    public function test_la_dec_emite_la_referencia_y_avisa_a_los_participantes(): void
    {
        Mail::fake();
        Storage::fake('referencias');
        Storage::disk('referencias')->put('catalogo/'.self::REFERENCIA_GRUPAL.'.pdf', '%PDF-1.4');

        $this->sembrarCorreos();
        $id_pago = $this->solicitar();
        $id_referencia = $this->sembrarCatalogo();

        $this->actingAs($this->usuario(self::ADMIN_DEC))
            ->post(route('admin.referencias.especiales.emitir', ['id' => $id_pago]), [
                'referencia' => $id_referencia,
            ])
            ->assertRedirect(route('admin.referencias.especiales.index'));

        $pago = DB::table('pago')->where('pago_id_pago', $id_pago)->first();

        $this->assertSame(self::REFERENCIA_GRUPAL, $pago->pago_referencia_bancaria);
        $this->assertSame(
            $id_pago,
            (int) DB::table('referencia_bancaria')->where('reba_id_referencia_bancaria', $id_referencia)->value('reba_id_pago')
        );

        Mail::assertSent(ReferenciaEspecialEmitida::class, 2);

        /* Ya emitida, los dos participantes ven el mismo número. */
        $this->actingAs($this->usuario(self::COMPANERA))
            ->get(route('persona.referencia.individual'))
            ->assertSee(self::REFERENCIA_GRUPAL);
    }

    public function test_la_misma_referencia_no_se_entrega_dos_veces(): void
    {
        Mail::fake();
        Storage::fake('referencias');
        Storage::disk('referencias')->put('catalogo/'.self::REFERENCIA_GRUPAL.'.pdf', '%PDF-1.4');

        $id_pago = $this->solicitar();
        $id_referencia = $this->sembrarCatalogo();

        $this->actingAs($this->usuario(self::ADMIN_DEC))
            ->post(route('admin.referencias.especiales.emitir', ['id' => $id_pago]), ['referencia' => $id_referencia]);

        /* El segundo intento no encuentra nada pendiente y rebota con aviso. */
        $this->actingAs($this->usuario(self::ADMIN_DEC))
            ->post(route('admin.referencias.especiales.emitir', ['id' => $id_pago]), ['referencia' => $id_referencia])
            ->assertSessionHas('warning');

        $this->assertSame(1, DB::table('referencia_bancaria')->whereNotNull('reba_id_pago')->count());
    }

    public function test_solo_se_ofrecen_las_referencias_por_el_importe_del_grupo(): void
    {
        Storage::fake('referencias');
        Storage::disk('referencias')->put('catalogo/'.self::REFERENCIA_GRUPAL.'.pdf', '%PDF-1.4');

        $id_pago = $this->solicitar();
        $this->sembrarCatalogo();

        /* Una referencia individual no cubre a dos participantes. */
        DB::table('referencia_bancaria')->insert([
            'reba_referencia' => self::REFERENCIA_INDIVIDUAL,
            'reba_path' => 'catalogo/'.self::REFERENCIA_INDIVIDUAL.'.pdf',
            'reba_monto' => 7000,
        ]);

        $respuesta = $this->actingAs($this->usuario(self::ADMIN_DEC))
            ->get(route('admin.referencias.especiales.show', ['id' => $id_pago]));

        $respuesta->assertOk();
        $respuesta->assertSee(self::REFERENCIA_GRUPAL);
        $respuesta->assertDontSee(self::REFERENCIA_INDIVIDUAL);
    }

    public function test_la_bandeja_es_de_quien_gestiona_referencias(): void
    {
        $this->actingAs($this->usuario(self::ADMIN_DEC))
            ->get(route('admin.referencias.especiales.index'))
            ->assertOk();

        $this->actingAs($this->usuario(self::ADMIN_UIF))
            ->get(route('admin.referencias.especiales.index'))
            ->assertForbidden();
    }

    /**
     * Envía el formulario y comprueba que rebotó sin escribir nada.
     *
     * @param array<string, mixed> $datos
     */
    private function enviarYEsperarRechazo(array $datos, string $fragmento): void
    {
        $respuesta = $this->actingAs($this->usuario(self::SOLICITANTE))
            ->post(route('persona.referencia.especial.store'), $datos);

        $respuesta->assertSessionHasErrors('participantes');

        $this->assertStringContainsString(
            $fragmento,
            implode(' ', session('errors')->get('participantes'))
        );

        $this->assertSame(0, DB::table('pago')->where('pago_no_empleado', '>', 0)->count());
        $this->assertSame(0, DB::table('dato_fiscal')->count());
    }

    /**
     * Deja registrada la solicitud del camino feliz y devuelve el pago creado.
     */
    private function solicitar(): int
    {
        $this->actingAs($this->usuario(self::SOLICITANTE))
            ->post(route('persona.referencia.especial.store'), $this->formulario());

        return (int) DB::table('pago')->whereNotNull('pago_no_empleado')->value('pago_id_pago');
    }

    private function sembrarCatalogo(): int
    {
        return (int) DB::table('referencia_bancaria')->insertGetId([
            'reba_referencia' => self::REFERENCIA_GRUPAL,
            'reba_path' => 'catalogo/'.self::REFERENCIA_GRUPAL.'.pdf',
            'reba_monto' => 14000,
            'reba_vigencia' => '2026-12-31',
        ], 'reba_id_referencia_bancaria');
    }

    private function sembrarCorreos(): void
    {
        foreach ([self::SOLICITANTE, self::COMPANERA] as $usuario) {
            DB::table('comunicacion')->insert([
                'comu_id_persona' => $this->idPersona($usuario),
                'comu_id_tipo_comunicacion' => 1,
                'comu_descripcion' => 'persona'.$usuario.'@ejemplo.mx',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formulario(): array
    {
        return [
            'razon_social' => 'Empresa de Ejemplo S.A.',
            'persona_moral' => '1',
            'regimen_fiscal' => 1,
            'codigo_postal' => '01000',
            'rfc' => 'EEJ860101AB1',
            'correo_cfdi' => 'facturas@empresa.mx',
            'participantes' => [
                [
                    'curp' => self::CURP_SOLICITANTE,
                    'nombre' => 'Héctor',
                    'primer_apellido' => 'Gómez',
                    'segundo_apellido' => 'Álvarez',
                ],
                [
                    'curp' => self::CURP_COMPANERA,
                    'nombre' => 'Pablo',
                    'primer_apellido' => 'Hernández',
                    'segundo_apellido' => 'Juárez',
                ],
            ],
        ];
    }

    private function usuario(int $id): Usuario
    {
        return Usuario::findOrFail($id);
    }

    private function idPersona(int $id_usuario): int
    {
        return (int) DB::table('persona')->where('pers_id_usuario', $id_usuario)->value('pers_id_persona');
    }

    private function sembrarSolicitud(int $id_usuario, string $estado): void
    {
        $id_solicitud = DB::table('solicitud')->insertGetId([
            'soli_id_persona' => $this->idPersona($id_usuario),
            'soli_id_convocatoria' => self::CONVOCATORIA,
            'soli_id_pago' => null,
        ], 'soli_id_solicitud');

        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => $estado === 'Aprobada' ? 1 : 2,
            'esso_id_solicitud' => $id_solicitud,
        ]);
    }

    private function cambiarEstado(int $id_usuario, string $estado): void
    {
        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => $estado === 'Aprobada' ? 1 : 2,
            'esso_id_solicitud' => DB::table('solicitud')
                ->where('soli_id_persona', $this->idPersona($id_usuario))
                ->value('soli_id_solicitud'),
        ]);
    }

    /**
     * El trait trae el esqueleto administrativo. Falta lo que tocan el avance
     * de la persona, el catálogo de referencias y los datos fiscales.
     */
    private function completarEsquema(): void
    {
        Schema::table('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_evaluacion')->nullable();
        });

        Schema::table('pago', function (Blueprint $table): void {
            $table->string('pago_referencia_bancaria_path', 200)->nullable();
        });

        /* REBA_FECHA_ASIGNACION ya la crea el esquema común: los reportes
           también la necesitan. Repetirla aquí revienta la tabla. */
        Schema::table('referencia_bancaria', function (Blueprint $table): void {
            $table->string('reba_path', 200)->nullable();
            $table->time('reba_hora_asignacion')->nullable();
        });

        foreach ([
            'estado_documento', 'c_estado_documento', 'documento', 'tipo_documento',
            'dato_fiscal', 'regimen_fiscal', 'codigo_postal', 'comunicacion', 'tipo_comunicacion',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        /* Van vacías: el avance sólo necesita poder consultarlas. */
        Schema::create('tipo_documento', function (Blueprint $table): void {
            $table->increments('tido_id_tipo_documento');
            $table->string('tido_tipo_documento', 60);
        });

        Schema::create('documento', function (Blueprint $table): void {
            $table->increments('docu_id_documento');
            $table->integer('tido_id_tipo_documento');
            $table->integer('soli_id_solicitud');
        });

        Schema::create('c_estado_documento', function (Blueprint $table): void {
            $table->increments('esdo_id_c_estado_documento');
            $table->string('esdo_estado_documento', 20);
        });

        Schema::create('estado_documento', function (Blueprint $table): void {
            $table->increments('esdo_id_estado_documento');
            $table->integer('esdo_id_documento');
            $table->integer('esdo_id_c_estado_documento');
        });

        Schema::create('codigo_postal', function (Blueprint $table): void {
            $table->string('copo_id_codigo_postal', 5)->primary();
        });

        Schema::create('regimen_fiscal', function (Blueprint $table): void {
            $table->increments('refi_id_regimen_fiscal');
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

        Schema::create('tipo_comunicacion', function (Blueprint $table): void {
            $table->increments('tico_id_tipo_comunicacion');
            $table->string('tico_tipo_comunicacion', 25);
        });

        Schema::create('comunicacion', function (Blueprint $table): void {
            $table->increments('comu_id_comunicacion');
            $table->integer('comu_id_persona');
            $table->integer('comu_id_tipo_comunicacion');
            $table->string('comu_descripcion', 65);
        });
    }
}
