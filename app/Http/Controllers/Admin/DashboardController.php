<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ConsultaPagos;
use App\Support\Admin\ConsultaPersonasRegistradas;
use Illuminate\Support\Facades\Gate;

/**
 * Admin\DashboardController
 *
 * Migrado desde: app/controllers/admin/DashboardController.php
 * Responsabilidad: panel principal del administrador con métricas y accesos rápidos.
 *
 * El tablero lo abre cualquier administrador, pero no todos ven lo mismo: cada
 * indicador y cada acceso declara el permiso que lo destapa, y lo que no se
 * puede abrir tampoco se pinta. Enseñar una tarjeta que responde 403 al hacer
 * clic sería peor que no enseñarla.
 */
class DashboardController extends Controller
{
    public function index(
        ConsultaPersonasRegistradas $consulta_personas,
        ConsultaPagos $consulta_pagos
    ) {
        $indicadores = $this->indicadores($consulta_personas, $consulta_pagos);

        /*
         * Los accesos siguen el orden del trámite. Certificados va al final
         * por ser el último paso y todavía no tiene módulo: sin 'ruta', la
         * vista lo pinta como «Próximamente».
         */
        $acciones = [
            [
                'titulo' => 'Pre-registro',
                'ruta' => 'admin.personas.index',
                'permiso' => 'validar-registro',
                'descripcion' => 'Valida los pre-registros y documentación existentes.',
            ],
            [
                'titulo' => 'Personas registradas',
                'ruta' => 'admin.personas.registradas.index',
                'permiso' => 'validar-registro',
                'descripcion' => 'Consulta las personas registradas.',
            ],
            [
                'titulo' => 'Subir referencias bancarias',
                'ruta' => 'admin.referencias.carga',
                'permiso' => 'gestionar-referencias',
                'descripcion' => 'Carga la lista de referencias bancarias.',
            ],
            [
                'titulo' => 'Referencias bancarias',
                'ruta' => 'admin.referencias.index',
                'permiso' => 'gestionar-referencias',
                'descripcion' => 'Consulta la correspondencia de referencias bancarias.',
            ],
            [
                'titulo' => 'Pagos',
                'ruta' => 'admin.pagos.index',
                'permiso' => 'gestionar-pagos',
                'descripcion' => 'Consulta y resuelve los comprobantes de pago enviados.',
            ],
            [
                'titulo' => 'Sedes',
                'ruta' => 'admin.sedes.index',
                'permiso' => 'gestionar-sedes',
                'descripcion' => 'Gestiona las sedes activas.',
            ],
            [
                'titulo' => 'Grupos',
                'ruta' => 'admin.grupos.index',
                'permiso' => 'gestionar-sedes',
                'descripcion' => 'Programa las aplicaciones del examen en cada sede.',
            ],
            [
                'titulo' => 'Administradores',
                'ruta' => 'admin.administradores.index',
                'permiso' => 'gestionar-usuarios',
                'descripcion' => 'Da de alta y administra a quienes operan el sistema.',
            ],
            [
                'titulo' => 'Certificados',
                'permiso' => 'gestionar-usuarios',
                'descripcion' => 'Administra la emisión de certificados.',
            ],
        ];

        return view('admin.dashboard', [
            'indicadores' => $indicadores,
            'acciones' => $this->permitidas($acciones),
        ]);
    }

    /**
     * Los cuatro indicadores del encabezado, cada uno con el permiso que lo
     * destapa. Los conteos se consultan sólo si quien mira puede verlos.
     *
     * @return array<int, array>
     */
    private function indicadores(
        ConsultaPersonasRegistradas $consulta_personas,
        ConsultaPagos $consulta_pagos
    ): array {
        $indicadores = [];
        $resumen = null;
        /* Una sola consulta aunque los dos bloques la necesiten. */
        $traerResumen = function () use ($consulta_personas, &$resumen): array {
            return $resumen ??= $consulta_personas->resumenDashboard();
        };

        if (Gate::allows('validar-registro')) {
            $resumen = $traerResumen();

            $indicadores[] = [
                'titulo' => 'Personas registradas',
                'valor' => number_format($resumen['personas_registradas']),
                'clase' => 'admin-dashboard-indicador-azul',
            ];
            $indicadores[] = [
                'titulo' => 'Solicitudes en revisión',
                'valor' => number_format($resumen['solicitudes_en_revision']),
                'clase' => 'admin-dashboard-indicador-naranja',
            ];
        }

        if (Gate::allows('gestionar-pagos')) {
            $indicadores[] = [
                'titulo' => 'Pagos por validar',
                'valor' => number_format($consulta_pagos->totalPorValidar()),
                'clase' => 'admin-dashboard-indicador-naranja',
            ];
        }

        if (Gate::allows('gestionar-usuarios')) {
            $certificados = $traerResumen()['certificados_pendientes'];

            $indicadores[] = [
                'titulo' => 'Certificados pendientes',
                'valor' => is_null($certificados) ? 'Sin datos persistidos' : number_format($certificados),
                'clase' => 'admin-dashboard-indicador-verde',
                'sin_datos' => is_null($certificados),
            ];
        }

        return $indicadores;
    }

    /**
     * @param array<int, array> $acciones
     * @return array<int, array>
     */
    private function permitidas(array $acciones): array
    {
        return array_values(array_filter(
            $acciones,
            fn (array $accion): bool => Gate::allows($accion['permiso'])
        ));
    }
}
