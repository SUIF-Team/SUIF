<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\GestionAdministradores;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

class GestionAdministradoresTest extends TestCase
{
    use SiembraAdministradores;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->sembrarRolesYPrivilegios();

        $this->crearCuenta(1, self::ROL_PERSONA, 'PERS900101MDFABC01', 'Persona', 'Solicitante', 'Prueba');
        $this->crearCuenta(2, self::ROL_SUPERUSUARIO, 'SUPE900101MDFABC02', 'Sofía', 'Superusuaria', 'Prueba');
        $this->crearCuenta(3, self::ROL_ADMIN_UIF, 'UIFA900101MDFABC03', 'Ulises', 'Registro', 'Prueba');
        $this->crearCuenta(4, self::ROL_ADMIN_DEC, 'DECA900101MDFABC04', 'Delia', 'Pagos', 'Prueba');
    }

    public function test_solo_el_superusuario_abre_el_modulo(): void
    {
        $this->get(route('admin.administradores.index'))
            ->assertRedirect(route('login'));

        foreach ([1, 3, 4] as $idUsuario) {
            $this->actingAs(Usuario::findOrFail($idUsuario))
                ->get(route('admin.administradores.index'))
                ->assertForbidden();
        }

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.administradores.index'))
            ->assertOk()
            ->assertSee('Gestión de usuarios');
    }

    public function test_el_alta_crea_usuario_y_persona_con_la_clave_hasheada(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.administradores.store'), $this->datosDeAlta())
            ->assertRedirect(route('admin.administradores.index'))
            ->assertSessionHas('success', 'El administrador se creó correctamente.');

        $idUsuario = (int) DB::table('persona')
            ->where('pers_curp', 'NUCA900101MDFXYZ01')
            ->value('pers_id_usuario');

        $this->assertGreaterThan(0, $idUsuario);
        $this->assertDatabaseHas('usuario', [
            'usua_id_usuario' => $idUsuario,
            'usua_id_rol' => self::ROL_ADMIN_UIF,
            'usua_activo' => true,
        ]);

        /* La clave nunca se guarda en claro: el login la compara con Hash. */
        $hash = DB::table('usuario')->where('usua_id_usuario', $idUsuario)->value('usua_clave_acceso');
        $this->assertNotSame('clave-larga-de-prueba', $hash);
        $this->assertTrue(Hash::check('clave-larga-de-prueba', $hash));
    }

    public function test_la_bandeja_no_lista_a_las_personas_solicitantes(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.administradores.index'))
            ->assertOk()
            ->assertSee('Superusuaria Prueba Sofía')
            ->assertSee('Registro Prueba Ulises')
            ->assertSee('Pagos Prueba Delia')
            /* La bandeja administra cuentas de operación, no expedientes. */
            ->assertDontSee('Solicitante Prueba Persona');
    }

    public function test_rechaza_una_curp_ya_registrada(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.administradores.store'), $this->datosDeAlta([
                'curp' => 'UIFA900101MDFABC03',
            ]))
            ->assertSessionHasErrors('curp');

        $this->assertSame(4, DB::table('usuario')->count());
    }

    public function test_no_permite_asignar_un_rol_ajeno_al_modulo(): void
    {
        /* El formulario sólo ofrece los tres roles administrativos, pero el
           id llega por POST: manipularlo no debe convertir a nadie en Persona. */
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.administradores.store'), $this->datosDeAlta([
                'rol_id' => self::ROL_PERSONA,
            ]))
            ->assertSessionHas('error', 'Selecciona un tipo de administrador válido.');

        $this->assertDatabaseMissing('persona', ['pers_curp' => 'NUCA900101MDFXYZ01']);
    }

    public function test_la_clave_vacia_al_editar_conserva_la_anterior(): void
    {
        $hashPrevio = DB::table('usuario')->where('usua_id_usuario', 3)->value('usua_clave_acceso');

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.administradores.update', 3), $this->datosDeAlta([
                'curp' => 'UIFA900101MDFABC03',
                'nombre' => 'Ulises Corregido',
                'primer_apellido' => 'Registro',
                'segundo_apellido' => 'Prueba',
                'clave' => '',
            ]))
            ->assertRedirect(route('admin.administradores.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('persona', [
            'pers_id_usuario' => 3,
            'pers_nombre' => 'Ulises Corregido',
        ]);
        $this->assertSame(
            $hashPrevio,
            DB::table('usuario')->where('usua_id_usuario', 3)->value('usua_clave_acceso')
        );
    }

    public function test_nadie_cambia_su_propio_tipo_de_administrador(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.administradores.update', 2), $this->datosDeAlta([
                'curp' => 'SUPE900101MDFABC02',
                'nombre' => 'Sofía',
                'primer_apellido' => 'Superusuaria',
                'segundo_apellido' => 'Prueba',
                'rol_id' => self::ROL_ADMIN_DEC,
                'clave' => '',
            ]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('usuario', [
            'usua_id_usuario' => 2,
            'usua_id_rol' => self::ROL_SUPERUSUARIO,
        ]);
    }

    public function test_nadie_se_da_de_baja_a_si_mismo(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.administradores.destroy', 2))
            ->assertSessionHas('error', 'No puedes darte de baja a ti mismo.');

        $this->assertDatabaseHas('usuario', ['usua_id_usuario' => 2, 'usua_activo' => true]);
    }

    public function test_la_baja_retira_el_acceso_sin_borrar_el_renglon_y_se_puede_revertir(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.administradores.destroy', 3))
            ->assertRedirect(route('admin.administradores.index'))
            ->assertSessionHas('success', 'El administrador quedó sin acceso al sistema.');

        /* El renglón se conserva: es el rastro de lo que esa cuenta dictaminó. */
        $this->assertDatabaseHas('usuario', ['usua_id_usuario' => 3, 'usua_activo' => false]);
        $this->assertDatabaseHas('persona', ['pers_id_usuario' => 3]);

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.administradores.reactivar', 3))
            ->assertRedirect(route('admin.administradores.index'))
            ->assertSessionHas('success', 'El administrador recuperó su acceso.');

        $this->assertDatabaseHas('usuario', ['usua_id_usuario' => 3, 'usua_activo' => true]);
    }

    public function test_la_baja_de_una_cuenta_inexistente_avisa_en_vez_de_reventar(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.administradores.destroy', 999))
            ->assertSessionHas('error', 'El administrador indicado no existe.');
    }

    /**
     * Esta guarda no se alcanza por HTTP: dar de baja al único Superusuario
     * sólo puede intentarlo él mismo, y ahí salta antes la regla de que nadie
     * se da de baja solo. Se prueba contra el servicio porque es donde vive el
     * invariante, y porque el día que se relaje la regla de arriba —o que otro
     * rol reciba "Gestionar usuarios"— ésta es la que evita dejar el sistema
     * sin quien administre las cuentas.
     */
    public function test_el_servicio_no_deja_al_sistema_sin_superusuario_activo(): void
    {
        $gestion = app(GestionAdministradores::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('único Superusuario activo');

        $gestion->desactivar(2, 3);
    }

    public function test_el_servicio_tampoco_degrada_al_ultimo_superusuario(): void
    {
        $gestion = app(GestionAdministradores::class);

        try {
            $gestion->actualizar(2, $this->datosDeAlta([
                'curp' => 'SUPE900101MDFABC02',
                'rol_id' => self::ROL_ADMIN_UIF,
                'clave' => '',
            ]), 3);

            $this->fail('Degradar al último Superusuario debió lanzar DomainException.');
        } catch (DomainException $excepcion) {
            $this->assertStringContainsString('único Superusuario activo', $excepcion->getMessage());
        }

        $this->assertDatabaseHas('usuario', [
            'usua_id_usuario' => 2,
            'usua_id_rol' => self::ROL_SUPERUSUARIO,
        ]);
    }

    public function test_con_dos_superusuarios_si_se_puede_dar_de_baja_a_uno(): void
    {
        $this->crearCuenta(5, self::ROL_SUPERUSUARIO, 'SUPB900101MDFABC05', 'Sergio', 'Segundo', 'Prueba');

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.administradores.destroy', 5))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('usuario', ['usua_id_usuario' => 5, 'usua_activo' => false]);
    }

    public function test_los_filtros_acotan_la_bandeja(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.administradores.index', ['rol' => 'Admin DEC']))
            ->assertOk()
            ->assertSee('Pagos Prueba Delia')
            ->assertDontSee('Registro Prueba Ulises');

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.administradores.index', ['buscar' => 'UIFA900101']))
            ->assertOk()
            ->assertSee('Registro Prueba Ulises')
            ->assertDontSee('Pagos Prueba Delia');
    }

    /**
     * La bandeja se acota en el navegador sobre la tabla que Blade ya escribió,
     * y para eso cada renglón lleva sus valores en atributos. Si uno se cae al
     * editar la vista, el filtro deja de acotar sin avisar de nada.
     */
    public function test_la_bandeja_lleva_el_cableado_del_filtro(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.administradores.index'))
            ->assertOk()
            ->assertSee('data-filtros-tabla="admin-administradores-tabla"', false)
            ->assertSee('data-filtro-buscar="Registro Prueba Ulises UIFA900101MDFABC03"', false)
            ->assertSee('data-filtro-rol="Admin UIF"', false)
            ->assertSee('data-filtro-estatus="activos"', false)
            ->assertSee('data-filtro-orden', false)
            ->assertSee('data-tabla-vacia', false);
    }
}
