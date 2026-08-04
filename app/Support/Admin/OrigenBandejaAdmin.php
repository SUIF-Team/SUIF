<?php

namespace App\Support\Admin;

class OrigenBandejaAdmin
{
    public const PREREGISTROS = 'preregistros';

    public const PARTICIPANTES_REGISTRADOS = 'participantes_registrados';

    /**
     * Devuelve un origen permitido y su destino administrativo asociado.
     */
    public function contexto(?string $origen): array
    {
        $origen_normalizado = $origen === self::PARTICIPANTES_REGISTRADOS
            ? self::PARTICIPANTES_REGISTRADOS
            : self::PREREGISTROS;

        if ($origen_normalizado === self::PARTICIPANTES_REGISTRADOS) {
            return [
                'origen' => $origen_normalizado,
                'ruta' => route('admin.participantes.registrados.index'),
                'etiqueta' => 'Volver a la bandeja',
                'etiqueta_accesible' => 'Volver a la bandeja de participantes registrados',
            ];
        }

        return [
            'origen' => $origen_normalizado,
            'ruta' => route('admin.participantes.index'),
            'etiqueta' => 'Volver a la bandeja de pre-registros',
            'etiqueta_accesible' => 'Volver a la bandeja de pre-registros',
        ];
    }
}
