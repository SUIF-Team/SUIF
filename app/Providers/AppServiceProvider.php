<?php

namespace App\Providers;

use App\Autorizacion\AccesoAdministrativo;
use App\Models\Usuario;
use App\Servicios\AvancePersona;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        /* Recuperar la clave es público y envía correo. Ya no revoca la clave
           vigente —sólo manda un enlace—, pero sin freno permitiría barrer
           CURPs desde una dirección o llenarle el buzón a una persona desde
           muchas. El límite por CURP sólo aplica cuando hay CURP: un envío
           vacío no llega a la base y no debe gastar el cupo de nadie. */
        RateLimiter::for('recuperar-clave', function (Request $request) {
            $curp = mb_strtoupper(trim((string) $request->input('curp')), 'UTF-8');
            $limites = [Limit::perMinute(5)->by('recuperar-clave:'.$request->ip())];

            if ($curp !== '') {
                $limites[] = Limit::perHour(3)->by('recuperar-clave-curp:'.$curp);
            }

            return $limites;
        });

        /* El autollenado devuelve el nombre de quien trae esa CURP. Es un dato
           personal servido a una sesión válida: el tope por minuto sobra para
           teclear —una CURP no se escribe en dos segundos— y el de la hora
           cubre cuatro listas completas del máximo de participantes, así que
           deja trabajar y no deja barrer el padrón. */
        RateLimiter::for('buscar-persona', function (Request $request) {
            $identidad = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perMinute(30)->by('buscar-persona:'.$identidad),
                Limit::perHour(200)->by('buscar-persona-hora:'.$identidad),
            ];
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

        /* La puerta del trámite, simétrica a la de arriba: un administrador
           que abre /persona recibe 403 igual que una persona que abre /admin.
           Una cuenta dada de baja tampoco entra, aunque conserve la sesión. */
        Gate::define('acceder-persona', function (Usuario $usuario): bool {
            return app(AccesoAdministrativo::class)->esPersona($usuario);
        });

        /* La pantalla de reportes es de todas las áreas y de ninguna: cada
           reporte lleva dentro los datos de un módulo distinto y exige el
           permiso de ese módulo. Este permiso sólo abre la puerta; lo que se
           ve una vez dentro lo decide otra vez el permiso de cada reporte.

           No reutiliza 'generar-reportes' porque ese privilegio es el de
           certificados y resultados de examen, que no aparecen aquí. */
        Gate::define('ver-reportes', function (Usuario $usuario): bool {
            foreach ([
                AccesoAdministrativo::VALIDACION_REGISTRO,
                AccesoAdministrativo::GESTIONAR_PAGOS,
                AccesoAdministrativo::GESTIONAR_SEDES,
            ] as $privilegio) {
                if ($usuario->tienePrivilegio($privilegio)) {
                    return true;
                }
            }

            return false;
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
