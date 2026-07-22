<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;

/**
 * PagoController
 *
 * Migrado desde: app/controllers/PagoController.php
 * Responsabilidad: gestión de pagos y comprobantes de participantes.
 */
class PagoController extends Controller
{
    public function index()
    {
        return view('participante.pago');
    }
}
