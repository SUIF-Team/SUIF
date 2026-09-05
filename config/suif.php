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

    /*
     * Contenido de la landing.
     *
     * Vivía dentro de public/assets/js/pages/home.js, así que el servidor
     * entregaba la página con las llaves de Vue en crudo y el texto sólo
     * aparecía al montar la app, ya al final del <body>. Aquí lo lee el
     * controlador y Blade lo pinta de una vez. Además es contenido de ejemplo
     * pendiente de sustituir por el de la convocatoria real: aislado se
     * reemplaza sin abrir la plantilla.
     */
    'landing' => [
        'tarjetas' => [
            [
                'icono' => 'far fa-edit',
                'titulo' => 'Pre-registro',
                'descripcion' => 'Completa tu registro y sube tu documentación desde cualquier lugar.',
            ],
            [
                'icono' => 'far fa-credit-card',
                'titulo' => 'Comprobante de pago',
                'descripcion' => 'Obtén tu referencia bancaria y realiza el pago correspondiente.',
            ],
            [
                'icono' => 'fas fa-map-marker-alt',
                'titulo' => 'Sede y horario',
                'descripcion' => 'Selecciona la sede y el horario de aplicación de la evaluación.',
            ],
            [
                'icono' => 'fas fa-search',
                'titulo' => 'Seguimiento',
                'descripcion' => 'Revisa el estado de tu trámite y descarga tus constancias.',
            ],
        ],

        /* El número es dato y no se deriva del índice: renumerar o reordenar
           los pasos no debería obligar a tocar Blade. */
        'pasos' => [
            [
                'numero' => '01',
                'titulo' => 'Pre-registro y documentación',
                'descripcion' => 'Completa tus datos personales y sube la documentación requerida al sistema.',
            ],
            [
                'numero' => '02',
                'titulo' => 'Generación de referencia',
                'descripcion' => 'Obtén una referencia bancaria para realizar el pago correspondiente.',
            ],
            [
                'numero' => '03',
                'titulo' => 'Validación de pago',
                'descripcion' => 'Sube tu comprobante de pago; nuestro equipo de finanzas lo validará para confirmar tu inscripción.',
            ],
            [
                'numero' => '04',
                'titulo' => 'Selección de sede y horario',
                'descripcion' => 'Una vez validado, selecciona la sede y el horario de aplicación de la evaluación.',
            ],
            [
                'numero' => '05',
                'titulo' => 'Consulta de resultados',
                'descripcion' => 'Ingresa en la fecha estipulada para conocer el puntaje obtenido y descargar tu constancia.',
            ],
        ],

        /* La primera pregunta se muestra abierta al entrar; el resto colapsadas. */
        'preguntas' => [
            [
                'pregunta' => '¿Por qué medio se realiza el registro?',
                'respuesta' => 'El registro de su solicitud se llevará a cabo mediante la Plataforma web en [URL del sitio web]',
            ],
            [
                'pregunta' => '¿A través de qué medios se puede dar seguimiento al estatus del proceso?',
                'respuesta' => 'Ingresando a la Plataforma web y consultando los correos electrónicos principal y alterno que registraron.',
            ],
            [
                'pregunta' => '¿Quiénes pueden obtener la Certificación?',
                'respuesta' => 'Las personas físicas que realizan Actividades Vulnerables a las que se refiere el artículo 17 de la LFPIORPI; las personas responsables encargadas de cumplimiento, y aquellas personas interesadas que cumplan con lo establecido en la Convocatoria.',
            ],
            [
                'pregunta' => '¿Existe algún curso de preparación para la evaluación?',
                'respuesta' => 'La FCA no imparte ninguna capacitación o curso de preparación para obtener el Certificado, únicamente proporciona el temario de estudio y guía.',
            ],
            [
                'pregunta' => '¿El Certificado que emite la FCA cuenta con alguna vigencia?',
                'respuesta' => 'La vigencia del Certificado será de cinco años, a partir de la fecha de su emisión.',
            ],
            [
                'pregunta' => '¿Cuál es la cuota que deberá cubrirse para la evaluación de obtención del Certificado?',
                'respuesta' => 'La cuota de recuperación será de $7,000.00 (siete mil pesos 00/100 M.N.) y se pagará directamente a la Organización evaluadora.',
            ],
            [
                'pregunta' => '¿Estoy obligada u obligado a contar con la Certificación que otorga la FCA?',
                'respuesta' => 'No. Esto, de conformidad con el artículo 34 Bis de las Reglas de Carácter General a que se refiere la LFPIORPI.',
            ],
            [
                'pregunta' => 'Si cuento con la Certificación de la CNBV, ¿puedo obtener la certificación de la FCA?',
                'respuesta' => 'Sí. No existe incompatibilidad entre ambas Certificaciones.',
            ],
            [
                'pregunta' => '¿Es un requisito llevar a cabo alguna Actividad Vulnerable para poder obtener la Certificación?',
                'respuesta' => 'No. De acuerdo con la Convocatoria, aquellas personas físicas interesadas que cumplan con lo establecido en la misma podrán participar en el proceso.',
            ],
        ],
    ],
];
