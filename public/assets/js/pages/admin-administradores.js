/* Pantallas de administradores: navegación de regreso y modal de baja.
   Mismo comportamiento que el modal de sedes; lo que cambia son los atributos,
   porque aquí la acción no elimina sino que retira el acceso. */
(function () {
    'use strict';

    var navegacion = document.getElementById('admin-sedes-navegacion');
    if (navegacion && window.Vue && window.SUIFComponentes && window.SUIFComponentes.BackNavigation) {
        window.Vue.createApp({
            components: {
                BackNavigation: window.SUIFComponentes.BackNavigation
            }
        }).mount(navegacion);
    }

    var formulario = document.querySelector('[data-admin-administrador-formulario]');
    if (!formulario) {
        return;
    }

    /* La CURP se guarda en mayúsculas y el servidor la normaliza de todos
       modos; hacerlo aquí evita que el campo se vea distinto a como quedará. */
    var curp = formulario.querySelector('#curp');
    if (curp) {
        curp.addEventListener('input', function () {
            var posicion = curp.selectionStart;
            curp.value = curp.value.toUpperCase();
            curp.setSelectionRange(posicion, posicion);
        });
    }

    var modal = formulario.querySelector('[data-modal-baja]');
    var abrir = formulario.querySelector('[data-abrir-baja]');
    var focoAnterior;

    if (!modal || !abrir) {
        return;
    }

    var cerrarBotones = modal.querySelectorAll('[data-cerrar-baja]');
    var cancelar = modal.querySelector('button[data-cerrar-baja]');

    function cerrarModal() {
        modal.hidden = true;
        document.body.classList.remove('admin-sedes-modal-abierto');
        if (focoAnterior) {
            focoAnterior.focus();
        }
    }

    abrir.addEventListener('click', function () {
        focoAnterior = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('admin-sedes-modal-abierto');
        cancelar.focus();
    });

    cerrarBotones.forEach(function (boton) {
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

        var enfocables = Array.from(modal.querySelectorAll('button:not([disabled])'));
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
}());
