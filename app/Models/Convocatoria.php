<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Convocatoria
 *
 * Responsabilidad: el periodo de certificación con sus fechas y su cuota de
 * recuperación. En qué estado está no vive aquí sino en ESTADO_CONVOCATORIA,
 * que es una bitácora: cerrar una convocatoria agrega un renglón, no corrige
 * el anterior.
 */
class Convocatoria extends Model
{
    protected $table = 'convocatoria';

    protected $primaryKey = 'conv_id_convocatoria';

    public $timestamps = false;

    protected $fillable = [
        'conv_nombre',
        'conv_monto_recuperacion',
        'conv_fecha_inicio_registro',
        'conv_fecha_fin_registro',
        'conv_fin_fecha_entrega_docs',
        'conv_fecha_inicio',
        'conv_fecha_fin',
    ];

    protected function casts(): array
    {
        return [
            'conv_fecha_inicio_registro' => 'date:Y-m-d',
            'conv_fecha_fin_registro' => 'date:Y-m-d',
            'conv_fin_fecha_entrega_docs' => 'date:Y-m-d',
            'conv_fecha_inicio' => 'date:Y-m-d',
            'conv_fecha_fin' => 'date:Y-m-d',
        ];
    }

    /**
     * CONV_MONTO_RECUPERACION es MONEY: PostgreSQL lo devuelve formateado
     * —'$7,000.00'— y no como número. Se le quita todo lo que no sea dígito,
     * punto o signo para poder compararlo y volver a pintarlo.
     *
     * SQLite, el motor de las pruebas, no tiene MONEY y guarda la cadena tal
     * cual se escribió; la misma limpieza sirve para los dos.
     */
    public static function montoDecimal(mixed $valor): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $valor);
    }
}
