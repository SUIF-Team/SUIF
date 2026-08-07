<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Servicios\AvancePersona;
use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;
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
        Gate::define('gestionar-pagos', function (Usuario $usuario): bool {
            return $usuario->tienePrivilegio('Gestionar Pagos');
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
