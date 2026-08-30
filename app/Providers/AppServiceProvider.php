<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Servicios\AvancePersona;
use App\Models\Usuario;
use App\Support\Admin\AccesoAdministrativo;
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

        /* Todos los permisos se resuelven contra PRIVILEGIO_ROL y ninguno
           contra el nombre del rol. Con un solo administrador daba lo mismo;
           con uno por área, comparar contra la cadena "Administrador" deja
           fuera a los demás y obliga a volver aquí cada vez que se agrega un
           rol. El privilegio es el dato que el esquema ya modelaba. */
        $permisos = [
            'validar-registro' => AccesoAdministrativo::VALIDACION_REGISTRO,
            'gestionar-pagos' => AccesoAdministrativo::GESTIONAR_PAGOS,
            'gestionar-referencias' => AccesoAdministrativo::GESTIONAR_REFERENCIAS,
            'gestionar-sedes' => AccesoAdministrativo::GESTIONAR_SEDES,
            'gestionar-usuarios' => AccesoAdministrativo::GESTIONAR_USUARIOS,
            'generar-reportes' => AccesoAdministrativo::GENERAR_REPORTES,
            'gestionar-convocatorias' => AccesoAdministrativo::GESTIONAR_CONVOCATORIAS,
            /* Revertir una resolución ya notificada le toca a quien la dictó:
               la UIF reanuda y cancela lo que dictaminó en documentación, y la
               DEC reanuda los pagos que resolvió. Son permisos con nombre
               propio aunque hoy coincidan con el privilegio de su módulo: ahí
               se separan el día que la regla cambie. */
            'reanudar-tramite' => AccesoAdministrativo::VALIDACION_REGISTRO,
            'reanudar-pago' => AccesoAdministrativo::GESTIONAR_PAGOS,
        ];

        foreach ($permisos as $permiso => $privilegio) {
            Gate::define(
                $permiso,
                fn (Usuario $usuario): bool => $usuario->tienePrivilegio($privilegio)
            );
        }

        /* La puerta de la zona administrativa. Basta un privilegio del
           catálogo para entrar; qué se puede hacer ahí dentro lo deciden los
           permisos de cada módulo. */
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
