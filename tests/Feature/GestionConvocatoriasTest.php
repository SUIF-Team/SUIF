<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\GestionConvocatorias;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * El módulo de convocatorias: quién lo abre, qué guarda y cómo trata la
 * bitácora de estados.
 *
 * Lo que más se comprueba aquí no es el CRUD sino las dos reglas que no están
 * en el esquema y sí en el servicio: que sólo haya una convocatoria vigente y
 * que el historial se acumule en vez de corregirse.
 */
class GestionConvocatoriasTest extends TestCase
{
    use SiembraAdministradores;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->sembrarRolesYPrivilegios();
        $this->sembrarEstadosConvocatoria();

        $this->crearCuenta(1, self::ROL_PERSONA, 'PERS900101MDFABC01', 'Persona', 'Solicitante', 'Prueba');
        $this->crearCuenta(2, self::ROL_SUPERUSUARIO, 'SUPE900101MDFABC02', 'Sofía', 'Superusuaria', 'Prueba');
        $this->crearCuenta(3, self::ROL_ADMIN_UIF, 'UIFA900101MDFABC03', 'Ulises', 'Registro', 'Prueba');
    }

    public function test_la_bandeja_es_del_superusuario_y_de_nadie_mas(): void
    {
        $this->get(route('admin.convocatorias.index'))->assertRedirect(route('login'));

        $this->actingAs(Usuario::findOrFail(1))
            ->get(route('admin.convocatorias.index'))
            ->assertForbidden();

        /* El Admin UIF sí es administrador —entra a la zona— pero no tiene el
           privilegio del módulo. Es la distinción que separa acceder-admin de
           los permisos de cada pantalla. */
        $this->actingAs(Usuario::findOrFail(3))
            ->get(route('admin.convocatorias.index'))
            ->assertForbidden();

        $this->actingAs(Usuario::findOrFail(2))
            ->get(route('admin.convocatorias.index'))
            ->assertOk();
    }

    public function test_el_alta_guarda_las_siete_columnas_y_deja_la_convocatoria_vigente(): void
    {
        $respuesta = $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria());

        $respuesta
            ->assertRedirect(route('admin.convocatorias.index'))
            ->assertSessionHas('success', 'La convocatoria se creó correctamente y quedó vigente.');

        $id = (int) DB::table('convocatoria')->value('conv_id_convocatoria');

        $this->assertDatabaseHas('convocatoria', [
            'conv_id_convocatoria' => $id,
            'conv_nombre' => 'Certificación 2027',
            'conv_monto_recuperacion' => '7500.00',
            'conv_fecha_inicio' => '2027-01-01',
            'conv_fecha_inicio_registro' => '2027-01-15',
            'conv_fecha_fin_registro' => '2027-03-31',
            'conv_fin_fecha_entrega_docs' => '2027-04-30',
            'conv_fecha_fin' => '2027-12-31',
        ]);

        $estado = DB::table('estado_convocatoria')->where('esco_id_convocatoria', $id)->first();

        $this->assertSame(1, DB::table('estado_convocatoria')->count());
        $this->assertSame($this->idEstado('Vigente'), (int) $estado->esco_id_c_estado_convocatoria);
        $this->assertSame(now()->toDateString(), substr((string) $estado->esco_fecha, 0, 10));
        $this->assertNotEmpty($estado->esco_hora);
    }

    public function test_no_se_puede_dejar_una_segunda_convocatoria_vigente(): void
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria());

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria([
                'nombre' => 'Certificación 2028',
            ]))
            ->assertSessionHas('error', '«Certificación 2027» sigue vigente. Ciérrala o interrúmpela antes de dejar otra convocatoria vigente.');

        $this->assertSame(1, DB::table('convocatoria')->count());
    }

    public function test_cerrar_agrega_un_movimiento_y_conserva_el_anterior(): void
    {
        $id = $this->crearConvocatoriaVigente();

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.estado', $id), ['estado' => 'Cerrada'])
            ->assertRedirect(route('admin.convocatorias.index'))
            ->assertSessionHas('success', 'La convocatoria quedó en estado «Cerrada».');

        $movimientos = DB::table('estado_convocatoria')
            ->where('esco_id_convocatoria', $id)
            ->orderBy('esco_id_estado_convocatoria')
            ->pluck('esco_id_c_estado_convocatoria')
            ->map(fn ($id_estado): int => (int) $id_estado)
            ->all();

        $this->assertSame([$this->idEstado('Vigente'), $this->idEstado('Cerrada')], $movimientos);

        /* Con la vigente cerrada, la siguiente ya cabe. */
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria(['nombre' => 'Certificación 2028']))
            ->assertSessionHas('success');
    }

    public function test_pasar_una_convocatoria_al_estado_en_que_ya_esta_no_es_un_cambio(): void
    {
        $id = $this->crearConvocatoriaVigente();

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.estado', $id), ['estado' => 'Vigente'])
            ->assertSessionHas('error', 'La convocatoria ya está en estado «Vigente».');

        $this->assertSame(1, DB::table('estado_convocatoria')->count());
    }

    public function test_la_edicion_corrige_los_datos_sin_tocar_la_bitacora(): void
    {
        $id = $this->crearConvocatoriaVigente();

        $this->actingAs(Usuario::findOrFail(2))
            ->put(route('admin.convocatorias.update', $id), $this->datosConvocatoria([
                'nombre' => 'Certificación 2027 (corregida)',
                'monto' => '8000',
            ]))
            ->assertRedirect(route('admin.convocatorias.index'))
            ->assertSessionHas('success', 'La convocatoria se actualizó correctamente.');

        $this->assertDatabaseHas('convocatoria', [
            'conv_id_convocatoria' => $id,
            'conv_nombre' => 'Certificación 2027 (corregida)',
            'conv_monto_recuperacion' => '8000.00',
        ]);

        $this->assertSame(1, DB::table('estado_convocatoria')->count());
    }

    public function test_el_calendario_se_valida_completo(): void
    {
        $usuario = Usuario::findOrFail(2);

        $this->actingAs($usuario)
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria([
                'fecha_fin' => '2026-12-31',
            ]))
            ->assertSessionHasErrors('fecha_fin');

        $this->actingAs($usuario)
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria([
                'fecha_fin_registro' => '2027-01-01',
            ]))
            ->assertSessionHasErrors('fecha_fin_registro');

        /* El límite de documentos cae después del término de la convocatoria. */
        $this->actingAs($usuario)
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria([
                'fin_fecha_entrega_docs' => '2028-01-15',
            ]))
            ->assertSessionHasErrors('fin_fecha_entrega_docs');

        $this->assertSame(0, DB::table('convocatoria')->count());
    }

    public function test_una_convocatoria_con_solicitudes_no_se_elimina(): void
    {
        $id = $this->crearConvocatoriaVigente();

        DB::table('solicitud')->insert([
            'soli_id_persona' => 1,
            'soli_id_convocatoria' => $id,
        ]);

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.convocatorias.destroy', $id))
            ->assertSessionHas('error', 'No es posible eliminar una convocatoria que ya tiene solicitudes. Ciérrala o interrúmpela.');

        $this->assertDatabaseHas('convocatoria', ['conv_id_convocatoria' => $id]);
    }

    public function test_una_convocatoria_sin_solicitudes_se_elimina_con_su_bitacora(): void
    {
        $id = $this->crearConvocatoriaVigente();

        $this->actingAs(Usuario::findOrFail(2))
            ->delete(route('admin.convocatorias.destroy', $id))
            ->assertRedirect(route('admin.convocatorias.index'))
            ->assertSessionHas('success', 'La convocatoria se eliminó correctamente.');

        $this->assertSame(0, DB::table('convocatoria')->count());
        $this->assertSame(0, DB::table('estado_convocatoria')->count());
    }

    /**
     * La consulta que alimenta al pre-registro. Interrumpir una convocatoria la
     * saca de circulación aunque su ventana de fechas siga abierta: es lo que
     * le da sentido a poder interrumpirla.
     */
    public function test_una_convocatoria_interrumpida_deja_de_estar_abierta_a_registro(): void
    {
        $id = $this->crearConvocatoriaVigente([
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_inicio_registro' => now()->subDay()->toDateString(),
            'fecha_fin_registro' => now()->addMonth()->toDateString(),
            'fin_fecha_entrega_docs' => now()->addMonths(2)->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(),
        ]);

        $gestion = app(GestionConvocatorias::class);

        $this->assertSame($id, $gestion->idConvocatoriaAbierta());

        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.estado', $id), ['estado' => 'Interrumpida'])
            ->assertSessionHas('success');

        $this->assertNull($gestion->idConvocatoriaAbierta());

        /* Y volver a abrirla la devuelve a circulación: el historial acumula
           los tres movimientos. */
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.estado', $id), ['estado' => 'Vigente'])
            ->assertSessionHas('success');

        $this->assertSame($id, $gestion->idConvocatoriaAbierta());
        $this->assertSame(3, DB::table('estado_convocatoria')->count());
    }

    public function test_una_convocatoria_vigente_fuera_de_su_ventana_no_admite_registro(): void
    {
        $this->crearConvocatoriaVigente([
            'fecha_inicio' => now()->addMonth()->toDateString(),
            'fecha_inicio_registro' => now()->addMonths(2)->toDateString(),
            'fecha_fin_registro' => now()->addMonths(3)->toDateString(),
            'fin_fecha_entrega_docs' => now()->addMonths(4)->toDateString(),
            'fecha_fin' => now()->addMonths(5)->toDateString(),
        ]);

        $this->assertNull(app(GestionConvocatorias::class)->idConvocatoriaAbierta());
    }

    /* ── Apoyos ─────────────────────────────────────────────────────────── */

    private function sembrarEstadosConvocatoria(): void
    {
        DB::table('c_estado_convocatoria')->insert([
            ['esco_id_c_estado_convocatoria' => 1, 'esco_estado_convocatoria' => 'Vigente'],
            ['esco_id_c_estado_convocatoria' => 2, 'esco_estado_convocatoria' => 'Cerrada'],
            ['esco_id_c_estado_convocatoria' => 3, 'esco_estado_convocatoria' => 'Interrumpida'],
        ]);
    }

    private function idEstado(string $estado): int
    {
        return (int) DB::table('c_estado_convocatoria')
            ->where('esco_estado_convocatoria', $estado)
            ->value('esco_id_c_estado_convocatoria');
    }

    /**
     * @param array<string, mixed> $cambios
     * @return array<string, mixed>
     */
    private function datosConvocatoria(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Certificación 2027',
            'monto' => '7500',
            'fecha_inicio' => '2027-01-01',
            'fecha_inicio_registro' => '2027-01-15',
            'fecha_fin_registro' => '2027-03-31',
            'fin_fecha_entrega_docs' => '2027-04-30',
            'fecha_fin' => '2027-12-31',
        ], $cambios);
    }

    /**
     * @param array<string, mixed> $cambios
     */
    private function crearConvocatoriaVigente(array $cambios = []): int
    {
        $this->actingAs(Usuario::findOrFail(2))
            ->post(route('admin.convocatorias.store'), $this->datosConvocatoria($cambios));

        return (int) DB::table('convocatoria')->orderByDesc('conv_id_convocatoria')->value('conv_id_convocatoria');
    }
}
