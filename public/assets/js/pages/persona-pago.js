/*
 * Confirmación de lo adjuntado en la pantalla de pago.
 *
 * El input de archivo va oculto (opacity: 0) para poder estilizar el botón, y
 * con él se esconde el texto nativo del navegador con el nombre del archivo.
 * Sin esto la persona elige su PDF y la pantalla no cambia en nada, así que no
 * sabe si el adjunto quedó tomado. Aquí se pinta esa confirmación y se
 * adelanta la validación que de todas formas hace el servidor.
 */
(function () {
    'use strict';

    var LIMITE_BYTES = 1048576; /* Equivale al max:1024 (KB) del controlador. */

    function pesoEnKb(bytes) {
        return Math.ceil(bytes / 1024) + ' KB';
    }

    /* Algunos navegadores dejan el type vacío; ahí se recurre a la extensión
       para no rechazar un PDF bueno. Quien valida de verdad es el servidor. */
    function esPdf(archivo) {
        return archivo.type === 'application/pdf'
            || (archivo.type === '' && /\.pdf$/i.test(archivo.name));
    }

    function motivoDeRechazo(archivo) {
        if (!esPdf(archivo)) {
            return 'El comprobante debe ser un archivo PDF.';
        }

        if (archivo.size > LIMITE_BYTES) {
            return 'El comprobante no debe exceder los 1024 KB.';
        }

        return null;
    }

    function conectarFormulario(formulario) {
        var input = formulario.querySelector('[data-pago-archivo-input]');
        var etiqueta = formulario.querySelector('[data-pago-archivo]');
        var textoEtiqueta = formulario.querySelector('[data-pago-archivo-texto]');
        var iconoEtiqueta = formulario.querySelector('[data-pago-archivo-icono]');
        var aviso = formulario.querySelector('[data-pago-adjunto]');
        var titulo = formulario.querySelector('[data-pago-adjunto-titulo]');
        var nombre = formulario.querySelector('[data-pago-adjunto-nombre]');
        var peso = formulario.querySelector('[data-pago-adjunto-peso]');
        var iconoAviso = formulario.querySelector('[data-pago-adjunto-icono]');
        var quitar = formulario.querySelector('[data-pago-adjunto-quitar]');
        var enviar = formulario.querySelector('[data-pago-enviar]');

        if (!input || !etiqueta || !textoEtiqueta || !iconoEtiqueta || !aviso
            || !titulo || !nombre || !peso || !iconoAviso || !quitar || !enviar) {
            return;
        }

        function limpiar() {
            aviso.hidden = true;
            aviso.classList.remove('pago-adjunto--error');
            titulo.textContent = 'Archivo adjunto:';
            nombre.textContent = '';
            peso.textContent = '';
            iconoAviso.className = 'fa-solid fa-circle-check';
            quitar.hidden = false;
            etiqueta.classList.remove('pago-archivo--cargado');
            textoEtiqueta.textContent = 'Seleccionar PDF';
            iconoEtiqueta.className = 'fa-solid fa-paperclip';
            enviar.disabled = true;
        }

        function rechazar(motivo) {
            input.value = '';
            limpiar();
            titulo.textContent = motivo;
            iconoAviso.className = 'fa-solid fa-circle-exclamation';
            quitar.hidden = true;
            aviso.classList.add('pago-adjunto--error');
            aviso.hidden = false;
        }

        function aceptar(archivo) {
            limpiar();
            nombre.textContent = archivo.name;
            peso.textContent = '· ' + pesoEnKb(archivo.size);
            aviso.hidden = false;
            etiqueta.classList.add('pago-archivo--cargado');
            textoEtiqueta.textContent = 'Cambiar PDF';
            iconoEtiqueta.className = 'fa-solid fa-circle-check';
            enviar.disabled = false;
        }

        input.addEventListener('change', function () {
            var archivo = input.files && input.files[0];

            if (!archivo) {
                limpiar();

                return;
            }

            var motivo = motivoDeRechazo(archivo);

            if (motivo) {
                rechazar(motivo);

                return;
            }

            aceptar(archivo);
        });

        quitar.addEventListener('click', function () {
            input.value = '';
            limpiar();
            input.focus();
        });

        /*
         * El botón nace habilitado en el HTML y se apaga desde aquí: si este
         * script no llega a cargar, el formulario sigue enviándose como antes.
         */
        limpiar();
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(
            document.querySelectorAll('.pago-form'),
            conectarFormulario
        );
    });
}());
