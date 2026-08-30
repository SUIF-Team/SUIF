/* Pantallas de convocatorias: reutilizan el diseño de sedes y añaden el modal
   de cambio de estado, que es de la bandeja y no del formulario. */
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

    /* Trampa de foco y cierre con Escape. Es la misma que usan las sedes y los
       administradores; aquí se escribe una vez y la comparten los dos modales
       de esta pantalla. */
    function prepararModal(modal, abrir, alAbrir) {
        if (!modal || !abrir) {
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

        abrir.forEach(function (boton) {
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

            var enfocables = Array.from(modal.querySelectorAll(
                'button:not([disabled]), select:not([disabled]), input:not([disabled])'
            ));
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

    /* Bandeja: un solo modal para toda la tabla. El destino del formulario y el
       nombre de la convocatoria los trae el botón que lo abre. */
    var bandeja = document.querySelector('[data-admin-convocatorias]');
    if (bandeja) {
        var modalEstado = bandeja.querySelector('[data-modal-estado]');
        var formularioEstado = bandeja.querySelector('[data-formulario-estado]');
        var nombreEstado = bandeja.querySelector('[data-estado-nombre]');
        var actualEstado = bandeja.querySelector('[data-estado-actual]');
        var destinoEstado = bandeja.querySelector('[data-estado-destino]');

        marcarCierres(modalEstado, 'data-cerrar-estado');

        prepararModal(
            modalEstado,
            Array.from(bandeja.querySelectorAll('[data-abrir-estado]')),
            function (boton) {
                formularioEstado.action = boton.dataset.accion;
                nombreEstado.textContent = boton.dataset.nombre;
                actualEstado.textContent = boton.dataset.estado;

                /* Se preselecciona un destino distinto al estado actual: pasar
                   una convocatoria al estado en que ya está no es un cambio y
                   el servicio lo rechaza. */
                var opciones = Array.from(destinoEstado.options);
                var otro = opciones.find(function (opcion) {
                    return opcion.value !== boton.dataset.estado;
                });
                destinoEstado.value = otro ? otro.value : opciones[0].value;
            }
        );
    }

    /* Formulario: sólo el modal de eliminación. */
    var formulario = document.querySelector('[data-admin-convocatoria-formulario]');
    if (formulario) {
        var modalBaja = formulario.querySelector('[data-modal-eliminacion]');

        marcarCierres(modalBaja, 'data-cerrar-eliminacion');

        prepararModal(
            modalBaja,
            Array.from(formulario.querySelectorAll('[data-abrir-eliminacion]')),
            null
        );
    }
}());
