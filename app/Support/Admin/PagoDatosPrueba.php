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
                'nombre_completo' => 'Jordan Carrillo Guevara',
                'iniciales' => 'JC',
                'curp' => 'CAGJ900315HDFRVR01',
                'folio' => 'PAG-2026-01845',
                'estatus' => 'Por revisar',
                'fecha_envio_comprobante' => '2026-08-01 16:30:00',
            ],
            [
                'id' => 'maria-fernanda-lopez-castillo',
                'nombre_completo' => 'María Fernanda López Castillo',
                'iniciales' => 'ML',
                'curp' => 'LOCM900315MDFPSTR02',
                'folio' => 'PAG-2026-01844',
                'estatus' => 'Rechazado',
                'fecha_envio_comprobante' => '2026-07-31 11:15:00',
            ],
            [
                'id' => 'luis-alberto-reyes-mendoza',
                'nombre_completo' => 'Luis Alberto Reyes Mendoza',
                'iniciales' => 'LR',
                'curp' => 'REML880921HDFYNS07',
                'folio' => 'PAG-2026-01843',
                'estatus' => 'Aprobado',
                'fecha_envio_comprobante' => '2026-07-30 09:45:00',
            ],
            [
                'id' => 'claudia-hernandez-ruiz',
                'nombre_completo' => 'Claudia Hernández Ruiz',
                'iniciales' => 'CH',
                'curp' => 'HERC920614MDFRZL05',
                'folio' => 'PAG-2026-01842',
                'estatus' => 'Por revisar',
                'fecha_envio_comprobante' => '2026-07-29 14:20:00',
            ],
            [
                'id' => 'diego-morales-cruz',
                'nombre_completo' => 'Diego Morales Cruz',
                'iniciales' => 'DM',
                'curp' => 'MOCD950408HDFRRG03',
                'folio' => 'PAG-2026-01841',
                'estatus' => 'Aprobado',
                'fecha_envio_comprobante' => '2026-07-27 08:00:00',
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
}
