<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
}
