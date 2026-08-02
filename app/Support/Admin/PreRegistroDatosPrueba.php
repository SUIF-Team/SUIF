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
                'nombre_completo' => 'Jordan Carrillo Guevara',
                'fecha_registro' => '2026-07-29 14:30:00',
                'estado_bandeja' => 'En revisión',
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
            'maria-fernanda-lopez-castillo' => [
                'id' => 'maria-fernanda-lopez-castillo',
                'nombre' => 'María Fernanda',
                'primer_apellido' => 'López',
                'segundo_apellido' => 'Castillo',
                'nombre_completo' => 'María Fernanda López Castillo',
                'fecha_registro' => '2026-07-27 09:15:00',
                'estado_bandeja' => 'En revisión',
                'curp' => 'LOCM900315MDFPSTR02',
                'correo_principal' => 'maria.lopez@example.test',
                'correo_alterno' => 'maria.castillo@example.test',
                'telefono' => '5512345678',
                'entidad_federativa' => 'Ciudad de México',
                'folio' => 'FCA-2026-01841',
                'ultimo_grado_estudios' => 'Licenciatura',
                'actividad_vulnerable' => 'No',
                'responsable_cumplimiento' => 'No',
                'documentos' => [],
            ],
            'luis-alberto-reyes-mendoza' => [
                'id' => 'luis-alberto-reyes-mendoza',
                'nombre' => 'Luis Alberto',
                'primer_apellido' => 'Reyes',
                'segundo_apellido' => 'Mendoza',
                'nombre_completo' => 'Luis Alberto Reyes Mendoza',
                'fecha_registro' => '2026-07-25 16:45:00',
                'estado_bandeja' => 'Aceptado',
                'curp' => 'REML880921HDFYNS07',
                'correo_principal' => 'luis.reyes@example.test',
                'correo_alterno' => 'luis.mendoza@example.test',
                'telefono' => '5587654321',
                'entidad_federativa' => 'Estado de México',
                'folio' => 'FCA-2026-01840',
                'ultimo_grado_estudios' => 'Maestría',
                'actividad_vulnerable' => 'Sí',
                'responsable_cumplimiento' => 'Sí',
                'documentos' => [],
            ],
            'claudia-hernandez-ruiz' => [
                'id' => 'claudia-hernandez-ruiz',
                'nombre' => 'Claudia',
                'primer_apellido' => 'Hernández',
                'segundo_apellido' => 'Ruiz',
                'nombre_completo' => 'Claudia Hernández Ruiz',
                'fecha_registro' => '2026-07-23 11:20:00',
                'estado_bandeja' => 'Rechazado',
                'curp' => 'HERC920614MDFRZL05',
                'correo_principal' => 'claudia.hernandez@example.test',
                'correo_alterno' => 'claudia.ruiz@example.test',
                'telefono' => '5543210987',
                'entidad_federativa' => 'Puebla',
                'folio' => 'FCA-2026-01839',
                'ultimo_grado_estudios' => 'Licenciatura',
                'actividad_vulnerable' => 'No',
                'responsable_cumplimiento' => 'No',
                'documentos' => [],
            ],
            'diego-morales-cruz' => [
                'id' => 'diego-morales-cruz',
                'nombre' => 'Diego',
                'primer_apellido' => 'Morales',
                'segundo_apellido' => 'Cruz',
                'nombre_completo' => 'Diego Morales Cruz',
                'fecha_registro' => '2026-07-21 08:00:00',
                'estado_bandeja' => 'En revisión',
                'curp' => 'MOCD950408HDFRRG03',
                'correo_principal' => 'diego.morales@example.test',
                'correo_alterno' => 'diego.cruz@example.test',
                'telefono' => '5598761234',
                'entidad_federativa' => 'Jalisco',
                'folio' => 'FCA-2026-01838',
                'ultimo_grado_estudios' => 'Especialidad',
                'actividad_vulnerable' => 'Sí',
                'responsable_cumplimiento' => 'Sí',
                'documentos' => [],
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
