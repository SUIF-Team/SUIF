<?php

/*
|--------------------------------------------------------------------------
| public/index.php — Front Controller (Laravel 13)
|--------------------------------------------------------------------------
| Este archivo es el único punto de entrada HTTP al framework Laravel.
|
| La landing completa vive en:
|   resources/views/home/index.blade.php
| servida via HomeController@index en routes/web.php → GET /
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Modo de mantenimiento (php artisan down)
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
