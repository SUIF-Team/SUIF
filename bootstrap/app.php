<?php

use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Reemplaza el 'guest' por default: redirige al dashboard de la persona
        // en vez de a la ruta 'login' cuando ya hay sesión iniciada.
        $middleware->alias([
            'guest' => RedirectIfAuthenticated::class,
        ]);

        /*
         * La cookie del aviso de privacidad la escribe JavaScript al cerrarlo,
         * así que llega sin cifrar y EncryptCookies la descartaría por no poder
         * descifrarla: el servidor nunca sabría que ya se cerró y el aviso
         * volvería a pintarse en cada carga. No lleva nada privado, sólo la
         * marca de que ya se leyó.
         */
        $middleware->encryptCookies(except: ['suif_aviso_privacidad']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
