/*
    persona-documentos.js
    Pantalla de documentación de la persona.

    Dos comportamientos, ambos como mejora progresiva: sin JavaScript la
    carga y el envío a revisión siguen funcionando con los formularios.
*/
(function () {
    'use strict';

    var LIMITE_BYTES = 1048576;

    /* ── Previsualización del PDF antes de confirmar la carga ───────────── */

    function conectarPrevisualizacion() {
        var entradas = document.querySelectorAll('.pr-upload-form input[type=file]');

        Array.prototype.forEach.call(entradas, function (entrada) {
            entrada.addEventListener('change', function () {
                var archivo = entrada.files && entrada.files[0];
                var formulario = entrada.closest('form');
                var caja = formulario ? formulario.querySelector('.pr-preview') : null;

                if (!archivo || !caja) {
                    return;
                }

                if (archivo.type !== 'application/pdf' || archivo.size > LIMITE_BYTES) {
                    window.alert('Selecciona un PDF de máximo 1 MB.');
                    entrada.value = '';
                    return;
                }

                caja.querySelector('span').textContent =
                    archivo.name + ' · ' + Math.ceil(archivo.size / 1024) + ' KB';
                caja.querySelector('iframe').src = URL.createObjectURL(archivo);
                caja.classList.add('is-visible');
            });
        });
    }

    /* ── Confirmación antes de enviar el expediente a revisión ──────────── */

    function conectarEnvioARevision() {
        var formulario = document.querySelector('[data-envio-revision]');
        var modal = document.querySelector('[data-modal-envio]');

        if (!formulario || !modal) {
            return;
        }

        var boton = formulario.querySelector('[data-boton-envio]');
        var confirmar = modal.querySelector('[data-confirmar-envio]');
        var cerrarBotones = modal.querySelectorAll('[data-cerrar-envio]');
        var cancelar = modal.querySelector('button[data-cerrar-envio]');
        var focoAnterior = null;

        if (!boton || !confirmar || !cancelar) {
            return;
        }

        function cerrarModal() {
            modal.hidden = true;
            document.body.classList.remove('pr-modal-abierto');

            if (focoAnterior) {
                focoAnterior.focus();
            }
        }

        function abrirModal() {
            focoAnterior = document.activeElement;
            modal.hidden = false;
            document.body.classList.add('pr-modal-abierto');
            cancelar.focus();
        }

        /* Se intercepta el submit, no el clic: así el botón sigue enviando
           el formulario si este script no llega a cargar. */
        formulario.addEventListener('submit', function (evento) {
            evento.preventDefault();
            abrirModal();
        });

        confirmar.addEventListener('click', function () {
            confirmar.disabled = true;
            confirmar.textContent = 'Enviando…';
            confirmar.classList.add('pr-btn--enviando');

            cancelar.disabled = true;

            boton.disabled = true;
            boton.textContent = 'Enviando…';
            boton.classList.add('pr-btn--enviando');

            /* submit() no dispara el evento 'submit', así que el diálogo no
               se vuelve a abrir. */
            formulario.submit();
        });

        Array.prototype.forEach.call(cerrarBotones, function (elemento) {
            elemento.addEventListener('click', cerrarModal);
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
    }

    conectarPrevisualizacion();
    conectarEnvioARevision();
}());
