<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * Lo que separa a un área de otra: qué abre cada rol, qué ve en su tablero y
 * dónde aterriza al iniciar sesión.
 *
 * Las descripciones de las tarjetas se usan como aserción en lugar de sus
 * títulos porque los títulos se solapan —"Pagos" aparece dentro de "Pagos por
 * validar"— y un assertDontSee sobre una subcadena pasaría por accidente.
 */
class AccesoAdministrativoTest extends TestCase
{
    use SiembraAdministradores;

    private const TARJETA_PREREGISTRO = 'Valida los pre-registros y documentación existentes.';

    private const TARJETA_PAGOS = 'Consulta y resuelve los comprobantes de pago enviados.';

    private const TARJETA_REFERENCIAS = 'Carga la lista de referencias bancarias.';

    private const TARJETA_SEDES = 'Gestiona las sedes activas.';

    private const TARJETA_ADMINISTRADORES = 'Da de alta y administra a quienes operan el sistema.';

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

    public function test_la_zona_administrativa_no_responde_sin_sesion(): void
    {
        /* Todas éstas respondían con 200 y sin sesión. El visor de documentos
           sirve los formatos firmados, con el RFC y la CURP del solicitante. */
        $rutas = [
            route('admin.dashboard'),
            route('admin.personas.index'),
            route('admin.personas.registradas.index'),
            route('admin.documentos.index'),
            route('admin.resultados.index'),
            route('admin.administradores.index'),
        ];

        foreach ($rutas as $ruta) {
            $this->get($ruta)->assertRedirect(route('login'));
        }

        $this->post(route('admin.documentos.validar', 1))->assertRedirect(route('login'));
    }

    public function test_una_persona_solicitante_no_pisa_la_zona_administrativa(): void
    {
        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_cada_administrador_recibe_403_en_los_modulos_de_la_otra_area(): void
    {
        $uif = Usuario::findOrFail(3);
        $dec = Usuario::findOrFail(4);

        /* La UIF dictamina expedientes; el dinero y su catálogo no son suyos. */
        $this->actingAs($uif)->get(route('admin.pagos.index'))->assertForbidden();
        $this->actingAs($uif)->get(route('admin.referencias.index'))->assertForbidden();
        $this->actingAs($uif)->get(route('admin.sedes.index'))->assertForbidden();
        $this->actingAs($uif)->get(route('admin.administradores.index'))->assertForbidden();

        /* La DEC resuelve pagos; los expedientes no. */
        $this->actingAs($dec)->get(route('admin.personas.index'))->assertForbidden();
        $this->actingAs($dec)->get(route('admin.documentos.index'))->assertForbidden();
        $this->actingAs($dec)->get(route('admin.sedes.index'))->assertForbidden();
        $this->actingAs($dec)->get(route('admin.administradores.index'))->assertForbidden();
    }

    public function test_revertir_una_resolucion_le_toca_a_quien_la_dicto(): void
    {
        $uif = Usuario::findOrFail(3);
        $dec = Usuario::findOrFail(4);

        /* Reanudar el pago es de la DEC: la UIF no lo resolvió. */
        $this->actingAs($uif)->post(route('admin.pagos.reanudar', 1))->assertForbidden();

        /* Reabrir o cancelar el expediente es de la UIF, por lo mismo. */
        $this->actingAs($dec)->post(route('admin.documentos.reanudar', 1))->assertForbidden();
        $this->actingAs($dec)->post(route('admin.documentos.cancelar', 1))->assertForbidden();
    }

    public function test_el_tablero_del_superusuario_los_pinta_todos(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(self::TARJETA_PREREGISTRO)
            ->assertSee(self::TARJETA_PAGOS)
            ->assertSee(self::TARJETA_REFERENCIAS)
            ->assertSee(self::TARJETA_SEDES)
            ->assertSee(self::TARJETA_ADMINISTRADORES)
            ->assertSee('Personas registradas')
            ->assertSee('Solicitudes en revisión')
            ->assertSee('Pagos por validar')
            ->assertSee('Certificados pendientes');
    }

    public function test_el_tablero_de_la_uif_solo_pinta_pre_registro_y_documentacion(): void
    {
        $this->actingAs(Usuario::findOrFail(3))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(self::TARJETA_PREREGISTRO)
            ->assertDontSee(self::TARJETA_PAGOS)
            ->assertDontSee(self::TARJETA_REFERENCIAS)
            ->assertDontSee(self::TARJETA_SEDES)
            ->assertDontSee(self::TARJETA_ADMINISTRADORES)
            /* Cuántos pagos faltan por validar es dato de la DEC. */
            ->assertDontSee('Pagos por validar')
            ->assertDontSee('Certificados pendientes')
            ->assertSee('Solicitudes en revisión');
    }

    public function test_el_tablero_de_la_dec_solo_pinta_pagos_y_referencias(): void
    {
        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(self::TARJETA_PAGOS)
            ->assertSee(self::TARJETA_REFERENCIAS)
            ->assertSee('Pagos por validar')
            ->assertDontSee(self::TARJETA_PREREGISTRO)
            ->assertDontSee(self::TARJETA_SEDES)
            ->assertDontSee(self::TARJETA_ADMINISTRADORES)
            ->assertDontSee('Solicitudes en revisión')
            ->assertDontSee('Certificados pendientes');
    }

    public function test_cada_quien_aterriza_donde_trabaja_al_iniciar_sesion(): void
    {
        $destinos = [
            'SUPE900101MDFABC02' => 'admin.dashboard',
            'UIFA900101MDFABC03' => 'admin.personas.index',
            'DECA900101MDFABC04' => 'admin.pagos.index',
            'PERS900101MDFABC01' => 'persona.dashboard',
        ];

        foreach ($destinos as $curp => $ruta) {
            $this->post(route('login.post'), [
                'curp' => $curp,
                'clave' => 'CLAVE-DE-PRUEBA',
            ])->assertRedirect(route($ruta));

            $this->post(route('logout'));
        }
    }

    public function test_la_baja_corta_una_sesion_ya_abierta(): void
    {
        $uif = Usuario::findOrFail(3);

        $this->actingAs($uif)
            ->get(route('admin.dashboard'))
            ->assertOk();

        /* La baja la aplica otro Superusuario mientras la sesión sigue viva.
           Los permisos se evalúan en cada petición, así que la siguiente ya no
           pasa: no hace falta esperar a que cierre sesión.

           Se comprueba contra el tablero y no contra una bandeja porque el
           tablero sólo exige `acceder-admin`: si ahí ya responde 403, la zona
           entera quedó cerrada, no un módulo suelto. */
        DB::table('usuario')->where('usua_id_usuario', 3)->update(['usua_activo' => false]);

        $this->actingAs($uif)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_una_cuenta_sin_acceso_no_puede_iniciar_sesion(): void
    {
        DB::table('usuario')->where('usua_id_usuario', 3)->update(['usua_activo' => false]);

        $this->post(route('login.post'), [
            'curp' => 'UIFA900101MDFABC03',
            'clave' => 'CLAVE-DE-PRUEBA',
        ])->assertSessionHas('error', 'Esta cuenta ya no tiene acceso al sistema.');

        $this->assertGuest();
    }
}
