<?php

/*
|--------------------------------------------------------------------------
| public/index.php — Laravel 5.5 Front Controller
|--------------------------------------------------------------------------
| Reemplaza el public/index.php estático anterior.
| Este archivo es el único punto de entrada HTTP al framework Laravel.
|
| NOTA: el contenido visual anterior (landing estática con include de navbar)
| ahora debe cargarse como una vista Blade via HomeController@index
| en routes/web.php → GET /
|
| La landing completa ha sido migrada a:
|   resources/views/home/index.blade.php
*/

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
*/

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
*/

$app = require_once __DIR__ . '/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
*/

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);