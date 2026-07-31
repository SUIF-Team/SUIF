<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * FacturacionController
 *
 * Migrado desde: app/controllers/FacturacionController.php
 * Responsabilidad: generación y gestión de facturas.
 */
class FacturacionController extends Controller
{
    public function index()
    {
        return view('participante.facturacion');
    }

    public function store(Request $request)
    {
        // TODO: guardar datos fiscales y generar la factura/ticket.
        return redirect()->route('participante.facturacion.index');
    }
}
