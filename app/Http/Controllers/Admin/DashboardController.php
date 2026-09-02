<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\ReferenciaEspecial;
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
        ConsultaPagos $consulta_pagos,
        ReferenciaEspecial $referencias_especiales
    ) {
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
                'descripcion' => 'Gestiona las personas registradas.',
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
                'titulo' => 'Referencias especiales',
                'ruta' => 'admin.referencias.especiales.index',
                'permiso' => 'gestionar-referencias',
                'descripcion' => 'Emite las referencias con las que un tercero paga a varios participantes.',
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
                'titulo' => 'Convocatorias',
                'ruta' => 'admin.convocatorias.index',
                'permiso' => 'gestionar-convocatorias',
                'descripcion' => 'Define el calendario y la cuota de recuperación de cada convocatoria.',
            ],
            [
                'titulo' => 'Gestión de usuarios',
                'ruta' => 'admin.administradores.index',
                'permiso' => 'gestionar-usuarios',
                'descripcion' => 'Crea, edita y administra las cuentas de quienes operan el sistema.',
            ],
            /* Reportes va junto a Certificados y no antes: se descarga cuando
               el trámite ya ocurrió, no mientras se opera. Su permiso es una
               unión —basta poder descargar un reporte para ver la pantalla—
               porque dentro cada tarjeta se filtra otra vez. */
            [
                'titulo' => 'Reportes',
                'ruta' => 'admin.reportes.index',
                'permiso' => 'ver-reportes',
                'descripcion' => 'Descarga en Excel los pagos, los registros, las listas de grupo y los datos de facturación.',
            ],
            [
                'titulo' => 'Certificados',
                'permiso' => 'generar-reportes',
                'descripcion' => 'Administra la emisión de certificados.',
            ],
        ];

        return view('admin.dashboard', [
            'indicadores' => $this->indicadores($consulta_personas, $consulta_pagos, $referencias_especiales),
            'acciones' => $this->permitidas($acciones),
        ]);
    }

    /**
     * Los indicadores del encabezado, cada uno con el permiso que lo destapa.
     *
     * El conteo se consulta sólo si quien mira puede verlo: cuántos pagos
     * faltan por validar es dato de la DEC, y no tiene por qué aparecer en el
     * tablero de quien revisa documentación.
     *
     * @return array<int, array<string, mixed>>
     */
    private function indicadores(
        ConsultaPersonasRegistradas $consulta_personas,
        ConsultaPagos $consulta_pagos,
        ReferenciaEspecial $referencias_especiales
    ): array {
        $indicadores = [];
        $resumen = null;

        /* Dos bloques distintos pueden necesitarlo; se consulta una sola vez. */
        $traerResumen = function () use ($consulta_personas, &$resumen): array {
            return $resumen ??= $consulta_personas->resumenDashboard();
        };

        if (Gate::allows('validar-registro')) {
            $datos = $traerResumen();

            $indicadores[] = [
                'titulo' => 'Personas registradas',
                'valor' => number_format($datos['personas_registradas']),
                'clase' => 'admin-dashboard-indicador-azul',
                'sin_datos' => false,
            ];
            $indicadores[] = [
                'titulo' => 'Solicitudes en revisión',
                'valor' => number_format($datos['solicitudes_en_revision']),
                'clase' => 'admin-dashboard-indicador-naranja',
                'sin_datos' => false,
            ];
        }

        if (Gate::allows('gestionar-pagos')) {
            $indicadores[] = [
                'titulo' => 'Pagos por validar',
                'valor' => number_format($consulta_pagos->totalPorValidar()),
                'clase' => 'admin-dashboard-indicador-naranja',
                'sin_datos' => false,
            ];
        }

        /* La solicitud de una referencia especial no llega por ningún otro
           lado: si el tablero no la anuncia, la empresa se queda esperando. */
        if (Gate::allows('gestionar-referencias')) {
            $indicadores[] = [
                'titulo' => 'Referencias especiales por emitir',
                'valor' => number_format($referencias_especiales->totalPendientes()),
                'clase' => 'admin-dashboard-indicador-naranja',
                'sin_datos' => false,
            ];
        }

        if (Gate::allows('generar-reportes')) {
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
     * @param array<int, array<string, mixed>> $acciones
     * @return array<int, array<string, mixed>>
     */
    private function permitidas(array $acciones): array
    {
        return array_values(array_filter(
            $acciones,
            fn (array $accion): bool => Gate::allows($accion['permiso'])
        ));
    }
}
