<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ConsultaPagos;
use App\Support\Admin\ConsultaPersonasRegistradas;

/**
 * Admin\DashboardController
 *
 * Migrado desde: app/controllers/admin/DashboardController.php
 * Responsabilidad: panel principal del administrador con métricas y accesos rápidos.
 */
class DashboardController extends Controller
{
    public function index(
        ConsultaPersonasRegistradas $consulta_personas,
        ConsultaPagos $consulta_pagos
    ) {
        $resumen = $consulta_personas->resumenDashboard();
        $resumen['pagos_pendientes'] = $consulta_pagos->totalPorValidar();

        /*
         * Los accesos siguen el orden del trámite. Certificados va al final
         * por ser el último paso y todavía no tiene módulo: sin 'ruta', la
         * vista lo pinta como «Próximamente».
         */
        $acciones = [
            [
                'titulo' => 'Pre-registro',
                'ruta' => 'admin.personas.index',
                'descripcion' => 'Valida los pre-registros y documentación existentes.',
            ],
            [
                'titulo' => 'Personas registradas',
                'ruta' => 'admin.personas.registradas.index',
                'descripcion' => 'Consulta las personas registradas.',
            ],
            [
                'titulo' => 'Subir referencias bancarias',
                'ruta' => 'admin.referencias.carga',
                'descripcion' => 'Carga la lista de referencias bancarias.',
            ],
            [
                'titulo' => 'Referencias bancarias',
                'ruta' => 'admin.referencias.index',
                'descripcion' => 'Consulta la correspondencia de referencias bancarias.',
            ],
            [
                'titulo' => 'Pagos',
                'ruta' => 'admin.pagos.index',
                'descripcion' => 'Consulta y resuelve los comprobantes de pago enviados.',
            ],
            [
                'titulo' => 'Sedes',
                'ruta' => 'admin.sedes.index',
                'descripcion' => 'Gestiona las sedes activas.',
            ],
            [
                'titulo' => 'Grupos',
                'ruta' => 'admin.grupos.index',
                'descripcion' => 'Programa las aplicaciones del examen en cada sede.',
            ],
            [
                'titulo' => 'Certificados',
                'descripcion' => 'Administra la emisión de certificados.',
            ],
        ];

        return view('admin.dashboard', compact('resumen', 'acciones'));
    }
}
