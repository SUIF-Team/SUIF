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
     * El contenido sale de la configuración y no de un script de página: así
     * la landing llega pintada desde el servidor, sin plantilla que sustituir
     * después de cargar.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('home.index', [
            'tarjetas' => config('suif.landing.tarjetas', []),
            'pasos' => config('suif.landing.pasos', []),
            'preguntas' => config('suif.landing.preguntas', []),
        ]);
    }
}
