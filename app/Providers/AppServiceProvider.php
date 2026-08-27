<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Servicios\AvancePersona;
use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /* El login se ataca por CURP: el primer límite frena la fuerza bruta
           sobre una cuenta y el segundo los barridos de CURPs desde una
           misma dirección. */
        RateLimiter::for('login', function (Request $request) {
            $curp = mb_strtoupper(trim((string) $request->input('curp')), 'UTF-8');

            return [
                Limit::perMinute(5)->by('login:'.$curp.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        /* El alta de pre-registro es pública, crea cuentas y envía correo:
           sin freno permite registros masivos. */
        RateLimiter::for('preregistro', function (Request $request) {
            return Limit::perMinute(5)->by('preregistro:'.$request->ip());
        });

        /* Recuperar la clave es público, envía correo y revoca la clave
           vigente: sin freno permite barrer CURPs o bombardear a una
           persona con restablecimientos. */
        RateLimiter::for('recuperar-clave', function (Request $request) {
            return Limit::perMinute(5)->by('recuperar-clave:'.$request->ip());
        });

        Gate::define('gestionar-pagos', function (Usuario $usuario): bool {
            return $usuario->tienePrivilegio('Gestionar Pagos');
        });

        Gate::define('gestionar-usuarios', function (Usuario $usuario): bool {
            return $usuario->tienePrivilegio('Gestionar usuarios');
        });

        Gate::define('gestionar-sedes', function (Usuario $usuario): bool {
            return $usuario->rol?->rol_tipo_rol === 'Administrador';
        });

        Gate::define('gestionar-referencias', function (Usuario $usuario): bool {
            return $usuario->rol?->rol_tipo_rol === 'Administrador';
        });

        /* Revertir una resolución ya notificada es privilegiado: sólo el rol
           de Administrador puede reanudar o cancelar un trámite. */
        Gate::define('reanudar-tramite', function (Usuario $usuario): bool {
            return $usuario->rol?->rol_tipo_rol === 'Administrador';
        });

            /* La barra de avance recibe siempre el avance real de la persona. */
        View::composer('partials.sidebar-progreso', function ($view) {
            $view->with('avance', new AvancePersona(auth()->id()));
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
