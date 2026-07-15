<?php

return [
    'validation' => [
        'required'       => 'El campo :attribute es obligatorio.',
        'email'          => 'El campo :attribute debe ser una dirección de correo válida.',
        'min'            => [
            'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        ],
        'max'            => [
            'string' => 'El campo :attribute no puede tener más de :max caracteres.',
            'file'   => 'El archivo :attribute no puede pesar más de :max kilobytes.',
        ],
        'unique'         => 'El valor del campo :attribute ya está en uso.',
        'confirmed'      => 'La confirmación de :attribute no coincide.',
        'mimes'          => 'El archivo :attribute debe ser de tipo: :values.',
        'mimetypes'      => 'El archivo :attribute debe ser de tipo: :values.',
    ],
];
