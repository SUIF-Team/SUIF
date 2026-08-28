<?php

namespace Tests\Feature;

use App\Mail\ClaveRestablecida;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RestaurarClaveAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaTemporal();
        $this->cargarDatos();
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->post(route('admin.personas.registradas.restaurar-clave', ['id' => 1]))
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('AAAA-BBBB-CCCC', $this->hashDe(1)));
    }

    public function test_sin_el_privilegio_recibe_403(): void
    {
        $this->actingAs(Usuario::find(3));

        $this->post(route('admin.personas.registradas.restaurar-clave', ['id' => 1]))
            ->assertForbidden();

        $this->assertTrue(Hash::check('AAAA-BBBB-CCCC', $this->hashDe(1)));
    }

    public function test_restaura_hashea_y_envia_el_correo(): void
    {
        Mail::fake();

        $this->actingAs(Usuario::find(2))
            ->post(route('admin.personas.registradas.restaurar-clave', ['id' => 1]))
            ->assertRedirect(route('admin.personas.registradas.index'))
            ->assertSessionHas('success', 'La clave de Ada Lovelace Byron fue restaurada y enviada a su correo principal.');

        $hash = $this->hashDe(1);

        $this->assertFalse(Hash::check('AAAA-BBBB-CCCC', $hash));
        Mail::assertSent(ClaveRestablecida::class, function (ClaveRestablecida $correo) use ($hash): bool {
            return $correo->hasTo('ada@example.com')
                && preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $correo->clave) === 1
                && Hash::check($correo->clave, $hash);
        });

        /* Cuando el correo salió, el aviso al administrador no trae la clave. */
        $this->assertDoesNotMatchRegularExpression(
            '/[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}/',
            (string) session('success')
        );
    }

    public function test_si_el_correo_falla_la_clave_se_muestra_una_sola_vez(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP fuera de servicio'));

        $this->actingAs(Usuario::find(2))
            ->post(route('admin.personas.registradas.restaurar-clave', ['id' => 1]))
            ->assertRedirect(route('admin.personas.registradas.index'));

        $aviso = (string) session('warning');
        $this->assertStringContainsString('el correo no pudo enviarse', $aviso);
        $this->assertSame(1, preg_match('/([A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4})/', $aviso, $clave));

        /* El aviso es la única copia: la clave que muestra es la que quedó
           hasheada en la base, nunca NULL. */
        $hash = $this->hashDe(1);
        $this->assertNotNull($hash);
        $this->assertTrue(Hash::check($clave[1], $hash));
    }

    public function test_no_restaura_a_quien_no_esta_en_la_bandeja(): void
    {
        $casos = [
            '2',   /* Cuenta administrativa: fuera de la bandeja por rol. */
            '99',  /* No existe. */
            'abc', /* Id malformado. */
        ];

        foreach ($casos as $id) {
            $this->actingAs(Usuario::find(2))
                ->post(route('admin.personas.registradas.restaurar-clave', ['id' => $id]))
                ->assertRedirect(route('admin.personas.registradas.index'))
                ->assertSessionHas('warning', 'La persona solicitada no fue encontrada.');
        }

        $this->assertTrue(Hash::check('DDDD-EEEE-FFFF', $this->hashDe(2)));
    }

    private function hashDe(int $id_usuario): ?string
    {
        return DB::table('usuario')->where('usua_id_usuario', $id_usuario)->value('usua_clave_acceso');
    }

    private function crearEsquemaTemporal(): void
    {
        foreach ([
            'estado_solicitud',
            'c_estado_solicitud',
            'solicitud',
            'comunicacion',
            'tipo_comunicacion',
            'persona',
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
            $table->integer('usua_id_usuario')->primary();
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
        Schema::create('persona', function (Blueprint $table): void {
            $table->integer('pers_id_persona')->primary();
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
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
        Schema::create('solicitud', function (Blueprint $table): void {
            $table->integer('soli_id_solicitud')->primary();
            $table->integer('soli_id_persona')->nullable();
        });
        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_c_estado_solicitud')->primary();
            $table->string('esso_estado_solicitud', 40);
        });
        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->integer('esso_id_estado_solicitud')->primary();
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
            $table->string('esso_motivo_rechazo', 255)->nullable();
        });
    }

    private function cargarDatos(): void
    {
        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
            ['rol_id_rol' => 2, 'rol_tipo_rol' => 'Administrador'],
        ]);
        DB::table('privilegio')->insert([
            ['priv_id_privilegio' => 4, 'priv_privilegio' => 'Gestionar usuarios'],
        ]);
        DB::table('privilegio_rol')->insert([
            ['ropr_id_rol' => 2, 'ropr_id_privilegio' => 4],
        ]);
        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => Hash::make('AAAA-BBBB-CCCC')],
            ['usua_id_usuario' => 2, 'usua_id_rol' => 2, 'usua_clave_acceso' => Hash::make('DDDD-EEEE-FFFF')],
            ['usua_id_usuario' => 3, 'usua_id_rol' => 1, 'usua_clave_acceso' => Hash::make('GGGG-HHHH-IIII')],
        ]);
        DB::table('persona')->insert([
            [
                'pers_id_persona' => 1,
                'pers_id_usuario' => 1,
                'pers_curp' => 'LOVA151210MDFABC01',
                'pers_nombre' => 'Ada',
                'pers_apellido_paterno' => 'Lovelace',
                'pers_apellido_materno' => 'Byron',
                'pers_fecha_registro' => '2026-08-01',
            ],
            [
                'pers_id_persona' => 2,
                'pers_id_usuario' => 2,
                'pers_curp' => 'ADMA800101MDFABC03',
                'pers_nombre' => 'Cuenta',
                'pers_apellido_paterno' => 'Administrativa',
                'pers_apellido_materno' => 'SUIF',
                'pers_fecha_registro' => '2026-08-03',
            ],
            [
                'pers_id_persona' => 3,
                'pers_id_usuario' => 3,
                'pers_curp' => 'SINP900101MDFABC04',
                'pers_nombre' => 'Sin',
                'pers_apellido_paterno' => 'Privilegio',
                'pers_apellido_materno' => 'Alguno',
                'pers_fecha_registro' => '2026-08-04',
            ],
        ]);
        DB::table('tipo_comunicacion')->insert([
            ['tico_id_tipo_comunicacion' => 1, 'tico_tipo_comunicacion' => 'Correo principal'],
        ]);
        DB::table('comunicacion')->insert([
            ['comu_id_persona' => 1, 'comu_id_tipo_comunicacion' => 1, 'comu_descripcion' => 'ada@example.com'],
        ]);

        /* Solo Ada tiene una solicitud aprobada: es la única en la bandeja. */
        DB::table('c_estado_solicitud')->insert([
            ['esso_id_c_estado_solicitud' => 4, 'esso_estado_solicitud' => 'Aprobada'],
        ]);
        DB::table('solicitud')->insert([
            ['soli_id_solicitud' => 10, 'soli_id_persona' => 1],
        ]);
        DB::table('estado_solicitud')->insert([
            ['esso_id_estado_solicitud' => 1, 'esso_id_c_estado_solicitud' => 4, 'esso_id_solicitud' => 10],
        ]);
    }
}
