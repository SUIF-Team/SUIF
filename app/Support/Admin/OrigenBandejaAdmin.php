<?php

namespace App\Support\Admin;

class OrigenBandejaAdmin
{
    public const PREREGISTROS = 'preregistros';

    /**
     * Devuelve un origen permitido y su destino administrativo asociado.
     */
    public function contexto(?string $origen): array
    {
        return [
            'origen' => self::PREREGISTROS,
            'ruta' => route('admin.personas.index'),
            'etiqueta' => 'Atrás',
            'etiqueta_accesible' => 'Atrás',
            /* Al cerrar la revisión ya no hay expediente al cual regresar: la
               pantalla de resultado nombra la bandeja de la que se vino. */
            'etiqueta_resultado' => 'Volver a la bandeja de pre-registro',
        ];
    }
}
