<?php

namespace App\Support\Admin;

class OrigenBandejaAdmin
{
    public const PREREGISTROS = 'preregistros';

    /**
     * Devuelve la bandeja de origen y su destino administrativo asociado.
     * Hoy la única bandeja es la de pre-registros.
     */
    public function contexto(): array
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
