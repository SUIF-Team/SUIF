<?php

namespace Tests\Feature;

use App\Support\Admin\RevisionDocumentos;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RevisionDocumentosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaTemporal();
        $this->cargarCatalogos();
    }

    public function test_aprobar_todos_crea_historial_documental_y_aprueba_la_solicitud(): void
    {
        $this->crearExpedienteEnRevision();

        $resultado = app(RevisionDocumentos::class)->resolver(1, [
            '10' => RevisionDocumentos::APROBADO,
            '11' => RevisionDocumentos::APROBADO,
        ], []);

        $this->assertSame(RevisionDocumentos::APROBADO, $resultado);
        $this->assertSame(2, DB::table('estado_documento')
            ->where('esdo_id_c_estado_documento', 4)
            ->count());
        $this->assertSame('Aprobada', $this->ultimoEstadoSolicitud());
    }

    public function test_rechazar_documento_guarda_comentario_y_conserva_solicitud_en_revision(): void
    {
        $this->crearExpedienteEnRevision();

        $resultado = app(RevisionDocumentos::class)->resolver(1, [
            '10' => RevisionDocumentos::APROBADO,
            '11' => RevisionDocumentos::RECHAZADO,
        ], ['11' => 'El archivo no es legible.'], '2026-08-20');

        $this->assertSame(RevisionDocumentos::REVISION, $resultado);
        $this->assertDatabaseHas('estado_documento', [
            'esdo_id_documento' => 11,
            'esdo_id_c_estado_documento' => 5,
            'esdo_comentarios' => "El archivo no es legible.\nFecha límite: 2026-08-20",
        ]);
        $this->assertSame('En revisión', $this->ultimoEstadoSolicitud());
    }

    public function test_cada_documento_rechazado_guarda_su_propio_comentario(): void
    {
        $this->crearExpedienteEnRevision();

        app(RevisionDocumentos::class)->resolver(1, [
            '10' => RevisionDocumentos::RECHAZADO,
            '11' => RevisionDocumentos::RECHAZADO,
        ], [
            '10' => 'Falta la firma autógrafa.',
            '11' => 'El archivo no es legible.',
        ], '2026-08-20');

        $this->assertDatabaseHas('estado_documento', [
            'esdo_id_documento' => 10,
            'esdo_comentarios' => "Falta la firma autógrafa.\nFecha límite: 2026-08-20",
        ]);
        $this->assertDatabaseHas('estado_documento', [
            'esdo_id_documento' => 11,
            'esdo_comentarios' => "El archivo no es legible.\nFecha límite: 2026-08-20",
        ]);
    }

    public function test_rechazar_documento_requiere_su_comentario(): void
    {
        $this->crearExpedienteEnRevision();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Escribe el motivo del rechazo de cada documento rechazado.');

        app(RevisionDocumentos::class)->resolver(1, [
            '10' => RevisionDocumentos::APROBADO,
            '11' => RevisionDocumentos::RECHAZADO,
        ], ['11' => '   '], '2026-08-20');
    }

    public function test_interrumpir_agrega_rechazo_al_historial_de_solicitud(): void
    {
        $this->crearExpedienteEnRevision();

        $resultado = app(RevisionDocumentos::class)->interrumpir(
            1,
            'La documentación presentada no corresponde a la persona solicitante.'
        );

        $this->assertSame(RevisionDocumentos::RECHAZADO, $resultado);
        $this->assertSame('Rechazada', $this->ultimoEstadoSolicitud());
        $this->assertDatabaseHas('estado_solicitud', [
            'esso_id_solicitud' => 1,
            'esso_motivo_rechazo' => 'La documentación presentada no corresponde a la persona solicitante.',
        ]);
    }

    public function test_rechazar_documento_requiere_fecha_limite(): void
    {
        $this->crearExpedienteEnRevision();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Indica una fecha límite válida.');

        app(RevisionDocumentos::class)->resolver(1, [
            '10' => RevisionDocumentos::APROBADO,
            '11' => RevisionDocumentos::RECHAZADO,
        ], ['11' => 'El archivo no es legible.']);
    }

    public function test_interrumpir_no_requiere_un_motivo_documental(): void
    {
        $this->crearExpedienteEnRevision();

        app(RevisionDocumentos::class)->interrumpir(1);

        $this->assertDatabaseHas('estado_solicitud', [
            'esso_id_solicitud' => 1,
            'esso_motivo_rechazo' => null,
        ]);
    }

    public function test_no_permite_resolver_dos_veces_la_misma_revision(): void
    {
        $this->crearExpedienteEnRevision();
        $revision = app(RevisionDocumentos::class);

        $revision->resolver(1, [
            '10' => RevisionDocumentos::APROBADO,
            '11' => RevisionDocumentos::APROBADO,
        ], []);

        $this->expectException(DomainException::class);
        $revision->resolver(1, [
            '10' => RevisionDocumentos::APROBADO,
            '11' => RevisionDocumentos::APROBADO,
        ], []);
    }

    private function crearEsquemaTemporal(): void
    {
        foreach ([
            'estado_documento',
            'documento',
            'c_estado_documento',
            'estado_solicitud',
            'solicitud',
            'c_estado_solicitud',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_solicitud')->primary();
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

        Schema::create('documento', function (Blueprint $table): void {
            $table->integer('docu_id_documento')->primary();
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
            $table->text('esdo_comentarios')->nullable();
            $table->date('esdo_fecha');
            $table->time('esdo_hora');
        });
    }

    private function cargarCatalogos(): void
    {
        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 3, 'esso_estado_solicitud' => 'En revisión'],
            ['esso_id_c_estado_solicitud' => 4, 'esso_estado_solicitud' => 'Aprobada'],
            ['esso_id_c_estado_solicitud' => 5, 'esso_estado_solicitud' => 'Rechazada'],
        ]);

        DB::table('c_estado_documento')->insert([
            ['esdo_id_c_estado_documento' => 3, 'esdo_estado_documento' => 'En revisión'],
            ['esdo_id_c_estado_documento' => 4, 'esdo_estado_documento' => 'Aprobado'],
            ['esdo_id_c_estado_documento' => 5, 'esdo_estado_documento' => 'Rechazado'],
        ]);
    }

    private function crearExpedienteEnRevision(): void
    {
        DB::table('solicitud')->insert(['soli_id_solicitud' => 1]);
        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => 3,
            'esso_id_solicitud' => 1,
            'esso_fecha' => '2026-08-05',
            'esso_hora' => '12:00:00',
            'esso_motivo_rechazo' => null,
        ]);

        DB::table('documento')->insert([
            ['docu_id_documento' => 10, 'soli_id_solicitud' => 1],
            ['docu_id_documento' => 11, 'soli_id_solicitud' => 1],
        ]);

        DB::table('estado_documento')->insert([
            [
                'esdo_id_c_estado_documento' => 3,
                'esdo_id_documento' => 10,
                'esdo_comentarios' => null,
                'esdo_fecha' => '2026-08-05',
                'esdo_hora' => '12:00:00',
            ],
            [
                'esdo_id_c_estado_documento' => 3,
                'esdo_id_documento' => 11,
                'esdo_comentarios' => null,
                'esdo_fecha' => '2026-08-05',
                'esdo_hora' => '12:00:00',
            ],
        ]);
    }

    private function ultimoEstadoSolicitud(): string
    {
        return DB::table('estado_solicitud as es')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('es.esso_id_solicitud', 1)
            ->orderByDesc('es.esso_id_estado_solicitud')
            ->value('ces.esso_estado_solicitud');
    }
}
