<?php

namespace App\Http\Controllers\Persona;

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
        return view('persona.facturacion');
    }

    public function store(Request $request)
    {
        // TODO: guardar datos fiscales y generar la factura/ticket.
        return redirect()->route('persona.facturacion.index');
    }
}
