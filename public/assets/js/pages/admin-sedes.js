/* Pantallas de sedes, grupos y administradores: comparten diseño y modal de
   baja. El mapa sólo aparece donde hay dirección que ubicar. */
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

    var formulario = document.querySelector('[data-admin-sede-formulario], [data-admin-grupo-formulario], [data-admin-administrador-formulario]');
    if (!formulario) {
        return;
    }

    var direccion = formulario.querySelector('[data-sede-direccion]');
    var mapa = formulario.querySelector('[data-sede-mapa]');
    var temporizador;

    if (direccion && mapa) {
        direccion.addEventListener('input', function () {
            window.clearTimeout(temporizador);
            temporizador = window.setTimeout(function () {
                var consulta = direccion.value.trim() || '19.324167,-99.184722';
                mapa.src = 'https://maps.google.com/maps?q=' + encodeURIComponent(consulta) + '&hl=es&z=16&output=embed';
            }, 800);
        });
    }

    var modal = formulario.querySelector('[data-modal-eliminacion]');
    var abrir = formulario.querySelector('[data-abrir-eliminacion]');
    var focoAnterior;

    if (!modal || !abrir) {
        return;
    }

    var cerrarBotones = modal.querySelectorAll('[data-cerrar-eliminacion]');
    var cancelar = modal.querySelector('button[data-cerrar-eliminacion]');

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
