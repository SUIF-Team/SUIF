<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rol
 *
 * Responsabilidad: catálogo de roles del sistema (tabla ROL).
 */
class Rol extends Model
{
    protected $table = 'rol';

    protected $primaryKey = 'rol_id_rol';

    public $timestamps = false;

    protected $fillable = [
        'rol_tipo_rol',
    ];
}