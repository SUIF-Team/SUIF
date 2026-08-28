<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RutasPersonaTest extends TestCase
{
    public function test_refactor_expone_rutas_persona_y_retira_las_rutas_participante(): void
    {
        $this->assertTrue(Route::has('persona.dashboard'));
        $this->assertTrue(Route::has('persona.preregistro.index'));
        $this->assertTrue(Route::has('admin.personas.index'));
        $this->assertTrue(Route::has('admin.personas.registradas.index'));

        $this->assertFalse(Route::has('participante.dashboard'));
        $this->assertFalse(Route::has('admin.participantes.index'));
        $this->assertSame('/persona/dashboard', parse_url(route('persona.dashboard'), PHP_URL_PATH));
        $this->assertSame(
            '/admin/personas-registradas',
            parse_url(route('admin.personas.registradas.index'), PHP_URL_PATH)
        );
    }

    public function test_el_paso_de_referencia_expone_el_selector_y_el_camino_individual(): void
    {
        $this->assertTrue(Route::has('persona.referencia.index'));
        $this->assertTrue(Route::has('persona.referencia.individual'));

        /* El selector se queda con la URI de siempre para que los enlaces ya
           existentes —barra de avance, tablero, documentación— entren por él. */
        $this->assertSame('/persona/referencia', parse_url(route('persona.referencia.index'), PHP_URL_PATH));
        $this->assertSame(
            '/persona/referencia/individual',
            parse_url(route('persona.referencia.individual'), PHP_URL_PATH)
        );

        /* El flujo especial todavía no existe: mientras no exista, no se anuncia. */
        $this->assertFalse(Route::has('persona.referencia.especial'));
    }

    public function test_las_rutas_de_desarrollo_quedaron_retiradas(): void
    {
        /* reiniciar mutaba la sesión por GET y demo apuntaba a un método
           inexistente: ninguna debe volver a registrarse. */
        $this->assertFalse(Route::has('persona.preregistro.reiniciar'));
        $this->assertFalse(Route::has('persona.resultados.demo'));

        /* Sin ruta registrada el 404 se resuelve antes que el middleware auth. */
        $this->get('/persona/preregistro/reiniciar')->assertNotFound();
        $this->get('/persona/resultados/demo/aprobado')->assertNotFound();
    }
}
