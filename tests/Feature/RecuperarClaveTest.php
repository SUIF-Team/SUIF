<?php

namespace Tests\Feature;

use App\Mail\ClaveRestablecida;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecuperarClaveTest extends TestCase
{
    private const MENSAJE_GENERICO = 'Si tu CURP está registrada, enviaremos una clave de acceso nueva a tu correo principal. Revisa también tu bandeja de spam.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaTemporal();

        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Administrador'],
        ]);
        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => Hash::make('AAAA-BBBB-CCCC')],
            ['usua_id_usuario' => 2, 'usua_id_rol' => 2, 'usua_clave_acceso' => Hash::make('DDDD-EEEE-FFFF')],
        ]);
        DB::table('persona')->insert([
            [
                'pers_id_persona' => 1,
                'pers_id_usuario' => 1,
                'pers_curp' => 'EAVR800101MDFNZS08',
                'pers_nombre' => 'Rosa',
            ],
            [
                'pers_id_persona' => 2,
                'pers_id_usuario' => 2,
                'pers_curp' => 'ADMA800101MDFNZS09',
                'pers_nombre' => 'Admin',
            ],
        ]);
        DB::table('tipo_comunicacion')->insert([
            ['tico_id_tipo_comunicacion' => 1, 'tico_tipo_comunicacion' => 'Correo principal'],
        ]);
        DB::table('comunicacion')->insert([
            ['comu_id_persona' => 1, 'comu_id_tipo_comunicacion' => 1, 'comu_descripcion' => 'rosa@example.com'],
            ['comu_id_persona' => 2, 'comu_id_tipo_comunicacion' => 1, 'comu_descripcion' => 'admin@example.com'],
        ]);
    }

    public function test_las_rutas_de_recuperacion_quedaron_registradas(): void
    {
        $this->assertTrue(Route::has('clave.recuperar'));
        $this->assertTrue(Route::has('clave.recuperar.post'));
        $this->assertTrue(Route::has('admin.personas.registradas.restaurar-clave'));
        $this->assertSame('/recuperar-clave', parse_url(route('clave.recuperar'), PHP_URL_PATH));
    }

    public function test_el_login_enlaza_la_recuperacion_y_el_formulario_renderiza(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Recuperar contraseña')
            ->assertSee(route('clave.recuperar'));

        $this->get(route('clave.recuperar'))
            ->assertOk()
            ->assertSee('Recuperar clave de acceso')
            ->assertSee('Enviar clave nueva');
    }

    public function test_curp_existente_e_inexistente_reciben_el_mismo_mensaje(): void
    {
        Mail::fake();

        $existente = $this->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08']);
        $inexistente = $this->post(route('clave.recuperar.post'), ['curp' => 'XXXX800101HDFXXX00']);

        $existente->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);
        $inexistente->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);
    }

    public function test_actualiza_el_hash_y_envia_la_clave_al_correo_principal(): void
    {
        Mail::fake();

        $this->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08'])
            ->assertRedirect(route('clave.recuperar'));

        $hash = DB::table('usuario')->where('usua_id_usuario', 1)->value('usua_clave_acceso');

        /* La clave anterior dejó de servir y la nueva viaja solo en el correo. */
        $this->assertFalse(Hash::check('AAAA-BBBB-CCCC', $hash));
        Mail::assertSent(ClaveRestablecida::class, function (ClaveRestablecida $correo) use ($hash): bool {
            return $correo->hasTo('rosa@example.com')
                && preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $correo->clave) === 1
                && Hash::check($correo->clave, $hash);
        });

        /* La respuesta jamás trae la clave. */
        $this->assertDoesNotMatchRegularExpression(
            '/[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/',
            (string) session('success')
        );
    }

    public function test_curp_inexistente_no_envia_correo_ni_toca_la_base(): void
    {
        Mail::fake();

        $hash_original = DB::table('usuario')->where('usua_id_usuario', 1)->value('usua_clave_acceso');

        $this->post(route('clave.recuperar.post'), ['curp' => 'XXXX800101HDFXXX00'])
            ->assertRedirect(route('clave.recuperar'));

        Mail::assertNothingSent();
        $this->assertSame(
            $hash_original,
            DB::table('usuario')->where('usua_id_usuario', 1)->value('usua_clave_acceso')
        );
    }

    public function test_si_el_correo_falla_la_clave_anterior_sigue_sirviendo(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP fuera de servicio'));

        $this->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08'])
            ->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);

        /* El hash solo cambia cuando el correo salió: sin correo la persona
           se quedaría sin ningún canal para conocer la clave nueva. */
        $hash = DB::table('usuario')->where('usua_id_usuario', 1)->value('usua_clave_acceso');
        $this->assertTrue(Hash::check('AAAA-BBBB-CCCC', $hash));
    }

    public function test_el_rol_administrador_no_se_recupera_por_autoservicio(): void
    {
        Mail::fake();

        $hash_original = DB::table('usuario')->where('usua_id_usuario', 2)->value('usua_clave_acceso');

        $this->post(route('clave.recuperar.post'), ['curp' => 'ADMA800101MDFNZS09'])
            ->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);

        Mail::assertNothingSent();
        $this->assertSame(
            $hash_original,
            DB::table('usuario')->where('usua_id_usuario', 2)->value('usua_clave_acceso')
        );
    }

    public function test_el_envio_masivo_topa_con_el_limite_por_ip(): void
    {
        /* Payload vacío: la validación corta antes de tocar la base, pero
           cada intento cuenta para el freno. */
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('clave.recuperar.post'), [])->assertRedirect();
        }

        $this->post(route('clave.recuperar.post'), [])->assertStatus(429);
    }

    private function crearEsquemaTemporal(): void
    {
        foreach (['comunicacion', 'tipo_comunicacion', 'persona', 'usuario', 'rol'] as $tabla) {
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
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
            $table->string('pers_nombre', 45);
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
    }
}
