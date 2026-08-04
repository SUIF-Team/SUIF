<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuario
 *
 * Responsabilidad: credenciales de acceso al sistema (tabla USUARIO).
 * La CURP no vive aquí sino en PERSONA; el acceso se resuelve cruzando ambas.
 */
class Usuario extends Authenticatable
{
    protected $table = 'usuario';

    protected $primaryKey = 'usua_id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'usua_id_rol',
        'usua_clave_acceso',
    ];

    protected $hidden = [
        'usua_clave_acceso',
    ];

    /**
     * Laravel busca por omisión una columna llamada "password".
     * Aquí la clave se guarda en usua_clave_acceso, así que se lo indicamos.
     */
    public function getAuthPassword()
    {
        return $this->usua_clave_acceso;
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'usua_id_rol', 'rol_id_rol');
    }

    public function persona()
    {
        return $this->hasOne(Persona::class, 'pers_id_usuario', 'usua_id_usuario');
    }
}