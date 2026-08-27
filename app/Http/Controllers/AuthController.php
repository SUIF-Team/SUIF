<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Persona;
use App\Support\Admin\AccesoAdministrativo;
use Illuminate\Support\Facades\Hash;

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
     * Valida la CURP y la clave de acceso, y abre la sesión de la persona.
     */
    public function login(Request $request, AccesoAdministrativo $acceso)
    {
        $datos = $this->validate($request, [
            'curp' => 'required|string|size:18',
            'clave' => 'required|string',
        ], [
            'curp.required' => 'Escribe tu CURP.',
            'curp.size' => 'La CURP debe tener 18 caracteres.',
            'clave.required' => 'Escribe tu clave de acceso.',
        ]);

        $persona = Persona::where('pers_curp', strtoupper($datos['curp']))->first();

        /* Un solo mensaje para los tres casos: no se revela si la CURP existe,
           ni si existe pero quedó sin acceso. Una cuenta dada de baja no tiene
           por qué anunciarse. */
        if (!$persona || !$persona->usuario
            || !$persona->usuario->tieneAcceso()
            || !Hash::check($datos['clave'], $persona->usuario->usua_clave_acceso)) {
            return back()
                ->withInput($request->only('curp'))
                ->with('error', 'La CURP o la clave de acceso no son correctas.');
        }

        Auth::login($persona->usuario);
        $request->session()->regenerate();

        /* Cada quien entra a lo suyo: quien sólo valida pagos no pasa por un
           tablero que no puede usar. */
        return redirect()->route($acceso->rutaInicial($persona->usuario));
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
