<?php

namespace App\Http\Middleware;

use App\Support\Admin\AccesoAdministrativo;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * RedirectIfAuthenticated
 *
 * Middleware 'guest': redirige al panel si el usuario ya está autenticado.
 * Reemplaza la verificación manual con core/Auth.php.
 */
class RedirectIfAuthenticated
{
    public function __construct(private AccesoAdministrativo $acceso)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        /* El destino es el mismo que decide el login: mandar a un
           administrador al tablero de la persona lo dejaba en una pantalla
           que no le corresponde. */
        if (Auth::guard($guard)->check()) {
            return redirect()->route($this->acceso->rutaInicial(Auth::guard($guard)->user()));
        }

        return $next($request);
    }
}
