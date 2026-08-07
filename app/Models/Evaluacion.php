<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    protected $table = 'evaluacion';

    protected $primaryKey = 'eval_id_evaluacion';

    public $timestamps = false;

    protected $fillable = [
        'eval_id_sede',
        'eval_fecha_inicio',
        'eval_hora_inicio',
        'eval_fecha_fin',
        'eval_hora_fin',
        'eval_resultado',
    ];

    protected function casts(): array
    {
        return [
            'eval_fecha_inicio' => 'date:Y-m-d',
            'eval_fecha_fin' => 'date:Y-m-d',
            'eval_resultado' => 'integer',
        ];
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'eval_id_sede', 'sede_id_sede');
    }
}
