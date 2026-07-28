<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;

/**
 * Responsabilidad: consulta de resultados publicados para el participante.
 */
class ResultadoController extends Controller
{
    public function resultados()
    {
        return view('participante.resultados');
    }
}
