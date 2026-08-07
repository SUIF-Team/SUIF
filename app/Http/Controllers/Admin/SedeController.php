<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * Admin\SedeController
 *
 * Migrado desde: app/controllers/admin/SedeController.php
 * Responsabilidad: alta, edición y gestión de sedes de aplicación desde el panel administrativo.
 */
class SedeController extends Controller
{
    /**
     * Muestra la pantalla base del módulo de sedes.
     */
    public function index()
    {
        return view('admin.sedes');
    }
}
