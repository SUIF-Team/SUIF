<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * User
 *
 * Migrado desde: app/models/User.php
 * Extiende Authenticatable (que extiende Eloquent Model) para soportar autenticación de Laravel.
 *
 * @property int    $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $remember_token
 */
class User extends Authenticatable
{
    use Notifiable;

    /**
     * Los atributos asignables en masa.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * Los atributos que deben ocultarse en arrays/JSON.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
}
