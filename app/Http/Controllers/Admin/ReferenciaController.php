<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * Admin\ReferenciaController
 *
 * Migrado desde: app/controllers/admin/ReferenciaController.php
 * Responsabilidad: gestión de referencias bancarias desde el panel administrativo.
 */
class ReferenciaController extends Controller
{
    /**
     * Muestra la pantalla base del módulo de referencias.
     */
    public function index()
    {
        return view('admin.referencias');
    }
}
