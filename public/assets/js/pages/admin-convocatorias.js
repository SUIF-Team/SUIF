/* Pantallas de convocatorias: reutilizan el diseño de sedes y añaden, en el
   formulario, el modal que confirma el cambio de estado. La bandeja no tiene
   más comportamiento que el botón de volver. */
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

    var formulario = document.querySelector('[data-admin-convocatoria-formulario]');
    if (!formulario) {
        return;
    }

    /* Trampa de foco y cierre con Escape. Es la misma que usan las sedes y los
       administradores; aquí se escribe una vez y la comparten los dos modales
       de esta pantalla. */
    function prepararModal(modal, botones, alAbrir) {
        if (!modal || !botones.length) {
            return;
        }

        var cerrarBotones = modal.querySelectorAll('[data-cerrar]');
        var cancelar = modal.querySelector('button[data-cerrar]');
        var focoAnterior;

        function cerrar() {
            modal.hidden = true;
            document.body.classList.remove('admin-sedes-modal-abierto');
            if (focoAnterior) {
                focoAnterior.focus();
            }
        }

        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                focoAnterior = document.activeElement;
                if (alAbrir) {
                    alAbrir(boton);
                }
                modal.hidden = false;
                document.body.classList.add('admin-sedes-modal-abierto');
                cancelar.focus();
            });
        });

        cerrarBotones.forEach(function (boton) {
            boton.addEventListener('click', cerrar);
        });

        modal.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrar();
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
    }

    /* Los atributos data-cerrar-* de la plantilla se normalizan a data-cerrar
       para que la trampa de foco no tenga que conocer cada modal. */
    function marcarCierres(modal, atributo) {
        if (!modal) {
            return;
        }

        modal.querySelectorAll('[' + atributo + ']').forEach(function (nodo) {
            nodo.setAttribute('data-cerrar', '');
        });
    }

    /* Cambio de estado. Cerrar, interrumpir y reabrir comparten modal: el botón
       que se pulsa trae el destino, el verbo y el aviso de su consecuencia. */
    var modalEstado = formulario.querySelector('[data-modal-estado]');

    if (modalEstado) {
        var valorEstado = modalEstado.querySelector('[data-estado-valor]');
        var tituloEstado = modalEstado.querySelector('[data-estado-titulo]');
        var destinoEstado = modalEstado.querySelector('[data-estado-destino]');
        var avisoEstado = modalEstado.querySelector('[data-estado-aviso]');
        var confirmarEstado = modalEstado.querySelector('[data-estado-confirmar]');

        marcarCierres(modalEstado, 'data-cerrar-estado');

        prepararModal(
            modalEstado,
            Array.from(formulario.querySelectorAll('[data-abrir-estado]')),
            function (boton) {
                valorEstado.value = boton.dataset.estado;
                tituloEstado.textContent = '¿' + boton.dataset.verbo + ' esta convocatoria?';
                destinoEstado.textContent = boton.dataset.estado;
                avisoEstado.textContent = boton.dataset.aviso;
                confirmarEstado.textContent = 'Sí, ' + boton.dataset.verbo.toLowerCase();
            }
        );
    }

    var modalBaja = formulario.querySelector('[data-modal-eliminacion]');

    marcarCierres(modalBaja, 'data-cerrar-eliminacion');

    prepararModal(
        modalBaja,
        Array.from(formulario.querySelectorAll('[data-abrir-eliminacion]')),
        null
    );
}());
