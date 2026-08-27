<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquemaTemporal();

        DB::table('rol')->insert([
            ['rol_id_rol' => 1, 'rol_tipo_rol' => 'Persona'],
        ]);
        DB::table('usuario')->insert([
            ['usua_id_usuario' => 1, 'usua_id_rol' => 1, 'usua_clave_acceso' => Hash::make('AAAA-BBBB-CCCC')],
        ]);
        DB::table('persona')->insert([
            [
                'pers_id_persona' => 1,
                'pers_id_usuario' => 1,
                'pers_curp' => 'EAVR800101MDFNZS08',
                'pers_nombre' => 'Rosa',
            ],
        ]);
    }

    public function test_la_curp_inexistente_y_la_clave_mala_reciben_el_mismo_mensaje(): void
    {
        $inexistente = $this->post(route('login.post'), [
            'curp' => 'XXXX800101HDFXXX00',
            'clave' => 'AAAA-BBBB-CCCC',
        ]);
        $claveMala = $this->post(route('login.post'), [
            'curp' => 'EAVR800101MDFNZS08',
            'clave' => 'MALA-MALA-MALA',
        ]);

        $inexistente->assertSessionHas('error', 'La CURP o la clave de acceso no son correctas.');
        $claveMala->assertSessionHas('error', 'La CURP o la clave de acceso no son correctas.');
        $this->assertGuest();
    }

    public function test_con_la_clave_correcta_entra_al_panel_de_persona(): void
    {
        $this->post(route('login.post'), [
            'curp' => 'EAVR800101MDFNZS08',
            'clave' => 'AAAA-BBBB-CCCC',
        ])->assertRedirect(route('persona.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_seis_intentos_seguidos_con_la_misma_curp_activan_el_freno(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), [
                'curp' => 'EAVR800101MDFNZS08',
                'clave' => 'MALA-MALA-MALA',
            ])->assertRedirect();
        }

        $this->post(route('login.post'), [
            'curp' => 'EAVR800101MDFNZS08',
            'clave' => 'MALA-MALA-MALA',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_el_barrido_de_curps_distintas_topa_con_el_limite_por_ip(): void
    {
        /* Cada CURP distinta esquiva el freno por cuenta; el freno por
           dirección lo alcanza en el intento 21. */
        for ($i = 0; $i < 20; $i++) {
            $this->post(route('login.post'), [
                'curp' => sprintf('AAAA800101HDFRRR%02d', $i),
                'clave' => 'MALA-MALA-MALA',
            ])->assertRedirect();
        }

        $this->post(route('login.post'), [
            'curp' => 'AAAA800101HDFRRR99',
            'clave' => 'MALA-MALA-MALA',
        ])->assertStatus(429);
    }

    private function crearEsquemaTemporal(): void
    {
        foreach (['privilegio_rol', 'privilegio', 'persona', 'usuario', 'rol'] as $tabla) {
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
        });
    }
}
