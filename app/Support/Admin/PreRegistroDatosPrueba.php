<?php

namespace App\Support\Admin;

class PreRegistroDatosPrueba
{
    /**
     * Obtiene los participantes temporales disponibles para revisión administrativa.
     *
     * Esta fuente puede sustituirse después por un repositorio respaldado por base de datos.
     */
    public function participantes(): array
    {
        return [
            'jordan-carrillo-guevara' => [
                'id' => 'jordan-carrillo-guevara',
                'nombre' => 'Jordan',
                'primer_apellido' => 'Carrillo',
                'segundo_apellido' => 'Guevara',
                'curp' => 'EOJKHZHIU87T6788IUJDJKBDHG',
                'correo_principal' => 'jcarrillo@gmail.com',
                'correo_alterno' => 'jguevara@gmail.com',
                'telefono' => '5598745632',
                'entidad_federativa' => 'Ciudad de México',
                'folio' => 'FCA-2026-01842',
                'ultimo_grado_estudios' => 'Licenciatura',
                'actividad_vulnerable' => 'Sí',
                'responsable_cumplimiento' => 'Sí',
                'documentos' => [
                    ['id' => 'solicitud-firmada', 'titulo' => 'Solicitud firmada'],
                    ['id' => 'aceptacion-notificaciones', 'titulo' => 'Aceptación de notificaciones'],
                    ['id' => 'carta-bajo-protesta', 'titulo' => 'Carta bajo protesta'],
                    ['id' => 'autorizacion-publicacion', 'titulo' => 'Autorización para la publicación'],
                    ['id' => 'curp', 'titulo' => 'CURP'],
                    ['id' => 'identificacion-oficial', 'titulo' => 'Identificación oficial'],
                ],
            ],
        ];
    }

    public function participante(string $id): ?array
    {
        return $this->participantes()[$id] ?? null;
    }

    public function estadoInicial(): array
    {
        return [
            'general' => 'En revisión',
            'preregistro' => 'En revisión',
            'documentacion' => 'Pendiente',
        ];
    }

    public function estadoAceptado(): array
    {
        return [
            'general' => 'En revisión',
            'preregistro' => 'Completado',
            'documentacion' => 'En revisión',
        ];
    }
}
