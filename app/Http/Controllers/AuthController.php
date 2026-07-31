<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
 *   - @csrf en las vistas Blade (en lugar de core/Csrf.php)
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

    /**
     * Cierra la sesión actual y vuelve al formulario de acceso.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->route('login');
    }
}
