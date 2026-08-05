<?php

namespace App\Support\Admin;

class ParticipanteRegistradoDatosPrueba
{
    /**
     * Obtiene participantes temporales para la bandeja administrativa.
     *
     * Esta fuente puede sustituirse después por un repositorio respaldado por base de datos.
     */
    public function participantes(): array
    {
        return [
            [
                'id' => 'jordan-carrillo-guevara',
                'nombre' => 'Jordan',
                'primer_apellido' => 'Carrillo',
                'segundo_apellido' => 'Guevara',
                'nombre_completo' => 'Jordan Carrillo Guevara',
                'curp' => 'EOJKHZHIU87T6788IUJDJKBDHG',
                'fecha_registro' => '2026-08-02 14:30:00',
                'etapa' => 'Evaluación',
                'estado' => 'Correcto',
                'clase_estado' => 'admin-bandeja-preregistros-estado-aceptado',
            ],
            [
                'id' => 'maria-fernanda-lopez-castillo',
                'nombre' => 'María Fernanda',
                'primer_apellido' => 'López',
                'segundo_apellido' => 'Castillo',
                'nombre_completo' => 'María Fernanda López Castillo',
                'curp' => 'LOCM900315MDFPSTR02',
                'fecha_registro' => '2026-08-01 09:15:00',
                'etapa' => 'Documentación',
                'estado' => 'Pendiente de validación',
                'clase_estado' => 'admin-bandeja-preregistros-estado-revision',
            ],
            [
                'id' => 'luis-alberto-reyes-mendoza',
                'nombre' => 'Luis Alberto',
                'primer_apellido' => 'Reyes',
                'segundo_apellido' => 'Mendoza',
                'nombre_completo' => 'Luis Alberto Reyes Mendoza',
                'curp' => 'REML880921HDFYNS07',
                'fecha_registro' => '2026-07-30 16:45:00',
                'etapa' => 'Pago',
                'estado' => 'En proceso',
                'clase_estado' => 'admin-bandeja-preregistros-estado-proceso',
            ],
            [
                'id' => 'claudia-hernandez-ruiz',
                'nombre' => 'Claudia',
                'primer_apellido' => 'Hernández',
                'segundo_apellido' => 'Ruiz',
                'nombre_completo' => 'Claudia Hernández Ruiz',
                'curp' => 'HERC920614MDFRZL05',
                'fecha_registro' => '2026-07-28 11:20:00',
                'etapa' => 'Evaluación',
                'estado' => 'Con incidencia',
                'clase_estado' => 'admin-bandeja-preregistros-estado-rechazado',
            ],
            [
                'id' => 'diego-morales-cruz',
                'nombre' => 'Diego',
                'primer_apellido' => 'Morales',
                'segundo_apellido' => 'Cruz',
                'nombre_completo' => 'Diego Morales Cruz',
                'curp' => 'MOCD950408HDFRRG03',
                'fecha_registro' => '2026-07-26 08:00:00',
                'etapa' => 'Pre-registro',
                'estado' => 'Correcto',
                'clase_estado' => 'admin-bandeja-preregistros-estado-aceptado',
            ],
        ];
    }
}
