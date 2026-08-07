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
}
