<?php

namespace Tests\Feature;

use App\Mail\ClaveAcceso;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreRegistroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaTemporal();
        $this->cargarCatalogos();
    }

    public function test_el_alta_envia_la_clave_por_correo_y_la_muestra_en_pantalla(): void
    {
        Mail::fake();

        $this->post(route('persona.preregistro.datos.store'), $this->datosValidos())
            ->assertRedirect(route('persona.preregistro.index'));

        Mail::assertSent(ClaveAcceso::class, function (ClaveAcceso $correo): bool {
            return $correo->hasTo('rosa@example.com');
        });

        $estado = session('suif.preregistro');
        $this->assertSame('clave', $estado['fase']);
        $this->assertTrue($estado['correo_enviado']);
        $this->assertNotEmpty($estado['clave']);

        /* La clave guardada en la base va hasheada, nunca en claro. */
        $guardada = DB::table('usuario')->value('usua_clave_acceso');
        $this->assertTrue(Hash::check($estado['clave'], $guardada));
        $this->assertNotSame($estado['clave'], $guardada);

        /* La pantalla la muestra y sobrevive a una recarga sin confirmar. */
        $this->get(route('persona.preregistro.index'))
            ->assertOk()
            ->assertSee($estado['clave'])
            ->assertSee('También la enviamos a tu correo principal');
        $this->get(route('persona.preregistro.index'))
            ->assertOk()
            ->assertSee($estado['clave']);
    }

    /**
     * La convocatoria manda sobre la ventana de fechas: interrumpirla cierra el
     * registro aunque sus fechas sigan abiertas.
     *
     * Sin este corte, SOLICITUD recibiría un nulo en una columna obligatoria y
     * quien se está registrando vería un error de base de datos.
     */
    public function test_sin_convocatoria_vigente_el_alta_se_detiene_sin_crear_nada(): void
    {
        Mail::fake();

        DB::table('estado_convocatoria')->insert([
            'esco_id_c_estado_convocatoria' => 3,
            'esco_id_convocatoria' => 1,
            'esco_fecha' => now()->toDateString(),
            'esco_hora' => now()->toTimeString(),
        ]);

        $this->post(route('persona.preregistro.datos.store'), $this->datosValidos())
            ->assertSessionHasErrors('datos');

        $this->assertSame(0, DB::table('usuario')->count());
        $this->assertSame(0, DB::table('persona')->count());
        $this->assertSame(0, DB::table('solicitud')->count());

        Mail::assertNothingSent();
    }

    public function test_el_fallo_del_correo_no_revierte_el_alta_y_se_avisa_en_pantalla(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP fuera de servicio'));

        $this->post(route('persona.preregistro.datos.store'), $this->datosValidos())
            ->assertRedirect(route('persona.preregistro.index'));

        /* El alta ya ocurrió: el correo es un respaldo, no un requisito. */
        $this->assertTrue(
            DB::table('persona')->where('pers_curp', 'EAVR800101MDFNZS08')->exists()
        );

        $estado = session('suif.preregistro');
        $this->assertFalse($estado['correo_enviado']);

        $this->get(route('persona.preregistro.index'))
            ->assertOk()
            ->assertSee($estado['clave'])
            ->assertSee('No pudimos enviar el correo con tu clave');
    }

    public function test_avanzar_limpia_la_clave_de_la_sesion(): void
    {
        Mail::fake();

        $this->post(route('persona.preregistro.datos.store'), $this->datosValidos());
        $clave = session('suif.preregistro')['clave'];

        $this->post(route('persona.preregistro.avanzar'))
            ->assertRedirect(route('persona.documentos.index'));

        $estado = session('suif.preregistro');
        $this->assertSame('formatos', $estado['fase']);
        $this->assertNull($estado['clave']);

        $this->get(route('persona.preregistro.index'))
            ->assertOk()
            ->assertDontSee($clave);
    }

    public function test_una_sesion_vieja_con_clave_rezagada_se_limpia_al_entrar(): void
    {
        Mail::fake();

        /* Alta normal y avance, y después se simula una sesión previa al
           cambio, que conservaba la clave en claro en fases posteriores. */
        $this->post(route('persona.preregistro.datos.store'), $this->datosValidos());
        $estado = session('suif.preregistro');
        $estado['fase'] = 'formatos';
        $estado['clave'] = 'AAAA-BBBB-CCCC';
        session()->put('suif.preregistro', $estado);

        $this->get(route('persona.preregistro.index'))->assertOk();

        $this->assertNull(session('suif.preregistro')['clave']);
    }

    public function test_el_alta_masiva_topa_con_el_limite_por_ip(): void
    {
        /* Payload vacío: la validación corta antes de tocar la base, pero
           cada intento cuenta para el freno. */
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('persona.preregistro.datos.store'), [])->assertRedirect();
        }

        $this->post(route('persona.preregistro.datos.store'), [])->assertStatus(429);
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Rosa',
            'primer_apellido' => 'Enriquez',
            'segundo_apellido' => 'Vazquez',
            'curp' => 'EAVR800101MDFNZS08',
            'rfc' => 'EAVR800101AB1',
            'correo_principal' => 'rosa@example.com',
            'correo_alterno' => 'rosa.alterno@example.com',
            'telefono' => '5512345678',
            'entidad_federativa' => 'Ciudad de México',
            'grado_estudios' => 'licenciatura',
            'actividad_vulnerable' => 'no',
            'responsable_cumplimiento' => 'no',
        ], $cambios);
    }

    private function crearEsquemaTemporal(): void
    {
        foreach ([
            'estado_solicitud',
            'c_estado_solicitud',
            'solicitud',
            'estado_convocatoria',
            'c_estado_convocatoria',
            'convocatoria',
            'documento',
            'tipo_documento',
            'grado_persona',
            'nivel_profesional',
            'trabajo_persona',
            'trabajo',
            'comunicacion',
            'tipo_comunicacion',
            'persona',
            'entidad_federativa',
            'privilegio_rol',
            'privilegio',
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
            $table->increments('usua_id_usuario');
            $table->integer('usua_id_rol');
            $table->string('usua_clave_acceso')->nullable();
        });
        Schema::create('privilegio', function (Blueprint $table): void {
            $table->integer('priv_id_privilegio')->primary();
            $table->string('priv_privilegio', 45);
        });
        Schema::create('privilegio_rol', function (Blueprint $table): void {
            $table->integer('ropr_id_rol');
            $table->integer('ropr_id_privilegio');
        });
        Schema::create('entidad_federativa', function (Blueprint $table): void {
            $table->string('enfe_clave_inegi', 3)->primary();
            $table->string('enfe_entidad_federativa', 45);
        });
        Schema::create('persona', function (Blueprint $table): void {
            $table->increments('pers_id_persona');
            $table->integer('pers_id_usuario');
            $table->string('pers_clave_inegi', 3)->nullable();
            $table->string('pers_curp', 18);
            $table->string('pers_rfc', 13)->nullable();
            $table->string('pers_nombre', 45);
            $table->string('pers_apellido_paterno', 45)->nullable();
            $table->string('pers_apellido_materno', 45)->nullable();
            $table->date('pers_fecha_registro')->nullable();
        });
        Schema::create('tipo_comunicacion', function (Blueprint $table): void {
            $table->integer('tico_id_tipo_comunicacion')->primary();
            $table->string('tico_tipo_comunicacion', 45);
        });
        Schema::create('comunicacion', function (Blueprint $table): void {
            $table->increments('comu_id_comunicacion');
            $table->integer('comu_id_persona');
            $table->integer('comu_id_tipo_comunicacion');
            $table->string('comu_descripcion', 65);
        });
        Schema::create('trabajo', function (Blueprint $table): void {
            $table->increments('trab_id_trabajo');
            $table->boolean('trab_actividad_vulnerable');
            $table->boolean('trab_responsable');
        });
        Schema::create('trabajo_persona', function (Blueprint $table): void {
            $table->integer('trpe_id_trabajo');
            $table->integer('trpe_id_persona');
        });
        Schema::create('nivel_profesional', function (Blueprint $table): void {
            $table->integer('nipr_id_nivel_profesional')->primary();
            $table->string('nipr_nivel_profesional', 45);
        });
        Schema::create('grado_persona', function (Blueprint $table): void {
            $table->integer('grpe_id_nivel_profesional');
            $table->integer('grpe_id_persona');
        });
        Schema::create('tipo_documento', function (Blueprint $table): void {
            $table->integer('tido_id_tipo_documento')->primary();
            $table->string('tido_tipo_documento');
        });
        Schema::create('documento', function (Blueprint $table): void {
            $table->increments('docu_id_documento');
            $table->integer('tido_id_tipo_documento');
            $table->integer('soli_id_solicitud');
            $table->string('docu_path')->nullable();
            $table->string('docu_nombre')->nullable();
        });
        Schema::create('convocatoria', function (Blueprint $table): void {
            $table->increments('conv_id_convocatoria');
            $table->date('conv_fecha_inicio_registro');
            $table->date('conv_fecha_fin_registro');
        });
        /* El pre-registro ya no elige la convocatoria sólo por fechas: además
           exige que su último estado sea "Vigente". */
        Schema::create('c_estado_convocatoria', function (Blueprint $table): void {
            $table->increments('esco_id_c_estado_convocatoria');
            $table->string('esco_estado_convocatoria', 15);
        });
        Schema::create('estado_convocatoria', function (Blueprint $table): void {
            $table->increments('esco_id_estado_convocatoria');
            $table->integer('esco_id_c_estado_convocatoria');
            $table->integer('esco_id_convocatoria');
            $table->date('esco_fecha')->nullable();
            $table->time('esco_hora')->nullable();
        });
        Schema::create('solicitud', function (Blueprint $table): void {
            $table->increments('soli_id_solicitud');
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_convocatoria')->nullable();
            $table->integer('soli_id_pago')->nullable();
            $table->integer('soli_id_evaluacion')->nullable();
        });
        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_c_estado_solicitud')->primary();
            $table->string('esso_estado_solicitud', 40);
        });
        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_estado_solicitud');
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
            $table->date('esso_fecha')->nullable();
            $table->time('esso_hora')->nullable();
            $table->string('esso_motivo_rechazo', 255)->nullable();
        });
    }

    private function cargarCatalogos(): void
    {
        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
        ]);
        DB::table('entidad_federativa')->insert([
            ['enfe_clave_inegi' => '09', 'enfe_entidad_federativa' => 'Ciudad de México'],
        ]);
        DB::table('tipo_comunicacion')->insert([
            ['tico_id_tipo_comunicacion' => 1, 'tico_tipo_comunicacion' => 'Correo principal'],
            ['tico_id_tipo_comunicacion' => 2, 'tico_tipo_comunicacion' => 'Correo alterno'],
            ['tico_id_tipo_comunicacion' => 3, 'tico_tipo_comunicacion' => 'Teléfono celular'],
        ]);
        DB::table('nivel_profesional')->insert([
            ['nipr_id_nivel_profesional' => 1, 'nipr_nivel_profesional' => 'Licenciatura'],
        ]);
        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 1, 'esso_estado_solicitud' => 'Pre-registro'],
        ]);
        DB::table('convocatoria')->insert([
            [
                'conv_id_convocatoria' => 1,
                'conv_fecha_inicio_registro' => now()->subDay()->toDateString(),
                'conv_fecha_fin_registro' => now()->addMonth()->toDateString(),
            ],
        ]);
        DB::table('c_estado_convocatoria')->insert([
            ['esco_id_c_estado_convocatoria' => 1, 'esco_estado_convocatoria' => 'Vigente'],
            ['esco_id_c_estado_convocatoria' => 2, 'esco_estado_convocatoria' => 'Cerrada'],
            ['esco_id_c_estado_convocatoria' => 3, 'esco_estado_convocatoria' => 'Interrumpida'],
        ]);
        DB::table('estado_convocatoria')->insert([
            [
                'esco_id_c_estado_convocatoria' => 1,
                'esco_id_convocatoria' => 1,
                'esco_fecha' => now()->subDay()->toDateString(),
                'esco_hora' => now()->toTimeString(),
            ],
        ]);
    }
}
