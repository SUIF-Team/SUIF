<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $table = 'sede';

    protected $primaryKey = 'sede_id_sede';

    public $timestamps = false;

    protected $fillable = [
        'sede_nombre',
        'sede_direccion',
        'sede_cupo',
        'sede_estado',
    ];

    protected function casts(): array
    {
        return [
            'sede_cupo' => 'integer',
            'sede_estado' => 'boolean',
        ];
    }

    public function grupo()
    {
        return $this->hasOne(Grupo::class, 'sede_id_sede', 'sede_id_sede');
    }
}
