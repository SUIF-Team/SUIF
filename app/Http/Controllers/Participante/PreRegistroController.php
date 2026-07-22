<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;

/**
 * PreRegistroController
 *
 * Migrado desde: app/controllers/PreRegistroController.php
 * Responsabilidad: flujo de pre-registro de candidatos a certificación.
 */
class PreRegistroController extends Controller
{
    public function create()
    {
        return view('participante.preregistro');
    }
}
