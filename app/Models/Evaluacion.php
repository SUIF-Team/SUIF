<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    protected $table = 'evaluacion';

    protected $primaryKey = 'eval_id_evaluacion';

    public $timestamps = false;

    protected $fillable = [
        'grup_id_grupo',
        'eval_resultado',
    ];

    protected function casts(): array
    {
        return [
            'eval_resultado' => 'integer',
        ];
    }
}
