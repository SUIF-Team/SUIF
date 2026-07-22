<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;

/**
 * ReferenciaController
 *
 * Migrado desde: app/controllers/ReferenciaController.php
 * Responsabilidad: generación y consulta de referencias bancarias de pago.
 */
class ReferenciaController extends Controller
{
    public function index()
    {
        return view('participante.referencia');
    }
}
