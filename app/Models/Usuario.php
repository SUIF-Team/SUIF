<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

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

    public function tienePrivilegio(string $privilegio): bool
    {
        return DB::table('privilegio_rol as pr')
            ->join('privilegio as p', 'p.priv_id_privilegio', '=', 'pr.ropr_id_privilegio')
            ->where('pr.ropr_id_rol', $this->usua_id_rol)
            ->where('p.priv_privilegio', $privilegio)
            ->exists();
    }
}
