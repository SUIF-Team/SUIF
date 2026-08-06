<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;

/**
 * Responsabilidad: consulta de resultados publicados para la persona.
 */
class ResultadoController extends Controller
{
    public function resultados()
    {
        return view('persona.resultados');
    }
}
