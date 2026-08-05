<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Servicios\AvanceParticipante;
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
            /* La barra de avance recibe siempre el avance real del participante. */
        View::composer('partials.sidebar-progreso', function ($view) {
            $view->with('avance', new AvanceParticipante(auth()->id()));
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
