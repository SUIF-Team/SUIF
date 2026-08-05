<?php

namespace App\Support\Admin;

class NotificacionResultado
{
    public function paraPreRegistro(array $participante, array $estados): array
    {
        $resultado = $estados['resultado'] ?? null;
        $es_rechazo_preregistro = $resultado === 'rechazado'
            && ($estados['preregistro'] ?? null) === 'Rechazado';
        $es_rechazo = $resultado === 'rechazado';
        $es_aprobado = $resultado === 'aprobado';

        return [
            'participante' => $this->participante($participante),
            'titulo_pagina' => $es_rechazo
                ? 'SUIF — Solicitud rechazada'
                : ($es_aprobado ? 'SUIF — Solicitud aprobada' : 'SUIF — Solicitud en revisión'),
            'titulo' => $es_rechazo
                ? 'SOLICITUD RECHAZADA'
                : ($es_aprobado ? 'SOLICITUD APROBADA' : 'SOLICITUD EN REVISIÓN'),
            'estado_general' => $es_rechazo
                ? 'Rechazado'
                : ($es_aprobado ? 'Aprobado' : 'En revisión'),
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
            'ruta_regreso' => route('admin.participantes.index'),
            'etiqueta_regreso' => 'Volver a la bandeja',
            'etiqueta_regreso_accesible' => 'Volver a la bandeja',
            'contexto' => $es_rechazo_preregistro ? 'preregistro' : 'documentacion',
        ];
    }

    public function paraPago(array $pago): array
    {
        return [
            'participante' => [
                'iniciales' => $pago['iniciales'],
                'nombre_completo' => $pago['nombre_completo'],
                'curp' => $pago['curp'],
                'folio' => $pago['folio'],
                'entidad_federativa' => $pago['entidad_federativa'],
            ],
            'titulo_pagina' => 'SUIF — Pago rechazado',
            'titulo' => 'PAGO RECHAZADO',
            'estado_general' => 'Rechazado',
            'clase_estado' => 'admin-preregistro-estado--rechazado',
            'clase_mensaje' => 'admin-preregistro-paso--rechazado',
            'pasos' => [
                $this->paso('Pre-registro', 'Completado', 'admin-preregistro-paso--completado'),
                $this->paso('Documentación', 'Completado', 'admin-preregistro-paso--completado'),
                $this->paso('Pago', 'Rechazado', 'admin-preregistro-paso--rechazado', true),
            ],
            'clase_progreso' => 'admin-preregistro-progreso--tres-pasos',
            'ruta_regreso' => route('admin.pagos.index'),
            'etiqueta_regreso' => 'Volver a la bandeja',
            'etiqueta_regreso_accesible' => 'Volver a la bandeja',
            'contexto' => 'pago',
        ];
    }

    private function participante(array $participante): array
    {
        return [
            'iniciales' => mb_strtoupper(
                mb_substr($participante['nombre'], 0, 1)
                .mb_substr($participante['primer_apellido'], 0, 1)
            ),
            'nombre_completo' => $participante['nombre_completo'],
            'curp' => $participante['curp'],
            'folio' => $participante['folio'],
            'entidad_federativa' => $participante['entidad_federativa'],
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
            'Rechazado' => 'admin-preregistro-paso--rechazado',
            default => 'admin-preregistro-paso--pendiente',
        };
    }
}
