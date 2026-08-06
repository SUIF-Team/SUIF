<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ConsultaPersonasRegistradas;

/**
 * Admin\DashboardController
 *
 * Migrado desde: app/controllers/admin/DashboardController.php
 * Responsabilidad: panel principal del administrador con métricas y accesos rápidos.
 */
class DashboardController extends Controller
{
    public function index(ConsultaPersonasRegistradas $consulta_personas)
    {
        $resumen = $consulta_personas->resumenDashboard();

        /*
         * Se conservan los accesos del diseño aprobado. Ninguno se habilita
         * hasta que su módulo tenga controlador, autorización y flujo listos.
         */
        $acciones = [
            [
                'titulo' => 'Pre-registro',
                'ruta' => 'admin.personas.index',
                'descripcion' => 'Valida los pre-registros y documentación existentes.',
            ],
            [
                'titulo' => 'Pagos',
                'descripcion' => 'Disponible cuando el flujo de pagos persista datos reales.',
            ],
            [
                'titulo' => 'Referencias bancarias',
                'descripcion' => 'Consulta la correspondencia de referencias bancarias.',
            ],
            [
                'titulo' => 'Certificados',
                'descripcion' => 'Administra la emisión de certificados.',
            ],
            [
                'titulo' => 'Personas registradas',
                'ruta' => 'admin.personas.registradas.index',
                'descripcion' => 'Consulta las personas registradas.',
            ],
            [
                'titulo' => 'Subir referencias bancarias',
                'descripcion' => 'Carga la lista de referencias bancarias.',
            ],
            [
                'titulo' => 'Sedes',
                'descripcion' => 'Gestiona las sedes activas.',
            ],
        ];

        return view('admin.dashboard', compact('resumen', 'acciones'));
    }
}
