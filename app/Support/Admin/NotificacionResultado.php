<?php

namespace App\Support\Admin;

use Illuminate\Support\Facades\Gate;

class NotificacionResultado
{
    public function paraPreRegistro(array $persona, array $estados): array
    {
        $resultado = $estados['resultado'] ?? null;
        $es_rechazo_preregistro = $resultado === 'rechazado'
            && ($estados['preregistro'] ?? null) === 'Rechazado';
        $es_cancelado = $resultado === RevisionDocumentos::CANCELADO;
        /* Una cancelación histórica cierra el trámite igual que un rechazo:
           comparten títulos en negativo y la paleta roja del tablero. */
        $es_rechazo = $resultado === 'rechazado' || $es_cancelado;
        $es_aprobado = $resultado === 'aprobado';

        return [
            'persona' => $this->persona($persona),
            'titulo_pagina' => $es_cancelado
                ? 'SUIF — Solicitud cancelada'
                : ($es_rechazo
                    ? 'SUIF — Solicitud rechazada'
                    : ($es_aprobado ? 'SUIF — Solicitud aprobada' : 'SUIF — Solicitud en revisión')),
            'titulo' => $es_cancelado
                ? 'SOLICITUD CANCELADA'
                : ($es_rechazo
                    ? 'SOLICITUD RECHAZADA'
                    : ($es_aprobado ? 'SOLICITUD APROBADA' : 'SOLICITUD EN REVISIÓN')),
            'estado_general' => $es_cancelado
                ? 'Cancelado'
                : ($es_rechazo
                    ? 'Rechazado'
                    : ($es_aprobado ? 'Aprobado' : 'En revisión')),
            'clase_estado' => $es_rechazo
                ? 'admin-preregistro-estado--rechazado'
                : ($es_aprobado
                    ? 'admin-preregistro-estado--completado'
                    : 'admin-preregistro-estado--revision'),
            'clase_mensaje' => $es_rechazo
                ? 'admin-preregistro-paso--rechazado'
                : ($es_aprobado
                    ? 'admin-preregistro-paso--completado'
                    : 'admin-preregistro-paso--actual'),
            'pasos' => [
                $this->paso(
                    'Pre-registro',
                    $estados['preregistro'] ?? 'Pendiente',
                    $this->clasePaso($estados['preregistro'] ?? 'Pendiente'),
                    $es_rechazo_preregistro
                ),
                $this->paso(
                    'Documentación',
                    $estados['documentacion'] ?? 'Pendiente',
                    $this->clasePaso($estados['documentacion'] ?? 'Pendiente'),
                    ! $es_rechazo_preregistro
                ),
            ],
            'clase_progreso' => 'admin-preregistro-progreso--dos-pasos',
            'ruta_regreso' => route('admin.personas.index'),
            'etiqueta_regreso' => 'Volver a la bandeja',
            'etiqueta_regreso_accesible' => 'Volver a la bandeja',
            'contexto' => $es_rechazo_preregistro ? 'preregistro' : 'documentacion',
            'acciones' => $this->permitidas($this->accionesDocumentacion(
                (string) ($persona['id'] ?? ''),
                $resultado
            )),
        ];
    }

    public function paraPago(array $pago): array
    {
        $es_aprobado = $pago['estado_persistido'] === ConsultaPagos::COMPLETADO;

        return [
            'persona' => [
                'iniciales' => $pago['iniciales'],
                'nombre_completo' => $pago['nombre_completo'],
                'curp' => $pago['curp'],
                'entidad_federativa' => $pago['entidad_federativa'],
            ],
            'titulo_pagina' => $es_aprobado ? 'SUIF — Pago aprobado' : 'SUIF — Pago rechazado',
            'titulo' => $es_aprobado ? 'PAGO APROBADO' : 'PAGO RECHAZADO',
            'estado_general' => $es_aprobado ? 'Aprobado' : 'Rechazado',
            'clase_estado' => $es_aprobado
                ? 'admin-preregistro-estado--completado'
                : 'admin-preregistro-estado--rechazado',
            'clase_mensaje' => $es_aprobado
                ? 'admin-preregistro-paso--completado'
                : 'admin-preregistro-paso--rechazado',
            'pasos' => [
                $this->paso('Pre-registro', 'Completado', 'admin-preregistro-paso--completado'),
                $this->paso('Documentación', 'Completado', 'admin-preregistro-paso--completado'),
                $this->paso(
                    'Pago',
                    $es_aprobado ? 'Aprobado' : 'Rechazado',
                    $es_aprobado ? 'admin-preregistro-paso--completado' : 'admin-preregistro-paso--rechazado',
                    true
                ),
            ],
            'clase_progreso' => 'admin-preregistro-progreso--tres-pasos',
            'ruta_regreso' => route('admin.pagos.index'),
            'etiqueta_regreso' => 'Volver a la bandeja',
            'etiqueta_regreso_accesible' => 'Volver a la bandeja',
            'contexto' => 'pago',
            'acciones' => $this->permitidas([$this->accionReanudarPago((string) $pago['id'])]),
        ];
    }

    /**
     * Acción para devolver un pago resuelto a la bandeja de revisión.
     *
     * La usan tanto la pantalla de resultado como el detalle en sólo lectura:
     * desde la bandeja, "Ver pago" lleva al detalle, no al resultado.
     *
     * @return array{ruta: string, etiqueta: string, titulo_modal: string, texto_modal: string, id: string}
     */
    public function accionReanudarPago(string $id_pago): array
    {
        return [
            'id' => 'reanudar-pago',
            'permiso' => 'reanudar-pago',
            'ruta' => route('admin.pagos.reanudar', ['id' => $id_pago]),
            'etiqueta' => 'Reanudar revisión del pago',
            'titulo_modal' => '¿Reanudar la revisión de este pago?',
            'texto_modal' => 'El pago volverá a "En revisión" y podrás validarlo o rechazarlo de nuevo. La resolución anterior se conserva en el historial.',
        ];
    }

    /**
     * Deja sólo las acciones que quien mira puede ejecutar.
     *
     * Cada acción declara su permiso porque revertir le toca a quien dictó la
     * resolución: la documentación la reabre la UIF y el pago la DEC. Antes
     * bastaba un permiso para las dos, cuando había un solo administrador.
     *
     * El filtro duplica a propósito el middleware de las rutas: quien no puede
     * revertir tampoco debería ver el botón.
     *
     * @param array<int, array<string, string>> $acciones
     * @return array<int, array<string, string>>
     */
    private function permitidas(array $acciones): array
    {
        return array_values(array_filter(
            $acciones,
            fn (array $accion): bool => Gate::allows($accion['permiso'])
        ));
    }

    /**
     * Acciones disponibles sobre un expediente según cómo haya quedado.
     *
     * @return array<int, array{ruta: string, etiqueta: string, titulo_modal: string, texto_modal: string, id: string}>
     */
    private function accionesDocumentacion(string $id_solicitud, ?string $resultado): array
    {
        if ($id_solicitud === '') {
            return [];
        }

        $acciones = [];

        /* Sólo se reanuda lo que ya está resuelto; mientras el expediente
           espera subsanación no hay resolución que revertir. CANCELADO
           sigue en la lista por el historial: ya no se pueden cancelar
           trámites, pero los que se cancelaron deben poder reabrirse. */
        if (in_array($resultado, [
            RevisionDocumentos::APROBADO,
            RevisionDocumentos::RECHAZADO,
            RevisionDocumentos::CANCELADO,
        ], true)) {
            $acciones[] = [
                'id' => 'reanudar-tramite',
                'permiso' => 'reanudar-tramite',
                'ruta' => route('admin.documentos.reanudar', ['id' => $id_solicitud]),
                'etiqueta' => 'Reanudar trámite',
                'titulo_modal' => '¿Reanudar este trámite?',
                'texto_modal' => 'El expediente completo volverá a revisión y tendrás que dictaminar de nuevo cada documento. El historial anterior se conserva.',
            ];
        }

        return $acciones;
    }

    private function persona(array $persona): array
    {
        return [
            'iniciales' => mb_strtoupper(
                mb_substr($persona['nombre'], 0, 1)
                .mb_substr($persona['primer_apellido'], 0, 1)
            ),
            'nombre_completo' => $persona['nombre_completo'],
            'curp' => $persona['curp'],
            'entidad_federativa' => $persona['entidad_federativa'],
        ];
    }

    private function paso(
        string $titulo,
        string $estado,
        string $clase,
        bool $actual = false
    ): array {
        return compact('titulo', 'estado', 'clase', 'actual');
    }

    private function clasePaso(string $estado): string
    {
        return match ($estado) {
            'Completado' => 'admin-preregistro-paso--completado',
            'En revisión' => 'admin-preregistro-paso--actual',
            'Rechazado', 'Cancelado' => 'admin-preregistro-paso--rechazado',
            default => 'admin-preregistro-paso--pendiente',
        };
    }
}
