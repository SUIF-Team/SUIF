<?php

namespace Tests\Feature;

use App\Mail\EnlaceRecuperacion;
use App\Models\Usuario;
use App\Servicios\GestionClaves;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RecuperarClaveTest extends TestCase
{
    private const MENSAJE_GENERICO = 'Si tu CURP está registrada, enviaremos a tu correo principal un enlace para crear una clave nueva. Revisa también tu bandeja de spam.';

    private const ENLACE_INVALIDO = 'El enlace ya no es válido';

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaTemporal();

        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Administrador'],
        ]);
        /* Quién es administrador lo decide el privilegio, no el nombre del
           rol: el rol 2 lo es porque tiene uno del catálogo. */
        DB::table('privilegio')->insert([
            ['priv_id_privilegio' => 1, 'priv_privilegio' => 'Gestionar usuarios'],
        ]);
        DB::table('privilegio_rol')->insert([
            ['ropr_id_privilegio' => 1, 'ropr_id_rol' => 2],
        ]);
        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => Hash::make('AAAA-BBBB-CCCC'), 'usua_activo' => true],
            ['usua_id_usuario' => 2, 'usua_id_rol' => 2, 'usua_clave_acceso' => Hash::make('DDDD-EEEE-FFFF'), 'usua_activo' => true],
            ['usua_id_usuario' => 3, 'usua_id_rol' => 2, 'usua_clave_acceso' => Hash::make('GGGG-HHHH-IIII'), 'usua_activo' => false],
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
            [
                'pers_id_persona' => 3,
                'pers_id_usuario' => 3,
                'pers_curp' => 'BAJA800101MDFNZS07',
                'pers_nombre' => 'Baja',
            ],
        ]);
        DB::table('tipo_comunicacion')->insert([
            ['tico_id_tipo_comunicacion' => 1, 'tico_tipo_comunicacion' => 'Correo principal'],
        ]);
        DB::table('comunicacion')->insert([
            ['comu_id_persona' => 1, 'comu_id_tipo_comunicacion' => 1, 'comu_descripcion' => 'rosa@example.com'],
            ['comu_id_persona' => 2, 'comu_id_tipo_comunicacion' => 1, 'comu_descripcion' => 'admin@example.com'],
            ['comu_id_persona' => 3, 'comu_id_tipo_comunicacion' => 1, 'comu_descripcion' => 'baja@example.com'],
        ]);
    }

    public function test_las_rutas_de_recuperacion_quedaron_registradas(): void
    {
        $this->assertTrue(Route::has('clave.recuperar'));
        $this->assertTrue(Route::has('clave.recuperar.post'));
        $this->assertTrue(Route::has('clave.restablecer'));
        $this->assertTrue(Route::has('clave.restablecer.post'));
        $this->assertTrue(Route::has('admin.personas.registradas.restaurar-clave'));
        $this->assertSame('/recuperar-clave', parse_url(route('clave.recuperar'), PHP_URL_PATH));
    }

    public function test_el_login_enlaza_la_recuperacion_y_el_formulario_renderiza(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Recuperar clave de acceso')
            ->assertSee(route('clave.recuperar'));

        $this->get(route('clave.recuperar'))
            ->assertOk()
            ->assertSee('Recuperar clave de acceso')
            ->assertSee('Enviar enlace');
    }

    /**
     * El from() no es adorno: la acción responde con back() —el formulario de
     * recuperación se contesta sobre su misma pantalla— y back() resuelve el
     * referer o, si falta, la última URL que la sesión visitó. El navegador
     * siempre trae una de las dos; una prueba que sólo hace post() no trae
     * ninguna, y Laravel caería en «/».
     */
    public function test_curp_existente_e_inexistente_reciben_el_mismo_mensaje(): void
    {
        Mail::fake();

        $existente = $this->from(route('clave.recuperar'))
            ->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08']);
        $inexistente = $this->from(route('clave.recuperar'))
            ->post(route('clave.recuperar.post'), ['curp' => 'XXXX800101HDFXXX00']);

        $existente->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);
        $inexistente->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);
    }

    public function test_envia_un_enlace_y_no_toca_la_clave(): void
    {
        Mail::fake();

        $this->from(route('clave.recuperar'))
            ->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08'])
            ->assertRedirect(route('clave.recuperar'));

        /* Pedir la recuperación ya no revoca nada: quien conozca una CURP
           ajena no puede dejar a su dueña fuera. */
        $this->assertTrue(Hash::check('AAAA-BBBB-CCCC', $this->hash(1)));

        Mail::assertSent(EnlaceRecuperacion::class, function (EnlaceRecuperacion $correo): bool {
            return $correo->hasTo('rosa@example.com')
                && str_contains($correo->enlace, '/recuperar-clave/1/')
                && str_contains($correo->enlace, 'signature=');
        });
    }

    public function test_el_correo_lleva_el_enlace_sin_escapar(): void
    {
        /* Correo de texto: con {{ }} el & de la firma saldría como &amp; y el
           enlace dejaría de servir. */
        $enlace = $this->pedirEnlace();

        $texto = view('emails.enlace-recuperacion', ['enlace' => $enlace, 'vigencia' => 60])->render();

        $this->assertStringContainsString($enlace, $texto);
        $this->assertStringNotContainsString('&amp;', $texto);
    }

    public function test_abrir_el_enlace_muestra_el_boton_y_no_cambia_la_clave(): void
    {
        $enlace = $this->pedirEnlace();

        /* Los antivirus de correo abren los enlaces: el GET no gasta nada. */
        $respuesta = $this->get($enlace)
            ->assertOk()
            ->assertSee('Generar clave nueva');

        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));

        $this->assertTrue(Hash::check('AAAA-BBBB-CCCC', $this->hash(1)));
    }

    public function test_confirmar_genera_la_clave_y_la_muestra_una_vez(): void
    {
        $enlace = $this->pedirEnlace();

        $respuesta = $this->post($enlace)->assertOk();

        preg_match('/class="codigo__valor">([A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4})</', $respuesta->getContent(), $coincidencia);
        $this->assertNotEmpty($coincidencia, 'La pantalla debe mostrar la clave generada.');

        $this->assertTrue(Hash::check($coincidencia[1], $this->hash(1)));
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertFalse(Hash::check('AAAA-BBBB-CCCC', $this->hash(1)));
    }

    public function test_el_enlace_sirve_una_sola_vez(): void
    {
        $enlace = $this->pedirEnlace();

        $this->post($enlace)->assertOk();
        $hash = $this->hash(1);

        /* La huella ya no coincide con la clave nueva. */
        $this->get($enlace)->assertForbidden()->assertSee(self::ENLACE_INVALIDO);
        $this->post($enlace)->assertForbidden()->assertSee(self::ENLACE_INVALIDO);

        $this->assertSame($hash, $this->hash(1));
    }

    public function test_el_enlace_caduca_en_una_hora(): void
    {
        $enlace = $this->pedirEnlace();

        $this->travel(GestionClaves::VIGENCIA_ENLACE_MINUTOS + 1)->minutes();

        $this->post($enlace)->assertForbidden()->assertSee(self::ENLACE_INVALIDO);
        $this->assertTrue(Hash::check('AAAA-BBBB-CCCC', $this->hash(1)));
    }

    public function test_un_enlace_alterado_no_sirve(): void
    {
        $enlace = $this->pedirEnlace();

        /* Otro usuario en la misma firma: la firma deja de cuadrar. */
        $this->post(str_replace('/recuperar-clave/1/', '/recuperar-clave/2/', $enlace))
            ->assertForbidden();

        /* Firma válida con una huella que no es la de la clave vigente. */
        $this->post($this->enlaceFirmado(1, str_repeat('0', 32)))->assertForbidden();

        $this->assertTrue(Hash::check('AAAA-BBBB-CCCC', $this->hash(1)));
    }

    public function test_un_enlace_firmado_no_abre_una_cuenta_administrativa(): void
    {
        $gestion = app(GestionClaves::class);
        $hash = $this->hash(2);

        $this->post($this->enlaceFirmado(2, $gestion->huella(Usuario::findOrFail(2))))
            ->assertForbidden();

        $this->assertSame($hash, $this->hash(2));
    }

    public function test_una_misma_curp_topa_aunque_cambie_la_ip(): void
    {
        Mail::fake();

        /* Llenarle el buzón a una persona desde muchas direcciones. */
        for ($i = 1; $i <= 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->from(route('clave.recuperar'))
                ->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08'])
                ->assertRedirect(route('clave.recuperar'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08'])
            ->assertStatus(429);

        Mail::assertSent(EnlaceRecuperacion::class, 3);
    }

    public function test_curp_inexistente_no_envia_correo_ni_toca_la_base(): void
    {
        Mail::fake();

        $hash_original = DB::table('usuario')->where('usua_id_usuario', 1)->value('usua_clave_acceso');

        $this->from(route('clave.recuperar'))
            ->post(route('clave.recuperar.post'), ['curp' => 'XXXX800101HDFXXX00'])
            ->assertRedirect(route('clave.recuperar'));

        Mail::assertNothingSent();
        $this->assertSame(
            $hash_original,
            DB::table('usuario')->where('usua_id_usuario', 1)->value('usua_clave_acceso')
        );
    }

    public function test_el_rol_administrador_no_se_recupera_por_autoservicio(): void
    {
        Mail::fake();

        $hash_original = DB::table('usuario')->where('usua_id_usuario', 2)->value('usua_clave_acceso');

        $this->from(route('clave.recuperar'))
            ->post(route('clave.recuperar.post'), ['curp' => 'ADMA800101MDFNZS09'])
            ->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);

        Mail::assertNothingSent();
        $this->assertSame(
            $hash_original,
            DB::table('usuario')->where('usua_id_usuario', 2)->value('usua_clave_acceso')
        );
    }

    /**
     * Dar de baja a un administrador le retira los privilegios, pero no lo
     * convierte en persona: su clave tampoco se revoca desde aquí.
     */
    public function test_un_administrador_dado_de_baja_no_se_recupera_por_autoservicio(): void
    {
        Mail::fake();

        $hash_original = DB::table('usuario')->where('usua_id_usuario', 3)->value('usua_clave_acceso');

        $this->from(route('clave.recuperar'))
            ->post(route('clave.recuperar.post'), ['curp' => 'BAJA800101MDFNZS07'])
            ->assertRedirect(route('clave.recuperar'))
            ->assertSessionHas('success', self::MENSAJE_GENERICO);

        Mail::assertNothingSent();
        $this->assertSame(
            $hash_original,
            DB::table('usuario')->where('usua_id_usuario', 3)->value('usua_clave_acceso')
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

    /** Pide la recuperación de la persona 1 y devuelve el enlace del correo. */
    private function pedirEnlace(): string
    {
        Mail::fake();

        $this->from(route('clave.recuperar'))
            ->post(route('clave.recuperar.post'), ['curp' => 'EAVR800101MDFNZS08']);

        $enlace = null;

        Mail::assertSent(EnlaceRecuperacion::class, function (EnlaceRecuperacion $correo) use (&$enlace): bool {
            $enlace = $correo->enlace;

            return true;
        });

        return $enlace;
    }

    private function enlaceFirmado(int $id_usuario, string $huella): string
    {
        return url(URL::temporarySignedRoute(
            'clave.restablecer',
            now()->addHour(),
            ['usuario' => $id_usuario, 'huella' => $huella],
            absolute: false
        ));
    }

    private function hash(int $id_usuario): string
    {
        return (string) DB::table('usuario')->where('usua_id_usuario', $id_usuario)->value('usua_clave_acceso');
    }

    private function crearEsquemaTemporal(): void
    {
        foreach (['comunicacion', 'tipo_comunicacion', 'persona', 'privilegio_rol', 'privilegio', 'usuario', 'rol'] as $tabla) {
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
            $table->boolean('usua_activo')->default(true);
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
