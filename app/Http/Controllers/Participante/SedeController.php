<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;

/**
 * SedeController
 *
 * Migrado desde: app/controllers/SedeController.php
 * Responsabilidad: consulta y selección de sedes y horarios de certificación.
 */
class SedeController extends Controller
{
    public function index()
    {
        return view('participante.sede');
    }
}
