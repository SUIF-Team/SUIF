<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Servicios\AvancePersona;
use App\Models\Usuario;
use App\Support\Admin\AccesoAdministrativo;
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
        /* Todos los permisos se resuelven contra PRIVILEGIO_ROL y ninguno
           contra el nombre del rol. Con un solo administrador daba igual;
           con uno por área, comparar contra la cadena "Administrador" deja
           fuera a los demás y obliga a tocar este archivo cada vez que se
           agrega un rol. El privilegio es el dato que ya modela la base. */
        $privilegios = [
            'validar-registro' => AccesoAdministrativo::VALIDACION_REGISTRO,
            'gestionar-pagos' => AccesoAdministrativo::GESTIONAR_PAGOS,
            'gestionar-referencias' => AccesoAdministrativo::GESTIONAR_REFERENCIAS,
            'gestionar-sedes' => AccesoAdministrativo::GESTIONAR_SEDES,
            'gestionar-usuarios' => AccesoAdministrativo::GESTIONAR_USUARIOS,
            'generar-reportes' => AccesoAdministrativo::GENERAR_REPORTES,
            /* Revertir una resolución ya notificada le toca a quien la dictó:
               el área que valida el registro reanuda y cancela trámites, y la
               que revisa el dinero reanuda pagos. Son permisos con nombre
               propio aunque hoy coincidan con el privilegio de su módulo:
               ahí se separan el día que la regla cambie. */
            'reanudar-tramite' => AccesoAdministrativo::VALIDACION_REGISTRO,
            'reanudar-pago' => AccesoAdministrativo::GESTIONAR_PAGOS,
        ];

        foreach ($privilegios as $permiso => $privilegio) {
            Gate::define($permiso, fn (Usuario $usuario): bool => $usuario->tienePrivilegio($privilegio));
        }

        /* La puerta de la zona administrativa. Basta un privilegio del
           catálogo para entrar; qué se puede hacer ahí dentro lo deciden
           los permisos de cada módulo. */
        Gate::define('acceder-admin', function (Usuario $usuario): bool {
            return app(AccesoAdministrativo::class)->esAdministrador($usuario);
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
