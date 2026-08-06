<?php

namespace App\Support\Admin;

class PagoDatosPrueba
{
    /**
     * Obtiene los comprobantes temporales disponibles para revisión administrativa.
     *
     * Esta fuente puede sustituirse después por un repositorio respaldado por base de datos.
     */
    public function pagos(): array
    {
        return [
            [
                'id' => 'jordan-carrillo-guevara',
                'nombre' => 'Jordan',
                'primer_apellido' => 'Carrillo',
                'segundo_apellido' => 'Guevara',
                'nombre_completo' => 'Jordan Carrillo Guevara',
                'iniciales' => 'JC',
                'curp' => 'CAGJ900315HDFRVR01',
                'estatus' => 'Por revisar',
                'fecha_envio_comprobante' => '2026-08-01 16:30:00',
                'entidad_federativa' => 'Ciudad de México',
                'estado_preregistro' => 'Completado',
                'estado_documentacion' => 'Completado',
                'comprobante' => [
                    'nombre' => 'ComprobantePago-JC.pdf',
                    'identificador' => 'comprobante-jordan-2026-01845',
                ],
                'monto' => '$2,500.00 MXN',
                'referencia_bancaria' => '9988 7766 5544',
                'banco' => 'BBVA',
                'fecha_pago' => '2026-06-12',
            ],
            [
                'id' => 'maria-fernanda-lopez-castillo',
                'nombre' => 'María Fernanda',
                'primer_apellido' => 'López',
                'segundo_apellido' => 'Castillo',
                'nombre_completo' => 'María Fernanda López Castillo',
                'iniciales' => 'ML',
                'curp' => 'LOCM900315MDFPSTR02',
                'estatus' => 'Rechazado',
                'fecha_envio_comprobante' => '2026-07-31 11:15:00',
                'entidad_federativa' => 'Estado de México',
                'estado_preregistro' => 'En revisión',
                'estado_documentacion' => 'Completado',
                'comprobante' => [
                    'nombre' => 'ComprobantePago-ML.pdf',
                    'identificador' => 'comprobante-maria-2026-01844',
                ],
                'monto' => '$2,500.00 MXN',
                'referencia_bancaria' => '9988 7766 5533',
                'banco' => 'Santander',
                'fecha_pago' => '2026-06-11',
            ],
            [
                'id' => 'luis-alberto-reyes-mendoza',
                'nombre' => 'Luis Alberto',
                'primer_apellido' => 'Reyes',
                'segundo_apellido' => 'Mendoza',
                'nombre_completo' => 'Luis Alberto Reyes Mendoza',
                'iniciales' => 'LR',
                'curp' => 'REML880921HDFYNS07',
                'estatus' => 'Aprobado',
                'fecha_envio_comprobante' => '2026-07-30 09:45:00',
                'entidad_federativa' => 'Puebla',
                'estado_preregistro' => 'Completado',
                'estado_documentacion' => 'Aprobado',
                'comprobante' => [
                    'nombre' => 'ComprobantePago-LR.pdf',
                    'identificador' => 'comprobante-luis-2026-01843',
                ],
                'monto' => '$2,500.00 MXN',
                'referencia_bancaria' => '9988 7766 5522',
                'banco' => 'Banorte',
                'fecha_pago' => '2026-06-10',
            ],
            [
                'id' => 'claudia-hernandez-ruiz',
                'nombre' => 'Claudia',
                'primer_apellido' => 'Hernández',
                'segundo_apellido' => 'Ruiz',
                'nombre_completo' => 'Claudia Hernández Ruiz',
                'iniciales' => 'CH',
                'curp' => 'HERC920614MDFRZL05',
                'estatus' => 'Por revisar',
                'fecha_envio_comprobante' => '2026-07-29 14:20:00',
                'entidad_federativa' => 'Querétaro',
                'estado_preregistro' => 'Completado',
                'estado_documentacion' => 'Rechazado',
                'comprobante' => [
                    'nombre' => 'ComprobantePago-CH.pdf',
                    'identificador' => 'comprobante-claudia-2026-01842',
                ],
                'monto' => '$2,500.00 MXN',
                'referencia_bancaria' => '9988 7766 5511',
                'banco' => 'HSBC',
                'fecha_pago' => '2026-06-09',
            ],
            [
                'id' => 'diego-morales-cruz',
                'nombre' => 'Diego',
                'primer_apellido' => 'Morales',
                'segundo_apellido' => 'Cruz',
                'nombre_completo' => 'Diego Morales Cruz',
                'iniciales' => 'DM',
                'curp' => 'MOCD950408HDFRRG03',
                'estatus' => 'Por revisar',
                'fecha_envio_comprobante' => '2026-07-27 08:00:00',
                'entidad_federativa' => 'Hidalgo',
                'estado_preregistro' => 'Completado',
                'estado_documentacion' => 'Completado',
                'comprobante' => null,
                'monto' => '$2,500.00 MXN',
                'referencia_bancaria' => '9988 7766 5500',
                'banco' => 'Scotiabank',
                'fecha_pago' => '2026-06-08',
            ],
        ];
    }

    public function pago(string $id): ?array
    {
        foreach ($this->pagos() as $pago) {
            if ($pago['id'] === $id) {
                return $pago;
            }
        }

        return null;
    }

    /**
     * Obtiene el mensaje de bloqueo cuando un pago no cumple los requisitos
     * para abrirse en revisión administrativa.
     */
    public function mensajeNoDisponibleParaRevision(array $pago): ?string
    {
        if (($pago['estado_preregistro'] ?? null) !== 'Completado') {
            return 'El pago no puede revisarse porque el pre-registro no está completado.';
        }

        if (!in_array($pago['estado_documentacion'] ?? null, ['Completado', 'Aprobado'], true)) {
            return 'El pago no puede revisarse porque la documentación no está aprobada.';
        }

        if (empty($pago['comprobante']['identificador'])) {
            return 'El pago no puede revisarse porque no cuenta con comprobante disponible.';
        }

        if (($pago['estatus'] ?? null) !== 'Por revisar') {
            return 'El pago no está disponible para revisión administrativa.';
        }

        return null;
    }
}
