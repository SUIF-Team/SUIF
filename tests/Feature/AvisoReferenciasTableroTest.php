<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\CatalogoReferencias;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SiembraAdministradores;
use Tests\TestCase;

/**
 * El tablero avisa cuando quedan pocas referencias que se puedan entregar,
 * para que la DEC tramite el siguiente paquete antes de que se acaben.
 */
class AvisoReferenciasTableroTest extends TestCase
{
    use SiembraAdministradores;

    private const AVISO = 'es momento de tramitar nuevas referencias';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearEsquemaAdministrativo();
        $this->sembrarRolesYPrivilegios();

        $this->crearCuenta(4, self::ROL_ADMIN_DEC, 'DECA900101MDFABC04', 'Delia', 'Pagos', 'Prueba');
    }

    public function test_en_el_limite_la_tarjeta_se_pone_en_rojo_y_lleva_a_subir_referencias(): void
    {
        $this->sembrarEntregables(CatalogoReferencias::MINIMO_ENTREGABLES);

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Referencias listas para entregar')
            ->assertSee('admin-dashboard-indicador--peligro', false)
            ->assertSee(self::AVISO)
            ->assertSee(route('admin.referencias.carga'));
    }

    public function test_por_encima_del_limite_no_hay_aviso(): void
    {
        $this->sembrarEntregables(CatalogoReferencias::MINIMO_ENTREGABLES + 1);

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Referencias listas para entregar')
            ->assertDontSee('admin-dashboard-indicador--peligro', false)
            ->assertDontSee(self::AVISO);
    }

    /**
     * Una libre sin su PDF no se puede entregar, así que no cuenta: con ella
     * el tablero diría que sobran referencias que nadie puede obtener.
     */
    public function test_las_libres_sin_formato_no_alejan_el_aviso(): void
    {
        $this->sembrarEntregables(CatalogoReferencias::MINIMO_ENTREGABLES);

        DB::table('referencia_bancaria')->insert([
            'reba_referencia' => 'SINFORMATO',
            'reba_path' => null,
        ]);

        $this->actingAs(Usuario::findOrFail(4))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(self::AVISO);
    }

    private function sembrarEntregables(int $cuantas): void
    {
        for ($i = 1; $i <= $cuantas; $i++) {
            DB::table('referencia_bancaria')->insert([
                'reba_referencia' => 'REF'.$i,
                'reba_path' => CatalogoReferencias::CARPETA_FORMATOS.'/REF'.$i.'.pdf',
            ]);
        }
    }
}
