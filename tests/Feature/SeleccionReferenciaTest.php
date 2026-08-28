<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * SeleccionReferenciaTest
 *
 * El paso «Obtener referencia» ya no lleva directo a la entrega: arranca en un
 * selector que explica el pago y encamina al flujo individual o al especial.
 *
 * Lo que se cubre aquí es a quién deja pasar y a dónde manda, porque el
 * selector es la única puerta del paso: si volviera a preguntar a quien ya
 * tiene su referencia la dejaría sin su número y sin su formato, y si dejara
 * entrar a quien no tiene la solicitud aprobada mostraría un botón que la
 * asignación va a rechazar de todas formas.
 */
class SeleccionReferenciaTest extends TestCase
{
    use SiembraAdministradores;

    private const USUARIO = 1;

    private const SOLICITUD = 500;

    private const PAGO = 900;

    private const REFERENCIA = '4130326001856RJ30299';

    private const BOTON_INDIVIDUAL = 'Referencia Individual';

    private const BOTON_ESPECIAL = 'Referencia Especial';

    private const AVISO_ESPERA = 'Tu referencia estará disponible cuando el equipo administrativo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->completarEsquemaDeLaPersona();
        $this->sembrarRolesYPrivilegios();
        $this->crearCuenta(self::USUARIO, self::ROL_PERSONA, 'PERS900101MDFABC01', 'Persona', 'Solicitante', 'Prueba');
    }

    public function test_el_paso_arranca_en_el_selector_con_los_dos_caminos(): void
    {
        $this->sembrarSolicitud('Aprobada');

        $respuesta = $this->actingAs($this->persona())->get(route('persona.referencia.index'));

        $respuesta->assertOk();
        $respuesta->assertSee(self::BOTON_INDIVIDUAL);
        $respuesta->assertSee(self::BOTON_ESPECIAL);
        $respuesta->assertSee(route('persona.referencia.individual'));
    }

    public function test_el_camino_especial_todavia_no_es_navegable(): void
    {
        $this->sembrarSolicitud('Aprobada');

        $respuesta = $this->actingAs($this->persona())->get(route('persona.referencia.index'));

        /* La tarjeta se pinta completa para que se lea la descripción, pero su
           botón va inhabilitado: detrás no hay flujo todavía. */
        $respuesta->assertSee('referencia-boton--inhabilitado', false);
        $this->assertFalse(Route::has('persona.referencia.especial'));
    }

    public function test_sin_solicitud_aprobada_el_selector_no_ofrece_ningun_camino(): void
    {
        $this->sembrarSolicitud('En revisión');

        $respuesta = $this->actingAs($this->persona())->get(route('persona.referencia.index'));

        $respuesta->assertOk();
        $respuesta->assertSee(self::AVISO_ESPERA);
        $respuesta->assertDontSee(self::BOTON_INDIVIDUAL);
        $respuesta->assertDontSee(self::BOTON_ESPECIAL);
    }

    public function test_con_la_referencia_entregada_el_selector_manda_directo_al_camino_individual(): void
    {
        $this->sembrarSolicitud('Aprobada');
        $this->sembrarReferenciaEntregada();

        $this->actingAs($this->persona())
            ->get(route('persona.referencia.index'))
            ->assertRedirect(route('persona.referencia.individual'));
    }

    public function test_el_camino_individual_devuelve_al_selector_si_la_solicitud_no_esta_aprobada(): void
    {
        /* Entrar por la URL directa no puede saltarse la aprobación: el aviso
           de espera vive en el selector. */
        $this->sembrarSolicitud('En revisión');

        $this->actingAs($this->persona())
            ->get(route('persona.referencia.individual'))
            ->assertRedirect(route('persona.referencia.index'));
    }

    public function test_el_camino_individual_entrega_el_numero_y_el_formato(): void
    {
        Storage::fake('referencias');
        Storage::disk('referencias')->put('catalogo/'.self::REFERENCIA.'.pdf', '%PDF-1.4');

        $this->sembrarSolicitud('Aprobada');
        $this->sembrarReferenciaEntregada();

        $respuesta = $this->actingAs($this->persona())->get(route('persona.referencia.individual'));

        $respuesta->assertOk();
        $respuesta->assertSee(self::REFERENCIA);
        $respuesta->assertSee(route('persona.referencia.formato'));
    }

    public function test_el_motivo_de_una_asignacion_rechazada_llega_hasta_el_selector(): void
    {
        /* La aprobación se revocó entre que se pintó el botón y se pulsó: la
           asignación rebota al selector y el motivo no puede perderse en el
           camino, o la persona vuelve a intentarlo sin saber por qué falló. */
        $this->sembrarSolicitud('En revisión');

        $respuesta = $this->actingAs($this->persona())
            ->followingRedirects()
            ->post(route('persona.referencia.generar'));

        $respuesta->assertOk();
        $respuesta->assertSee('referencia-alerta--error', false);
    }

    private function persona(): Usuario
    {
        return Usuario::findOrFail(self::USUARIO);
    }

    /**
     * El trait trae el esqueleto administrativo. La pantalla de la persona
     * necesita además lo que AvancePersona consulta para pintar la barra de
     * avance y las columnas que CatalogoReferencias lee para armar la
     * referencia entregada.
     */
    private function completarEsquemaDeLaPersona(): void
    {
        Schema::table('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_evaluacion')->nullable();
        });

        Schema::table('pago', function (Blueprint $table): void {
            $table->string('pago_referencia_bancaria_path', 200)->nullable();
        });

        Schema::table('referencia_bancaria', function (Blueprint $table): void {
            $table->string('reba_path', 200)->nullable();
        });

        Schema::table('convocatoria', function (Blueprint $table): void {
            $table->string('conv_monto_recuperacion', 20)->nullable();
        });

        foreach (['estado_documento', 'c_estado_documento', 'documento', 'tipo_documento'] as $tabla) {
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
    }

    private function sembrarSolicitud(string $estado): void
    {
        DB::table('solicitud')->insert([
            'soli_id_solicitud' => self::SOLICITUD,
            'soli_id_persona' => DB::table('persona')->where('pers_id_usuario', self::USUARIO)->value('pers_id_persona'),
            'soli_id_convocatoria' => null,
            'soli_id_pago' => null,
        ]);

        DB::table('c_estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => 1,
            'esso_estado_solicitud' => $estado,
        ]);

        DB::table('estado_solicitud')->insert([
            'esso_id_estado_solicitud' => 1,
            'esso_id_c_estado_solicitud' => 1,
            'esso_id_solicitud' => self::SOLICITUD,
        ]);
    }

    private function sembrarReferenciaEntregada(): void
    {
        $ruta = 'catalogo/'.self::REFERENCIA.'.pdf';

        DB::table('pago')->insert([
            'pago_id_pago' => self::PAGO,
            'pago_referencia_bancaria' => self::REFERENCIA,
            'pago_referencia_bancaria_path' => $ruta,
            'pago_monto_pagado' => 7000,
        ]);

        DB::table('referencia_bancaria')->insert([
            'reba_id_referencia_bancaria' => 1,
            'reba_id_pago' => self::PAGO,
            'reba_referencia' => self::REFERENCIA,
            'reba_path' => $ruta,
            'reba_monto' => 7000,
            'reba_vigencia' => '2026-12-31',
        ]);

        DB::table('solicitud')
            ->where('soli_id_solicitud', self::SOLICITUD)
            ->update(['soli_id_pago' => self::PAGO]);
    }
}
