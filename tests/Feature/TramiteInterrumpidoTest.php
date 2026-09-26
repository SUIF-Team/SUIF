<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * TramiteInterrumpidoTest
 *
 * Interrumpir un trámite lo cierra mientras sus documentos siguen «En
 * revisión»: sin una pantalla propia, la persona seguiría viendo la tabla de
 * carga y creería que todavía puede subsanar algo que ya nadie va a revisar.
 *
 * Lo que se cubre aquí es que esa pantalla gane sobre la tabla y que el motivo
 * capturado por el administrador llegue hasta ella, incluso cuando no existe:
 * los trámites cerrados antes de que se capturara el motivo tienen la columna
 * en nulo y no pueden prometer una explicación que no está.
 *
 * También se cubre que el trámite cerrado no se reabra desde la cuenta de la
 * persona: esconder los botones no basta si la ruta sigue aceptando el POST.
 * Hay dos caminos y los dos se prueban —reenviar documentos que quedaron «En
 * revisión» y reemplazar uno que ya estaba rechazado—, junto con la
 * subsanación normal, que no debe romperse.
 */
class TramiteInterrumpidoTest extends TestCase
{
    use SiembraAdministradores;

    private const USUARIO = 1;

    private const SOLICITUD = 500;

    private const MOTIVO = 'La documentación presentada no corresponde a la persona solicitante.';

    private const TITULO = 'Trámite interrumpido';

    private const TABLA = 'Documentación requerida';

    private const SIN_MOTIVO = 'No se registró un comentario adicional';

    private const CERRADA = 'Tu trámite ya no admite cambios en la documentación.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->completarEsquemaDeLaPersona();
        $this->sembrarRolesYPrivilegios();
        $this->crearCuenta(self::USUARIO, self::ROL_PERSONA, 'PERS900101MDFABC01', 'Persona', 'Solicitante', 'Prueba');
    }

    public function test_la_persona_ve_el_motivo_cuando_su_tramite_fue_interrumpido(): void
    {
        $this->sembrarSolicitud('Rechazada', self::MOTIVO);

        $respuesta = $this->actingAs($this->persona())->get(route('persona.documentos.index'));

        $respuesta->assertOk();
        $respuesta->assertSee(self::TITULO);
        $respuesta->assertSee(self::MOTIVO);
        $respuesta->assertDontSee(self::TABLA);
    }

    public function test_un_cierre_sin_motivo_no_promete_una_explicacion_que_no_existe(): void
    {
        /* Los trámites cerrados antes de que se capturara el motivo tienen la
           columna en nulo: en vez de una caja «Motivo» vacía va el aviso. */
        $this->sembrarSolicitud('Rechazada', null);

        $respuesta = $this->actingAs($this->persona())->get(route('persona.documentos.index'));

        $respuesta->assertOk();
        $respuesta->assertSee(self::TITULO);
        $respuesta->assertSee(self::SIN_MOTIVO);
        $respuesta->assertDontSee('<strong>Motivo</strong>', false);
    }

    public function test_una_solicitud_viva_sigue_viendo_la_tabla_de_documentos(): void
    {
        $this->sembrarSolicitud('En revisión', null);

        $respuesta = $this->actingAs($this->persona())->get(route('persona.documentos.index'));

        $respuesta->assertOk();
        $respuesta->assertSee(self::TABLA);
        $respuesta->assertDontSee(self::TITULO);
    }

    public function test_una_solicitud_interrumpida_no_se_puede_reenviar_a_revision(): void
    {
        /* Se interrumpió a medio dictamen: los documentos siguen «En revisión». */
        $this->sembrarExpediente('Rechazada', []);

        $this->actingAs($this->persona())
            ->postJson(route('persona.preregistro.documentos.enviar'))
            ->assertStatus(422)
            ->assertJsonPath('mensaje', self::CERRADA);

        $this->assertSame('Rechazada', $this->estadoVigente());
    }

    public function test_una_solicitud_interrumpida_no_acepta_documentos(): void
    {
        /* Se interrumpió después de un dictamen con rechazos: el documento
           rechazado sería reemplazable y luego reenviable. */
        Storage::fake('local');
        $this->sembrarExpediente('Rechazada', ['curp' => 'Rechazado']);
        $estados_antes = DB::table('estado_documento')->count();

        $this->actingAs($this->persona())
            ->postJson(route('persona.preregistro.documentos.store', 'curp'), [
                'archivo' => UploadedFile::fake()->create('curp.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', self::CERRADA);

        $this->assertSame($estados_antes, DB::table('estado_documento')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_una_solicitud_en_revision_sigue_admitiendo_la_subsanacion(): void
    {
        Storage::fake('local');
        $this->sembrarExpediente('En revisión', ['curp' => 'Rechazado']);

        $this->actingAs($this->persona())
            ->postJson(route('persona.preregistro.documentos.store', 'curp'), [
                'archivo' => UploadedFile::fake()->create('curp.pdf', 10, 'application/pdf'),
            ])
            ->assertOk();

        $this->actingAs($this->persona())
            ->postJson(route('persona.preregistro.documentos.enviar'))
            ->assertOk();

        $this->assertSame('En revisión', $this->estadoVigente());
    }

    public function test_el_envio_sigue_exigiendo_todos_los_documentos(): void
    {
        /* Control: la comprobación previa del controlador se conserva y da su
           propio mensaje antes de llegar al servicio. */
        $this->sembrarExpediente('Pre-registro', []);
        DB::table('documento')->where('docu_nombre', 'curp.pdf')->delete();

        $this->actingAs($this->persona())
            ->postJson(route('persona.preregistro.documentos.enviar'))
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Debes cargar todos los documentos antes de continuar.');
    }

    private function persona(): Usuario
    {
        return Usuario::findOrFail(self::USUARIO);
    }

    /**
     * El trait trae el esqueleto administrativo. La pantalla de documentación
     * consulta además las tablas documentales: van vacías porque lo que se
     * prueba es qué bloque gana, no el dictamen de cada archivo.
     */
    private function completarEsquemaDeLaPersona(): void
    {
        Schema::table('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_evaluacion')->nullable();
        });

        /* Las escrituras de la persona (carga y envío) llenan la fecha y la
           hora; las pruebas de pantalla insertan sin ellas. */
        Schema::table('estado_solicitud', function (Blueprint $table): void {
            $table->date('esso_fecha')->nullable();
            $table->time('esso_hora')->nullable();
        });

        foreach (['estado_documento', 'c_estado_documento', 'documento', 'tipo_documento'] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('tipo_documento', function (Blueprint $table): void {
            $table->increments('tido_id_tipo_documento');
            $table->string('tido_tipo_documento', 60);
        });

        Schema::create('documento', function (Blueprint $table): void {
            $table->increments('docu_id_documento');
            $table->integer('tido_id_tipo_documento');
            $table->integer('soli_id_solicitud');
            $table->string('docu_path', 200)->nullable();
            $table->string('docu_nombre', 200)->nullable();
            $table->date('docu_fecha_carga')->nullable();
            $table->time('docu_hora_carga')->nullable();
            $table->date('docu_fecha_autorizacion')->nullable();
            $table->time('docu_hora_autorizacion')->nullable();
        });

        Schema::create('c_estado_documento', function (Blueprint $table): void {
            $table->increments('esdo_id_c_estado_documento');
            $table->string('esdo_estado_documento', 20);
        });

        Schema::create('estado_documento', function (Blueprint $table): void {
            $table->increments('esdo_id_estado_documento');
            $table->integer('esdo_id_documento');
            $table->integer('esdo_id_c_estado_documento');
            $table->text('esdo_comentarios')->nullable();
            $table->date('esdo_fecha')->nullable();
            $table->time('esdo_hora')->nullable();
        });
    }

    private function sembrarSolicitud(string $estado, ?string $motivo): void
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
            'esso_motivo_rechazo' => $motivo,
        ]);
    }

    /**
     * Solicitud con los seis documentos del catálogo, cada uno con el estado
     * vigente indicado (los que no se indican quedan «En revisión»). Los
     * catálogos llevan los identificadores de suif_catalogos.sql.
     *
     * @param array<string, string> $estados slug => estado del documento
     */
    private function sembrarExpediente(string $estado_solicitud, array $estados): void
    {
        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_estado_solicitud' => 'Pre-registro'],
            ['esso_id_c_estado_solicitud' => 2, 'esso_estado_solicitud' => 'Documentación'],
            ['esso_id_c_estado_solicitud' => 3, 'esso_estado_solicitud' => 'En revisión'],
            ['esso_id_c_estado_solicitud' => 4, 'esso_estado_solicitud' => 'Aprobada'],
            ['esso_id_c_estado_solicitud' => 5, 'esso_estado_solicitud' => 'Rechazada'],
            ['esso_id_c_estado_solicitud' => 6, 'esso_estado_solicitud' => 'Cancelada'],
        ]);

        foreach (['Pendiente', 'Cargado', 'En revisión', 'Aprobado', 'Rechazado'] as $i => $nombre) {
            DB::table('c_estado_documento')->insert([
                'esdo_id_c_estado_documento' => $i + 1,
                'esdo_estado_documento' => $nombre,
            ]);
        }

        DB::table('solicitud')->insert([
            'soli_id_solicitud' => self::SOLICITUD,
            'soli_id_persona' => DB::table('persona')->where('pers_id_usuario', self::USUARIO)->value('pers_id_persona'),
            'soli_id_convocatoria' => null,
            'soli_id_pago' => null,
        ]);

        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => DB::table('c_estado_solicitud')
                ->where('esso_estado_solicitud', $estado_solicitud)
                ->value('esso_id_c_estado_solicitud'),
            'esso_id_solicitud' => self::SOLICITUD,
        ]);

        foreach (config('suif.documentos') as $slug => $tipo) {
            $id_tipo = DB::table('tipo_documento')->insertGetId(
                ['tido_tipo_documento' => $tipo],
                'tido_id_tipo_documento'
            );

            $id_documento = DB::table('documento')->insertGetId([
                'tido_id_tipo_documento' => $id_tipo,
                'soli_id_solicitud' => self::SOLICITUD,
                'docu_path' => 'preregistro/cargas/'.self::SOLICITUD."/{$slug}.pdf",
                'docu_nombre' => "{$slug}.pdf",
            ], 'docu_id_documento');

            DB::table('estado_documento')->insert([
                'esdo_id_documento' => $id_documento,
                'esdo_id_c_estado_documento' => DB::table('c_estado_documento')
                    ->where('esdo_estado_documento', $estados[$slug] ?? 'En revisión')
                    ->value('esdo_id_c_estado_documento'),
            ]);
        }
    }

    private function estadoVigente(): string
    {
        return DB::table('estado_solicitud as es')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('es.esso_id_solicitud', self::SOLICITUD)
            ->orderByDesc('es.esso_id_estado_solicitud')
            ->value('ces.esso_estado_solicitud');
    }
}
