/*
 * Datos de facturación del CFDI.
 *
 * App aditiva: el formulario es HTML real con required, maxlength y pattern,
 * así que sin JavaScript se envía igual y el servidor valida lo mismo. Aquí
 * sólo se adelantan los avisos —los mismos mensajes que devuelve el
 * controlador— y se apaga el botón mientras algo no cuadre.
 */
(function () {
    'use strict';

    var raiz = document.querySelector('#facturacion-app');

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.Alertas) {
        return;
    }

    var vista;

    try {
        vista = JSON.parse(raiz.dataset.vista);
    } catch (error) {
        return;
    }

    /* Espejo de las reglas del servidor: tres o cuatro letras iniciales
       —cuatro la persona física, tres la moral—, seis dígitos de fecha y la
       homoclave. El & aparece en razones sociales. */
    var PATRON_RFC = /^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
    var PATRON_CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var MAXIMO_RAZON_SOCIAL = 35;
    var MAXIMO_CORREO = 65;

    window.Vue.createApp({
        components: {
            alertas: window.SUIFComponentes.Alertas
        },
        data: function () {
            return {
                avisoError: '',
                /* {campo: mensaje} de lo que rechazó el servidor */
                erroresServidor: {},
                enviando: false,
                razonSocial: vista.razonSocial || '',
                personaMoral: vista.personaMoral || '0',
                regimenFiscal: vista.regimenFiscal || '',
                codigoPostal: vista.codigoPostal || '',
                rfc: vista.rfc || '',
                correoCfdi: vista.correoCfdi || ''
            };
        },

        computed: {
            /* El RFC de una persona moral mide 12 y el de una física 13. */
            longitudRfc: function () {
                return this.personaMoral === '1' ? 12 : 13;
            },

            avisoRazonSocial: function () {
                if (this.razonSocial.length > MAXIMO_RAZON_SOCIAL) {
                    return 'El nombre o razón social no debe exceder los 35 caracteres.';
                }

                return null;
            },

            avisoCodigoPostal: function () {
                if (!this.codigoPostal) {
                    return null;
                }

                if (!/^[0-9]{5}$/.test(this.codigoPostal)) {
                    return 'El código postal debe tener exactamente 5 dígitos.';
                }

                return null;
            },

            avisoRfc: function () {
                if (!this.rfc) {
                    return null;
                }

                if (this.rfc.length !== this.longitudRfc) {
                    return 'El RFC de una persona moral tiene 12 caracteres y el de una persona física 13.';
                }

                if (!PATRON_RFC.test(this.rfc)) {
                    return 'Escribe el RFC con homoclave, como aparece en la constancia de situación fiscal.';
                }

                return null;
            },

            avisoCorreo: function () {
                if (!this.correoCfdi) {
                    return null;
                }

                if (this.correoCfdi.length > MAXIMO_CORREO) {
                    return 'El correo no debe exceder los 65 caracteres.';
                }

                if (!PATRON_CORREO.test(this.correoCfdi)) {
                    return 'Escribe un correo válido.';
                }

                return null;
            },

            puedeEnviar: function () {
                var completo = this.razonSocial
                    && this.regimenFiscal
                    && this.codigoPostal
                    && this.rfc
                    && this.correoCfdi;

                return !!completo
                    && !this.avisoRazonSocial
                    && !this.avisoCodigoPostal
                    && !this.avisoRfc
                    && !this.avisoCorreo;
            }
        },

        methods: {
            /* El servidor lo guarda en mayúsculas; verlo así mientras se
               escribe evita la sorpresa de que cambie al enviar. */
            normalizarRfc: function () {
                this.rfc = this.rfc.toUpperCase().replace(/\s+/g, '');
            },

            normalizarCodigoPostal: function () {
                this.codigoPostal = this.codigoPostal.replace(/[^0-9]/g, '').slice(0, 5);
            },

            /*
             * Los datos fiscales no se pueden corregir después, así que el
             * formulario es largo y se revisa con cuidado. Antes, cualquier
             * rechazo del servidor recargaba la pantalla y devolvía a la
             * persona al principio; ahora el motivo aparece arriba sin mover
             * nada. El éxito sí navega: lleva de vuelta al paso de pago.
             */
            enviar: function (evento) {
                if (this.enviando) {
                    return;
                }

                this.enviando = true;
                this.avisoError = '';
                this.erroresServidor = {};

                window.SUIF.enviarYSeguir(evento.target).then(function (resultado) {
                    if (resultado.navegando) {
                        return;
                    }

                    this.enviando = false;
                    this.avisoError = resultado.mensaje;
                    this.erroresServidor = resultado.errores;
                }.bind(this));
            }
        }
    }).mount(raiz);
}());
