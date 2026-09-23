<?php

use Illuminate\Support\Str;

/*
 * Sólo lo que difiere del framework: los stores vienen de
 * vendor/laravel/framework/config/cache.php. El default no puede quedarse
 * en el del framework ('database'), porque la tabla cache no existe y el
 * throttle del login dejaría de funcionar.
 */
return [

    'default' => env('CACHE_STORE', 'file') ?: 'file',

    'prefix' => env(
        'CACHE_PREFIX',
        Str::slug(env('APP_NAME', 'suif'), '_') . '_cache'
    ),

];
