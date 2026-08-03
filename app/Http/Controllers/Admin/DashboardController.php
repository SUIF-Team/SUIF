<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ParticipanteRegistradoDatosPrueba;

/**
 * Admin\DashboardController
 *
 * Migrado desde: app/controllers/admin/DashboardController.php
 * Responsabilidad: panel principal del administrador con métricas y accesos rápidos.
 */
class DashboardController extends Controller
{
    /**
     * Muestra el resumen provisional del panel administrativo.
     *
     * Los indicadores se sustituirán por datos persistidos cuando se apruebe
     * e implemente la base de datos del sistema.
     *
     * @return \Illuminate\View\View
     */
    public function index(ParticipanteRegistradoDatosPrueba $datos_prueba)
    {
        $resumen = [
            'participantes_registrados' => count($datos_prueba->participantes()),
            'preregistros_pendientes' => 0,
            'pagos_pendientes' => 0,
            'certificados_pendientes' => 0,
        ];

        /*
         * Se conservan los accesos del diseño aprobado. Ninguno se habilita
         * hasta que su módulo tenga controlador, autorización y flujo listos.
         */
        $acciones = [
            [
                'titulo' => 'Pre-registro',
                'ruta' => 'admin.participantes.index',
                'descripcion' => 'Valida los pre-registros y documentación existentes.',
            ],
            [
                'titulo' => 'Pagos',
                'ruta' => 'admin.pagos.index',
                'descripcion' => 'Gestiona los pagos realizados.',
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
                'titulo' => 'Participantes registrados',
                'ruta' => 'admin.participantes.registrados.index',
                'descripcion' => 'Consulta los participantes registrados.',
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
