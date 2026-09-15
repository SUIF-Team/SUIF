<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * El personal de la DEC que atiende los pagos y aparece en «Atendido por» del
 * formato de pago. Lo administra quien gestiona pagos, y la baja nunca borra:
 * los pagos que ya atendió lo siguen referenciando.
 */
class GestionResponsablesTest extends TestCase
{
    use SiembraAdministradores;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->sembrarRolesYPrivilegios();

        $this->crearCuenta(3, self::ROL_ADMIN_UIF, 'UIFA900101MDFABC03', 'Ulises', 'Registro', 'Prueba');
        $this->crearCuenta(4, self::ROL_ADMIN_DEC, 'DECA900101MDFABC04', 'Delia', 'Pagos', 'Prueba');
    }

    public function test_solo_quien_gestiona_pagos_abre_el_modulo(): void
    {
        $this->get(route('admin.responsables.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(Usuario::findOrFail(3))
            ->get(route('admin.responsables.index'))
            ->assertForbidden();

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.responsables.index'))
            ->assertOk()
            ->assertSee('Todavía no hay responsables');
    }

    public function test_el_alta_y_la_edicion_guardan_el_nombre(): void
    {
        $this->actingAs(Usuario::findOrFail(4))
            ->post(route('admin.responsables.store'), [
                'nombre' => ' Rocío ',
                'apellido_paterno' => 'Durán',
                'apellido_materno' => '',
            ])
            ->assertRedirect(route('admin.responsables.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('responsable', [
            'resp_nombre' => 'Rocío',
            'resp_apellido_paterno' => 'Durán',
            'resp_apellido_materno' => null,
            'resp_activo' => true,
        ]);

        $id = (int) DB::table('responsable')->value('resp_id_responsable');

        $this->actingAs(Usuario::findOrFail(4))
            ->put(route('admin.responsables.update', $id), [
                'nombre' => 'Rocío',
                'apellido_paterno' => 'Durán',
                'apellido_materno' => 'Vega',
            ])
            ->assertRedirect(route('admin.responsables.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('responsable', [
            'resp_id_responsable' => $id,
            'resp_apellido_materno' => 'Vega',
        ]);

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.responsables.index'))
            ->assertOk()
            ->assertSee('Rocío Durán Vega');
    }

    public function test_la_baja_conserva_el_renglon_y_se_puede_reactivar(): void
    {
        $id = (int) DB::table('responsable')->insertGetId([
            'resp_nombre' => 'Tomás',
            'resp_apellido_paterno' => 'Ibarra',
            'resp_apellido_materno' => 'Luna',
            'resp_activo' => true,
        ], 'resp_id_responsable');

        $this->actingAs(Usuario::findOrFail(4))
            ->delete(route('admin.responsables.destroy', $id))
            ->assertRedirect(route('admin.responsables.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('responsable', ['resp_id_responsable' => $id, 'resp_activo' => false]);

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.responsables.index'))
            ->assertOk()
            ->assertSee('Inactivo')
            ->assertSee('Reactivar');

        $this->actingAs(Usuario::findOrFail(4))
            ->post(route('admin.responsables.reactivar', $id))
            ->assertRedirect(route('admin.responsables.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('responsable', ['resp_id_responsable' => $id, 'resp_activo' => true]);
    }

    public function test_el_nombre_y_el_apellido_paterno_son_obligatorios(): void
    {
        $this->actingAs(Usuario::findOrFail(4))
            ->from(route('admin.responsables.create'))
            ->post(route('admin.responsables.store'), [
                'apellido_materno' => str_repeat('A', 56),
            ])
            ->assertRedirect(route('admin.responsables.create'))
            ->assertSessionHasErrors(['nombre', 'apellido_paterno', 'apellido_materno']);

        $this->assertDatabaseCount('responsable', 0);
    }

    public function test_editar_un_responsable_inexistente_vuelve_a_la_bandeja(): void
    {
        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.responsables.edit', 99))
            ->assertRedirect(route('admin.responsables.index'))
            ->assertSessionHas('error', 'El responsable solicitado no existe.');
    }
}
