<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Todo lo que sube la gente vive bajo storage/app/private y se sirve sólo
    | a través de los controladores. Las rutas guardadas en la base son
    | relativas a la raíz de cada disco. Los discos public y s3 vienen del
    | framework y SUIF no los usa.
    |
    | 'local' no lleva 'serve' a propósito: el del framework lo activa y
    | registraría una ruta pública de URLs firmadas sobre estos archivos.
    |
    */

    'disks' => [

        // Documentos del pre-registro: preregistro/cargas/{solicitud}/...
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
        ],

        // Comprobantes de pago subidos por personas
        'comprobantes' => [
            'driver' => 'local',
            'root'   => storage_path('app/private/comprobantes'),
        ],

        // Formatos PDF del catálogo de referencias bancarias
        'referencias' => [
            'driver' => 'local',
            'root'   => storage_path('app/private/referencias'),
        ],

    ],

];
