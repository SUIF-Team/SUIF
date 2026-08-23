(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var boton = document.querySelector('[data-copiar-referencia]');

        if (!boton || !window.SUIF) {
            return;
        }

        window.SUIF.conectarCopiado(
            boton,
            boton.getAttribute('data-copiar-origen'),
            'Referencia copiada',
            'No se pudo copiar'
        );
    });
}());
