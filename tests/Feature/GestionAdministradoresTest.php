<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\GestionAdministradores;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * El módulo que da de alta a quienes operan el sistema.
 *
 * Lo abre sólo quien tiene "Gestionar usuarios", que es el privilegio más
 * delicado del catálogo: quien lo tiene decide quién entra.
 */
class GestionAdministradoresTest extends TestCase
{
    use SiembraAdministradores;

    private const SUPERUSUARIO = 1;

    private const ADMIN_UIF = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaTemporal();
        $this->cargarDatos();
    }

    public function test_el_modulo_exige_el_privilegio_de_gestionar_usuarios(): void
    {
        $this->get(route('admin.administradores.index'))
            ->assertRedirect(route('login'));

        /* Quien valida expedientes no decide quién entra al sistema. */
        $this->actingAs(Usuario::findOrFail(self::ADMIN_UIF))
            ->get(route('admin.administradores.index'))
            ->assertForbidden();

        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->get(route('admin.administradores.index'))
            ->assertOk()
            ->assertSee('Beto Revisor Prueba');
    }

    public function test_el_alta_crea_usuario_y_persona_con_la_clave_hasheada(): void
    {
        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->post(route('admin.administradores.store'), $this->datos())
            ->assertRedirect(route('admin.administradores.index'))
            ->assertSessionHas('success', 'El administrador se creó correctamente.');

        $idRolDec = DB::table('rol')->where('rol_tipo_rol', 'Admin DEC')->value('rol_id_rol');
        $usuario = DB::table('usuario')->where('usua_id_rol', $idRolDec)->first();

        $this->assertNotNull($usuario);
        $this->assertTrue((bool) $usuario->usua_activo);
        $this->assertTrue(Hash::check('ClaveSegura1', $usuario->usua_clave_acceso));

        $this->assertDatabaseHas('persona', [
            'pers_id_usuario' => $usuario->usua_id_usuario,
            'pers_curp' => 'NUEV800101HDFRRN07',
            'pers_nombre' => 'Cira',
            'pers_clave_inegi' => '009',
        ]);
    }

    public function test_una_curp_ya_registrada_se_rechaza_en_el_formulario(): void
    {
        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->post(route('admin.administradores.store'), $this->datos([
                'curp' => 'UIFA800101HDFRRN02',
            ]))
            ->assertSessionHasErrors('curp');

        $this->assertSame(2, DB::table('usuario')->count());
    }

    public function test_editar_sin_clave_conserva_la_anterior_y_con_clave_la_reemplaza(): void
    {
        $antes = DB::table('usuario')->where('usua_id_usuario', self::ADMIN_UIF)->value('usua_clave_acceso');

        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->put(route('admin.administradores.update', self::ADMIN_UIF), $this->datos([
                'curp' => 'UIFA800101HDFRRN02',
                'nombre' => 'Beto',
                'rol_id' => DB::table('rol')->where('rol_tipo_rol', 'Admin UIF')->value('rol_id_rol'),
                'clave' => '',
            ]))
            ->assertRedirect(route('admin.administradores.index'));

        $this->assertSame(
            $antes,
            DB::table('usuario')->where('usua_id_usuario', self::ADMIN_UIF)->value('usua_clave_acceso')
        );

        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->put(route('admin.administradores.update', self::ADMIN_UIF), $this->datos([
                'curp' => 'UIFA800101HDFRRN02',
                'nombre' => 'Beto',
                'rol_id' => DB::table('rol')->where('rol_tipo_rol', 'Admin UIF')->value('rol_id_rol'),
                'clave' => 'ClaveNueva99',
            ]))
            ->assertRedirect(route('admin.administradores.index'));

        $this->assertTrue(Hash::check(
            'ClaveNueva99',
            DB::table('usuario')->where('usua_id_usuario', self::ADMIN_UIF)->value('usua_clave_acceso')
        ));
    }

    public function test_nadie_se_da_de_baja_a_si_mismo(): void
    {
        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->delete(route('admin.administradores.destroy', self::SUPERUSUARIO))
            ->assertSessionHas('error', 'No puedes darte de baja a ti mismo.');

        $this->assertTrue(
            (bool) DB::table('usuario')->where('usua_id_usuario', self::SUPERUSUARIO)->value('usua_activo')
        );
    }

    /**
     * Por HTTP esta regla queda detrás de «no te des de baja a ti mismo»,
     * porque el único que puede abrir el módulo es un Superusuario. Se prueba
     * contra el servicio, que es donde tiene que sostenerse: también lo llama
     * el comando de consola.
     */
    public function test_el_servicio_no_deja_al_sistema_sin_superusuarios(): void
    {
        $gestion = app(GestionAdministradores::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('único Superusuario activo');

        /* El id en sesión es otro a propósito: lo que frena la baja no es que
           sea uno mismo, sino que no quedaría ninguno. */
        $gestion->desactivar(self::SUPERUSUARIO, self::ADMIN_UIF);
    }

    public function test_con_dos_superusuarios_si_se_puede_dar_de_baja_a_uno(): void
    {
        $gestion = app(GestionAdministradores::class);
        $idSegundo = $gestion->crear($this->datos([
            'curp' => 'SUPB800101HDFRRN08',
            'rol_id' => DB::table('rol')->where('rol_tipo_rol', 'Superusuario')->value('rol_id_rol'),
        ]));

        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->delete(route('admin.administradores.destroy', $idSegundo))
            ->assertRedirect(route('admin.administradores.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario', [
            'usua_id_usuario' => $idSegundo,
            'usua_activo' => false,
        ]);
    }

    public function test_la_baja_retira_el_acceso_sin_borrar_y_se_puede_devolver(): void
    {
        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->delete(route('admin.administradores.destroy', self::ADMIN_UIF))
            ->assertRedirect(route('admin.administradores.index'))
            ->assertSessionHas('success', 'El administrador quedó sin acceso al sistema.');

        /* El renglón se conserva: es el rastro de lo que dictaminó. */
        $this->assertDatabaseHas('usuario', [
            'usua_id_usuario' => self::ADMIN_UIF,
            'usua_activo' => false,
        ]);
        $this->assertDatabaseHas('persona', ['pers_id_usuario' => self::ADMIN_UIF]);

        $this->actingAs(Usuario::findOrFail(self::SUPERUSUARIO))
            ->post(route('admin.administradores.reactivar', self::ADMIN_UIF))
            ->assertSessionHas('success', 'El administrador recuperó su acceso.');

        $this->assertDatabaseHas('usuario', [
            'usua_id_usuario' => self::ADMIN_UIF,
            'usua_activo' => true,
        ]);
    }

    public function test_quien_perdio_el_acceso_no_inicia_sesion_y_el_mensaje_no_lo_delata(): void
    {
        DB::table('usuario')
            ->where('usua_id_usuario', self::ADMIN_UIF)
            ->update(['usua_activo' => false]);

        $this->post(route('login.post'), [
            'curp' => 'UIFA800101HDFRRN02',
            'clave' => 'ClaveSegura1',
        ])
            ->assertRedirect()
            ->assertSessionHas('error', 'La CURP o la clave de acceso no son correctas.');

        $this->assertGuest();
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Cira',
            'primer_apellido' => 'Nueva',
            'segundo_apellido' => 'Prueba',
            'curp' => 'NUEV800101HDFRRN07',
            'entidad_federativa' => '009',
            'rol_id' => DB::table('rol')->where('rol_tipo_rol', 'Admin DEC')->value('rol_id_rol'),
            'clave' => 'ClaveSegura1',
        ], $extra);
    }

    private function crearEsquemaTemporal(): void
    {
        foreach (['privilegio_rol', 'privilegio', 'entidad_federativa', 'persona', 'usuario', 'rol'] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('rol', function (Blueprint $table): void {
            $table->increments('rol_id_rol');
            $table->string('rol_tipo_rol', 15);
        });
        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('usua_id_usuario');
            $table->integer('usua_id_rol');
            $table->string('usua_clave_acceso')->nullable();
            $table->boolean('usua_activo')->default(true);
        });
        Schema::create('persona', function (Blueprint $table): void {
            $table->increments('pers_id_persona');
            $table->string('pers_clave_inegi', 3);
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
            $table->string('pers_nombre', 45);
            $table->string('pers_apellido_paterno', 45)->nullable();
            $table->string('pers_apellido_materno', 45);
            $table->date('pers_fecha_registro');
        });
        Schema::create('entidad_federativa', function (Blueprint $table): void {
            $table->string('enfe_clave_inegi', 3)->primary();
            $table->string('enfe_entidad_federativa', 60);
        });

        $this->crearTablasDePrivilegios();
    }

    private function cargarDatos(): void
    {
        DB::table('entidad_federativa')->insert([
            ['enfe_clave_inegi' => '009', 'enfe_entidad_federativa' => 'Ciudad de México'],
        ]);

        $this->sembrarRolesAdministrativos([
            'Superusuario' => 1,
            'Admin UIF' => 2,
            'Admin DEC' => 3,
        ]);

        DB::table('usuario')->insert([
            [
                'usua_id_usuario' => self::SUPERUSUARIO,
                'usua_id_rol' => 1,
                'usua_clave_acceso' => Hash::make('ClaveSegura1'),
                'usua_activo' => true,
            ],
            [
                'usua_id_usuario' => self::ADMIN_UIF,
                'usua_id_rol' => 2,
                'usua_clave_acceso' => Hash::make('ClaveSegura1'),
                'usua_activo' => true,
            ],
        ]);

        DB::table('persona')->insert([
            [
                'pers_id_persona' => 1,
                'pers_clave_inegi' => '009',
                'pers_id_usuario' => self::SUPERUSUARIO,
                'pers_curp' => 'SUPE800101HDFRRN01',
                'pers_nombre' => 'Ana',
                'pers_apellido_paterno' => 'Mando',
                'pers_apellido_materno' => 'Prueba',
                'pers_fecha_registro' => '2026-08-01',
            ],
            [
                'pers_id_persona' => 2,
                'pers_clave_inegi' => '009',
                'pers_id_usuario' => self::ADMIN_UIF,
                'pers_curp' => 'UIFA800101HDFRRN02',
                'pers_nombre' => 'Beto',
                'pers_apellido_paterno' => 'Revisor',
                'pers_apellido_materno' => 'Prueba',
                'pers_fecha_registro' => '2026-08-01',
            ],
        ]);
    }
}
