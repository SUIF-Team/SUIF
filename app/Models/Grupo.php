<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'grupo';

    protected $primaryKey = 'grup_id_grupo';

    public $timestamps = false;

    protected $fillable = [
        'sede_id_sede',
        'grup_fecha_inicio',
        'grup_hora_inicio',
        'grup_fecha_fin',
        'grup_hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'grup_fecha_inicio' => 'date:Y-m-d',
            'grup_fecha_fin' => 'date:Y-m-d',
        ];
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id_sede', 'sede_id_sede');
    }

    public function evaluacion()
    {
        return $this->hasOne(Evaluacion::class, 'grup_id_grupo', 'grup_id_grupo');
    }
}
