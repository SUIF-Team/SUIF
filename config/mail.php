<?php

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
     * Los demás mailers (log, array, sendmail…) vienen del framework. El
     * cifrado lo decide Laravel 13 por el puerto: 465 usa smtps y cualquier
     * otro negocia STARTTLS; la antigua clave 'encryption' ya no se lee.
     */
    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@suif.unam.mx'),
        'name' => env('MAIL_FROM_NAME', 'SUIF'),
    ],

];
