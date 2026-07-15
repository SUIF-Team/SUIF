<?php

namespace App\Http\Middleware;

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
        if (Auth::guard($guard)->check()) {
            return redirect('/participante/dashboard');
        }

        return $next($request);
    }
}
