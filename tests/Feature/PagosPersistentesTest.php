<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\Admin\ConsultaPagos;
use App\Support\Admin\RevisionPagos;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagosPersistentesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaTemporal();
        $this->cargarDatos();
        Storage::fake('comprobantes');
        Storage::disk('comprobantes')->put('solicitudes/100/recibo.pdf', 'PDF de prueba');
    }

    public function test_bandeja_muestra_solicitudes_de_personas_y_excluye_roles_administrativos(): void
    {
        $pagos = app(ConsultaPagos::class)->bandeja();

        $this->assertCount(1, $pagos);
        $this->assertSame('1', $pagos[0]['id']);
        $this->assertSame('Candidata Prueba Ana', $pagos[0]['nombre_completo']);
        $this->assertSame('Por revisar', $pagos[0]['estatus']);
        $this->assertTrue($pagos[0]['puede_revisarse']);
    }

    public function test_rechazo_agrega_historial_con_motivo_y_actualiza_la_pantalla_de_la_persona(): void
    {
        app(RevisionPagos::class)->rechazar(1, 'El comprobante está incompleto y no es legible.');

        $this->assertDatabaseHas('estado_pago', [
            'espa_id_pago' => 1,
            'espa_id_c_estado_pago' => 3,
            'espa_comentario' => 'El comprobante está incompleto y no es legible.',
        ]);

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('persona.pago.index'))
            ->assertOk()
            ->assertSee('El comprobante está incompleto y no es legible.');
    }

    public function test_rechazo_requiere_motivo_y_no_resuelve_el_pago(): void
    {
        $admin = Usuario::findOrFail(2);

        $this->actingAs($admin)
            ->from(route('admin.pagos.show', 1))
            ->post(route('admin.pagos.rechazar', 1), [])
            ->assertRedirect(route('admin.pagos.show', 1))
            ->assertSessionHasErrors('motivo_rechazo');

        $this->assertSame('Pendiente', $this->ultimoEstadoPago(1));
    }

    public function test_subsanar_pago_rechazado_reemplaza_el_archivo_y_reabre_la_revision(): void
    {
        app(RevisionPagos::class)->rechazar(1, 'El archivo no corresponde al pago.');

        app(RevisionPagos::class)->registrarComprobanteDePersona(
            1,
            'solicitudes/100/comprobante-corregido.pdf',
            ['monto_pagado' => '7000.00', 'fecha_pago' => '2026-08-03', 'hora_pago' => '11:45']
        );

        $this->assertDatabaseHas('pago', [
            'pago_id_pago' => 1,
            'pago_comprobante_path' => 'solicitudes/100/comprobante-corregido.pdf',
        ]);
        $this->assertSame('Pendiente', $this->ultimoEstadoPago(1));
        $this->assertDatabaseHas('estado_pago', [
            'espa_id_pago' => 1,
            'espa_id_c_estado_pago' => 1,
            'espa_comentario' => null,
        ]);
    }

    public function test_la_persona_captura_monto_fecha_y_hora_al_subir_su_comprobante(): void
    {
        app(RevisionPagos::class)->rechazar(1, 'El archivo no corresponde al pago.');

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('persona.pago.comprobante'), [
                'comprobante' => UploadedFile::fake()->create('pago.pdf', 40, 'application/pdf'),
                'monto_pagado' => '6850.50',
                'fecha_pago' => '2026-08-03',
                'hora_pago' => '11:45',
            ])
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHas('success');

        /* Los segundos los completa el servicio: PostgreSQL los rellenaría solo
           al guardar en TIME, pero SQLite guarda la cadena tal cual. */
        $this->assertDatabaseHas('pago', [
            'pago_id_pago' => 1,
            'pago_monto_pagado' => 6850.5,
            'pago_fecha_pago' => '2026-08-03',
            'pago_hora_pago' => '11:45:00',
        ]);
        $this->assertSame('Pendiente', $this->ultimoEstadoPago(1));
    }

    public function test_el_comprobante_se_rechaza_sin_los_datos_del_pago_o_con_una_fecha_futura(): void
    {
        app(RevisionPagos::class)->rechazar(1, 'El archivo no corresponde al pago.');

        $this->actingAs(Usuario::findOrFail(1))
            ->from(route('persona.pago.index'))
            ->post(route('persona.pago.comprobante'), [
                'comprobante' => UploadedFile::fake()->create('pago.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHasErrors(['monto_pagado', 'fecha_pago', 'hora_pago']);

        $this->actingAs(Usuario::findOrFail(1))
            ->from(route('persona.pago.index'))
            ->post(route('persona.pago.comprobante'), [
                'comprobante' => UploadedFile::fake()->create('pago.pdf', 40, 'application/pdf'),
                'monto_pagado' => '7000.00',
                'fecha_pago' => Carbon::now()->addDay()->toDateString(),
                'hora_pago' => '11:45',
            ])
            ->assertRedirect(route('persona.pago.index'))
            ->assertSessionHasErrors('fecha_pago');

        /* Nada alcanzó a escribirse: sigue el comprobante rechazado. */
        $this->assertDatabaseHas('pago', [
            'pago_id_pago' => 1,
            'pago_comprobante_path' => 'solicitudes/100/recibo.pdf',
        ]);
        $this->assertSame('Declinado', $this->ultimoEstadoPago(1));
    }

    public function test_rutas_administrativas_requieren_el_privilegio_de_gestionar_pagos(): void
    {
        $this->get(route('admin.pagos.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(Usuario::findOrFail(3))
            ->get(route('admin.pagos.index'))
            ->assertForbidden();

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.pagos.index'))
            ->assertOk()
            ->assertSee('Candidata Prueba Ana');
    }

    public function test_comprobante_se_entrega_desde_el_disco_privado_y_no_expone_otro_pago(): void
    {
        $admin = Usuario::findOrFail(2);

        $respuesta = $this->actingAs($admin)
            ->get(route('admin.pagos.comprobante', 1))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('private', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));

        $this->actingAs($admin)
            ->get(route('admin.pagos.comprobante', 2))
            ->assertNotFound();
    }

    public function test_pago_resuelto_permanece_consultable_sin_acciones(): void
    {
        app(RevisionPagos::class)->aprobar(1);

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('Aprobado')
            ->assertDontSee('Validar pago')
            ->assertDontSee('Rechazar pago');
    }

    public function test_pago_resuelto_ofrece_reanudar_la_revision(): void
    {
        app(RevisionPagos::class)->aprobar(1);

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('Reanudar revisión del pago');
    }

    public function test_reanudar_un_pago_aprobado_lo_regresa_a_revision(): void
    {
        app(RevisionPagos::class)->aprobar(1);
        app(RevisionPagos::class)->reanudar(1);

        $this->assertSame('Pendiente', $this->ultimoEstadoPago(1));

        /* La bitácora conserva la aprobación: reanudar agrega un renglón, no
           borra el anterior. */
        $this->assertDatabaseHas('estado_pago', [
            'espa_id_pago' => 1,
            'espa_id_c_estado_pago' => 2,
        ]);

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.pagos.show', 1))
            ->assertOk()
            ->assertSee('Validar pago')
            ->assertSee('Rechazar pago');
    }

    public function test_reanudar_un_pago_rechazado_lo_devuelve_a_la_bandeja_de_revision(): void
    {
        app(RevisionPagos::class)->rechazar(1, 'El comprobante no es legible.');

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.pagos.reanudar', 1))
            ->assertRedirect(route('admin.pagos.show', ['id' => 1]))
            ->assertSessionHas('success');

        $this->assertSame('Pendiente', $this->ultimoEstadoPago(1));
    }

    public function test_no_se_puede_reanudar_un_pago_que_sigue_pendiente(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El pago aún no ha sido resuelto');

        app(RevisionPagos::class)->reanudar(1);
    }

    public function test_reanudar_un_pago_exige_el_privilegio_de_gestionar_pagos(): void
    {
        app(RevisionPagos::class)->aprobar(1);

        /* Revertir una resolución le toca a quien la dictó, y el dinero lo
           resuelve la DEC: reanudar un pago se rige por el mismo privilegio
           que revisarlo. Ni el Auditor ni la persona lo tienen. */
        $this->actingAs(Usuario::findOrFail(3))
            ->post(route('admin.pagos.reanudar', 1))
            ->assertForbidden();

        $this->actingAs(Usuario::findOrFail(1))
            ->post(route('admin.pagos.reanudar', 1))
            ->assertForbidden();

        $this->assertSame('Completado', $this->ultimoEstadoPago(1));
    }

    private function crearEsquemaTemporal(): void
    {
        foreach ([
            'privilegio_rol',
            'privilegio',
            'estado_pago',
            'c_estado_pago',
            'referencia_bancaria',
            'convocatoria',
            'comunicacion',
            'tipo_comunicacion',
            'pago',
            'dato_fiscal',
            'regimen_fiscal',
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

        Schema::create('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_solicitud')->primary();
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_convocatoria')->nullable();
            $table->integer('soli_id_pago')->nullable();
            $table->integer('soli_id_evaluacion')->nullable();
        });

        /* La pantalla de pago muestra la cuota que hay que pagar, y ésa sale
           del catálogo de referencias con respaldo en la convocatoria. */
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

        /* El detalle del pago muestra el comprobante que la persona pidió y,
           si fue CFDI, los datos con los que se le factura. */
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
            $table->string('priv_privilegio', 35);
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
            ['rol_id_rol' => 3, 'rol_tipo_rol' => 'Auditor'],
        ]);
        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => 'hash-persona'],
            ['usua_id_usuario' => 2, 'usua_id_rol' => 2, 'usua_clave_acceso' => 'hash-admin'],
            ['usua_id_usuario' => 3, 'usua_id_rol' => 3, 'usua_clave_acceso' => 'hash-auditor'],
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
        DB::table('convocatoria')->insert([
            ['conv_id_convocatoria' => 1, 'conv_monto_recuperacion' => '$7,000.00'],
        ]);
        DB::table('solicitud')->insert([
            ['soli_id_solicitud' => 100, 'soli_id_persona' => 1, 'soli_id_convocatoria' => 1, 'soli_id_pago' => 1],
            ['soli_id_solicitud' => 200, 'soli_id_persona' => 2, 'soli_id_convocatoria' => 1, 'soli_id_pago' => 2],
        ]);
        DB::table('referencia_bancaria')->insert([
            ['reba_id_pago' => 1, 'reba_referencia' => 'REF-100', 'reba_monto' => 7000],
            ['reba_id_pago' => 2, 'reba_referencia' => 'REF-200', 'reba_monto' => 7000],
        ]);
        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_estado_solicitud' => 'Aprobada'],
        ]);
        DB::table('estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_id_solicitud' => 100, 'esso_fecha' => '2026-08-01', 'esso_hora' => '10:00:00'],
            ['esso_id_c_estado_solicitud' => 1, 'esso_id_solicitud' => 200, 'esso_fecha' => '2026-08-01', 'esso_hora' => '10:00:00'],
        ]);
        DB::table('pago')->insert([
            [
                'pago_id_pago' => 1,
                'pago_comprobante_path' => 'solicitudes/100/recibo.pdf',
                'pago_monto_pagado' => 7000,
                'pago_referencia_bancaria' => 'REF-100',
                'pago_fecha_pago' => '2026-08-02',
                'pago_hora_pago' => '10:30:00',
            ],
            [
                'pago_id_pago' => 2,
                'pago_comprobante_path' => 'solicitudes/200/admin.pdf',
                'pago_monto_pagado' => 7000,
                'pago_referencia_bancaria' => 'REF-200',
                'pago_fecha_pago' => '2026-08-02',
                'pago_hora_pago' => '10:30:00',
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
            [
                'espa_id_pago' => 2,
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

    private function ultimoEstadoPago(int $id_pago): string
    {
        return DB::table('estado_pago as ep')
            ->join('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->where('ep.espa_id_pago', $id_pago)
            ->orderByDesc('ep.espa_id_estado_pago')
            ->value('cep.esta_estado_pago');
    }
}
