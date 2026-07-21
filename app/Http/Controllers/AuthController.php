<?php

namespace App\Http\Controllers;

/**
 * AuthController
 *
 * Migrado desde: app/controllers/AuthController.php
 * Responsabilidad: autenticación de usuarios (login, logout, registro).
 *
 * TODO: implementar lógica de negocio usando facades nativas de Laravel:
 *   - Illuminate\Support\Facades\Auth  (en lugar de core/Auth.php)
 *   - Illuminate\Support\Facades\Session (en lugar de core/Session.php)
 *   - Illuminate\Support\Facades\Validator (en lugar de core/Validator.php)
 *   - Directiva @csrf en las vistas Blade (en lugar de core/Csrf.php)
 */
class AuthController extends Controller
{
    /**
     * Muestra el punto de entrada para la futura pantalla de acceso.
     *
     * @return \Illuminate\View\View
     */
    public function showLogin()
    {
        return view('auth.login');
    }
}
