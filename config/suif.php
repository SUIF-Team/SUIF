<?php

return [
    'cuota_recuperacion' => 7000.00,
    'moneda' => 'MXN',
    'soporte_correo' => 'soportesistemas@fca.unam.mx',
        'documentos' => [
        'solicitud-firmada' => 'Solicitud firmada',
        'aceptacion-notificaciones' => 'Aceptación de notificaciones',
        'carta-bajo-protesta' => 'Carta bajo protesta',
        'autorizacion-publicacion' => 'Autorización de la publicación',
        'curp' => 'CURP',
        'identificacion-oficial' => 'Identificación oficial',
    ],

    /* Los que tienen formato oficial descargable. */
    'formatos_descargables' => [
        'solicitud-firmada',
        'aceptacion-notificaciones',
        'carta-bajo-protesta',
        'autorizacion-publicacion',
    ],
    'enlaces' => [
        'unam' => 'https://www.unam.mx/',
        'fca' => 'https://www.fca.unam.mx/',
        'uif' => 'https://www.gob.mx/uif',
        'documento_seguridad' => 'https://www.fca.unam.mx/docs/permanentes/seguridad.pdf',
        'instrumento_juridico' => 'https://www.fca.unam.mx/docs/permanentes/aws.pdf',
        'cifca' => 'https://cifca.fca.unam.mx/',
    ],

    /*
     * Comprobante de sede y horario.
     *
     * Las recomendaciones viven aquí y no en la plantilla para que cambiarlas
     * no obligue a tocar Blade: son texto de la convocatoria, no maquetación.
     */
    'comprobante_sede' => [
        'recomendaciones' => [
            'Preséntate al menos 20 minutos antes de la hora de inicio.',
            'Lleva impreso este comprobante y una identificación oficial vigente.',
            'No se permite el acceso una vez iniciada la aplicación.',
            'No está permitido el uso de teléfonos celulares ni de dispositivos electrónicos durante la evaluación.',
            'Confirma la ubicación y el tiempo de traslado con anticipación.',
        ],
    ],
];
