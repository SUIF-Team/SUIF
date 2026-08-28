/*
 * Elección del comprobante fiscal del pago: ticket o CFDI.
 *
 * El envío sigue siendo un POST normal del formulario. Vue sólo intercepta
 * para pedir confirmación, porque la elección no se puede deshacer; si el
 * script no carga, los dos formularios se envían igual y el servidor aplica
 * la misma regla.
 *
 * El tipo viaja en un input oculto y no en el botón: formulario.submit() no
 * incluye el name/value del control que envió.
 */
(function () {
    'use strict';

    var raiz = document.querySelector('#comprobante-fiscal-app');

    if (!raiz || !window.Vue) {
        return;
    }

    var vista;

    try {
        vista = JSON.parse(raiz.dataset.vista);
    } catch (error) {
        return;
    }

    /* Fuera del estado reactivo a propósito: son nodos del DOM, no datos que
       la plantilla tenga que observar. */
    var formularioPendiente = null;
    var focoAnterior = null;

    window.Vue.createApp({
        data: function () {
            return {
                eleccion: vista.eleccion || null,
                tieneDatosFiscales: !!vista.tieneDatosFiscales,
                confirmacion: null,
                enviando: false
            };
        },

        computed: {
            etiquetaConfirmacion: function () {
                return this.confirmacion === 'cfdi' ? 'un CFDI' : 'un ticket';
            },

            descripcionConfirmacion: function () {
                if (this.confirmacion === 'cfdi') {
                    return 'Se emitirá un CFDI con uso «gastos en general» y se te hará llegar por'
                        + ' correo electrónico. Después tendrás que capturar tus datos de facturación.';
                }

                return 'Se emitirá un ticket sin efectos fiscales y se te hará llegar por correo'
                    + ' electrónico.';
            }
        },

        methods: {
            abrirConfirmacion: function (tipo, evento) {
                if (this.enviando) {
                    return;
                }

                formularioPendiente = evento.target;
                focoAnterior = document.activeElement;
                this.confirmacion = tipo;

                document.body.classList.add('pago-modal-abierto');

                /* Arranca el foco en Cancelar: la acción de al lado no tiene
                   vuelta atrás. */
                this.$nextTick(function () {
                    if (this.$refs.cancelar) {
                        this.$refs.cancelar.focus();
                    }
                }.bind(this));
            },

            cerrarConfirmacion: function () {
                this.confirmacion = null;
                formularioPendiente = null;
                document.body.classList.remove('pago-modal-abierto');

                if (focoAnterior) {
                    focoAnterior.focus();
                    focoAnterior = null;
                }
            },

            confirmarEleccion: function () {
                if (!formularioPendiente || this.enviando) {
                    return;
                }

                var formulario = formularioPendiente;

                this.enviando = true;
                formularioPendiente = null;

                /* submit() no dispara el evento submit, así que no se vuelve a
                   abrir el diálogo. */
                formulario.submit();
            },

            atraparFoco: function (evento) {
                var enfocables = Array.prototype.slice.call(
                    evento.currentTarget.querySelectorAll('button:not([disabled])')
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
            }
        }
    }).mount(raiz);
}());
