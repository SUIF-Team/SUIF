<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persona
 *
 * Responsabilidad: datos personales de la persona (tabla PERSONA).
 * Aquí vive la CURP con la que la persona inicia sesión.
 */
class Persona extends Model
{
    protected $table = 'persona';

    protected $primaryKey = 'pers_id_persona';

    public $timestamps = false;

    protected $fillable = [
        'pers_clave_inegi',
        'pers_id_usuario',
        'pers_curp',
        'pers_rfc',
        'pers_nombre',
        'pers_apellido_paterno',
        'pers_apellido_materno',
        'pers_fecha_registro',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'pers_id_usuario', 'usua_id_usuario');
    }

    /**
     * Arma el nombre completo para mostrarlo en las pantallas.
     */
    public function nombreCompleto()
    {
        return trim($this->pers_nombre.' '.$this->pers_apellido_paterno.' '.$this->pers_apellido_materno);
    }
}
