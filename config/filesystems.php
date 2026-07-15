<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Discos declarados para cada subcarpeta de almacenamiento del proyecto SUIF.
    | Migrado desde: storage/ subcarpetas directas → storage/app/<subcarpeta>
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
        ],

        // Disco para comprobantes de pago subidos por participantes
        'comprobantes' => [
            'driver' => 'local',
            'root'   => storage_path('app/comprobantes'),
        ],

        // Disco para documentación requerida (INE, título, cédula, foto)
        'documentos' => [
            'driver' => 'local',
            'root'   => storage_path('app/documentos'),
        ],

        // Disco para referencias bancarias en PDF
        'referencias' => [
            'driver' => 'local',
            'root'   => storage_path('app/referencias'),
        ],

        // Disco para facturas fiscales generadas
        'facturas' => [
            'driver' => 'local',
            'root'   => storage_path('app/facturas'),
        ],

        // Disco para certificados de aprobación generados
        'certificados' => [
            'driver' => 'local',
            'root'   => storage_path('app/certificados'),
        ],

        's3' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url'    => env('AWS_URL'),
        ],

    ],

];
