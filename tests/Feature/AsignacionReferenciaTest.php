<?php

namespace Tests\Feature;

use App\Servicios\CatalogoReferencias;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AsignacionReferenciaTest
 *
 * El catálogo se carga en dos pasos —el CSV con las referencias y el ZIP con
 * sus formatos PDF— y nada obliga a que ocurran los dos. Estas pruebas cubren
 * que una referencia sin su PDF no se entregue: la entrega no tiene vuelta
 * atrás, así que dársela a una persona la dejaría con un número y sin con qué
 * pagar en ventanilla, y la referencia ya no se podría recuperar.
 */
class AsignacionReferenciaTest extends TestCase
{
    /** La persona que pide su referencia. */
    private const USUARIO = 10;

    private const SOLICITUD = 500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquema();
        $this->sembrarSolicitudAprobada();
    }

    public function test_no_entrega_una_referencia_que_no_tiene_su_formato_pdf(): void
    {
        $this->sembrarReferencia('4130326001856RJ30299', null);

        try {
            $this->catalogo()->asignar(self::USUARIO);
            $this->fail('Se esperaba que la referencia sin formato no se entregara.');
        } catch (DomainException $excepcion) {
            $this->assertStringContainsString('formato de pago', $excepcion->getMessage());
        }

        /* Nada a medias: ni PAGO, ni la referencia ligada, ni la solicitud. */
        $this->assertSame(0, DB::table('pago')->count());
        $this->assertNull(
            DB::table('referencia_bancaria')->where('reba_referencia', '4130326001856RJ30299')->value('reba_id_pago')
        );
        $this->assertNull(
            DB::table('solicitud')->where('soli_id_solicitud', self::SOLICITUD)->value('soli_id_pago')
        );
    }

    public function test_una_ruta_vacia_cuenta_igual_que_no_tener_formato(): void
    {
        /* Cadena vacía en vez de NULL: la columna se puede quedar así y un
           `IS NOT NULL` a secas la daría por buena. */
        $this->sembrarReferencia('4130326001856RJ30299', '');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('formato de pago');

        $this->catalogo()->asignar(self::USUARIO);
    }

    public function test_salta_la_referencia_sin_formato_y_entrega_la_que_si_lo_tiene(): void
    {
        /* La de menor id no tiene PDF: el orden de entrega no puede mandar
           sobre la condición o se repartiría la incompleta. */
        $this->sembrarReferencia('4130326001856RJ30299', null);
        $this->sembrarReferencia('4130326001857RJ30210', 'catalogo/4130326001857RJ30210.pdf');

        $resultado = $this->catalogo()->asignar(self::USUARIO);

        $this->assertSame('4130326001857RJ30210', $resultado['referencia']);

        $this->assertNull(
            DB::table('referencia_bancaria')->where('reba_referencia', '4130326001856RJ30299')->value('reba_id_pago')
        );
        $this->assertSame(
            $resultado['id_pago'],
            (int) DB::table('referencia_bancaria')->where('reba_referencia', '4130326001857RJ30210')->value('reba_id_pago')
        );
        $this->assertSame(
            $resultado['id_pago'],
            (int) DB::table('solicitud')->where('soli_id_solicitud', self::SOLICITUD)->value('soli_id_pago')
        );

        /* El PDF viaja al PAGO: es de ahí de donde la persona lo descarga. */
        $this->assertSame(
            'catalogo/4130326001857RJ30210.pdf',
            DB::table('pago')->where('pago_id_pago', $resultado['id_pago'])->value('pago_referencia_bancaria_path')
        );
    }

    public function test_con_el_catalogo_vacio_el_mensaje_sigue_siendo_el_de_siempre(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No hay referencias bancarias disponibles');

        $this->catalogo()->asignar(self::USUARIO);
    }

    public function test_el_resumen_cuenta_aparte_las_que_de_verdad_se_pueden_entregar(): void
    {
        $this->sembrarReferencia('4130326001856RJ30299', null);
        $this->sembrarReferencia('4130326001857RJ30210', '');
        $this->sembrarReferencia('4130326001858RJ30221', 'catalogo/4130326001858RJ30221.pdf');
        $this->sembrarReferencia('4130326001859RJ30235', 'catalogo/4130326001859RJ30235.pdf', 77);

        $resumen = $this->catalogo()->resumen();

        $this->assertSame(4, $resumen['total']);
        $this->assertSame(3, $resumen['disponibles']);
        $this->assertSame(1, $resumen['asignadas']);
        $this->assertSame(2, $resumen['con_formato']);
        $this->assertSame(1, $resumen['entregables']);
    }

    private function catalogo(): CatalogoReferencias
    {
        return app(CatalogoReferencias::class);
    }

    /**
     * Sólo las tablas que toca asignar(). No se reutiliza el esquema de
     * Tests\Concerns\SiembraAdministradores porque su REFERENCIA_BANCARIA no
     * tiene REBA_PATH, que es justo la columna que aquí se prueba.
     */
    private function crearEsquema(): void
    {
        foreach ([
            'referencia_bancaria', 'pago', 'solicitud', 'persona',
            'convocatoria', 'estado_solicitud', 'c_estado_solicitud',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

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

        Schema::create('pago', function (Blueprint $table): void {
            $table->increments('pago_id_pago');
            $table->string('pago_referencia_bancaria', 20)->nullable();
            $table->string('pago_referencia_bancaria_path', 200)->nullable();
            $table->decimal('pago_monto_pagado', 10, 4)->nullable();
            $table->boolean('pago_uso_cfdi')->nullable();
            $table->integer('pago_id_dato_fiscal')->nullable();
        });

        Schema::create('solicitud', function (Blueprint $table): void {
            $table->increments('soli_id_solicitud');
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_convocatoria')->nullable();
            $table->integer('soli_id_pago')->nullable();
        });

        Schema::create('persona', function (Blueprint $table): void {
            $table->increments('pers_id_persona');
            $table->integer('pers_id_usuario')->nullable();
        });

        Schema::create('convocatoria', function (Blueprint $table): void {
            $table->increments('conv_id_convocatoria');
            $table->string('conv_monto_recuperacion', 20)->nullable();
        });

        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_c_estado_solicitud');
            $table->string('esso_estado_solicitud', 40);
        });

        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_estado_solicitud');
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
            $table->string('esso_motivo_rechazo', 255)->nullable();
        });
    }

    /** Una persona con solicitud aprobada: lo único que asignar() exige antes. */
    private function sembrarSolicitudAprobada(): void
    {
        DB::table('persona')->insert(['pers_id_persona' => 1, 'pers_id_usuario' => self::USUARIO]);
        DB::table('convocatoria')->insert(['conv_id_convocatoria' => 1, 'conv_monto_recuperacion' => '$7,000.00']);
        DB::table('solicitud')->insert([
            'soli_id_solicitud' => self::SOLICITUD,
            'soli_id_persona' => 1,
            'soli_id_convocatoria' => 1,
            'soli_id_pago' => null,
        ]);
        DB::table('c_estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => 1,
            'esso_estado_solicitud' => 'Aprobada',
        ]);
        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => 1,
            'esso_id_solicitud' => self::SOLICITUD,
        ]);
    }

    private function sembrarReferencia(string $referencia, ?string $ruta, ?int $id_pago = null): void
    {
        DB::table('referencia_bancaria')->insert([
            'reba_id_pago' => $id_pago,
            'reba_referencia' => $referencia,
            'reba_path' => $ruta,
            'reba_monto' => 100,
            'reba_vigencia' => '2026-09-20',
            'reba_fecha_emision' => '2026-08-20',
            'reba_fecha_carga' => '2026-08-20',
            'reba_hora_carga' => '10:00:00',
        ]);
    }
}
