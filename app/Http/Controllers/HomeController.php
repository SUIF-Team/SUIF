<?php

namespace App\Http\Controllers;

/**
 * HomeController
 *
 * Migrado desde: app/controllers/HomeController.php
 * Responsabilidad: página principal / landing del sistema.
 */
class HomeController extends Controller
{
    /**
     * Muestra la página pública de SUIF.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('home.index');
    }
}
