<?php

/*
 * Sólo lo que difiere del framework: las conexiones vienen de
 * vendor/laravel/framework/config/queue.php. El default del framework es
 * 'database' y la tabla jobs no existe; SUIF no encola nada.
 */
return [

    'default' => env('QUEUE_CONNECTION', 'sync') ?: 'sync',

];
