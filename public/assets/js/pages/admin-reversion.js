/* Modales de confirmación de las acciones que revierten una resolución.

   Va en JavaScript llano a propósito: el parcial que maneja se incluye en dos
   pantallas que ya montan apps Vue distintas, y así no se entrelaza con
   ninguna de las dos. */
(function () {
    'use strict';

    var disparadores = document.querySelectorAll('[data-abrir-reversion]');

    if (!disparadores.length) {
        return;
    }

    Array.prototype.forEach.call(disparadores, function (abrir) {
        var identificador = abrir.getAttribute('data-abrir-reversion');
        var modal = document.querySelector('[data-modal-reversion="' + identificador + '"]');

        if (!modal) {
            return;
        }

        var cerrarBotones = modal.querySelectorAll('[data-cerrar-reversion]');
        var cancelar = modal.querySelector('button[data-cerrar-reversion]');
        var focoAnterior;

        function cerrarModal() {
            modal.hidden = true;
            document.body.classList.remove('admin-reversion-modal-abierto');

            if (focoAnterior) {
                focoAnterior.focus();
            }
        }

        abrir.addEventListener('click', function () {
            focoAnterior = document.activeElement;
            modal.hidden = false;
            document.body.classList.add('admin-reversion-modal-abierto');

            if (cancelar) {
                cancelar.focus();
            }
        });

        Array.prototype.forEach.call(cerrarBotones, function (boton) {
            boton.addEventListener('click', cerrarModal);
        });

        modal.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrarModal();
                return;
            }

            if (evento.key !== 'Tab') {
                return;
            }

            var enfocables = Array.prototype.slice.call(
                modal.querySelectorAll('button:not([disabled])')
            );

            if (!enfocables.length) {
                return;
            }

            var primero = enfocables[0];
            var ultimo = enfocables[enfocables.length - 1];

            if (evento.shiftKey && document.activeElement === primero) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && document.activeElement === ultimo) {
                evento.preventDefault();
                primero.focus();
            }
        });
    });
}());
