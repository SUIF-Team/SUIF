/*
 * Captura de la referencia especial: el tercero que paga y la lista de
 * participantes que cubre.
 *
 * La lista se arma y se deshace en pantalla, así que esta pantalla sí depende
 * de Vue —el resto de los formularios del trámite no—. Lo que se valida aquí
 * es un espejo de las reglas del servidor, que es quien decide: adelantar los
 * avisos evita que la persona capture veinte renglones para que se los
 * rechacen todos al enviar.
 *
 * El envío no tiene vuelta atrás: liga a todos los participantes al mismo
 * pago, así que media un diálogo de confirmación. Mismo patrón accesible que
 * la elección de comprobante fiscal.
 */
(function () {
    'use strict';

    var raiz = document.querySelector('#referencia-especial-app');

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.Alertas) {
        return;
    }

    var vista;

    try {
        vista = JSON.parse(raiz.dataset.vista);
    } catch (error) {
        return;
    }

    /* Espejo de las reglas del servidor. */
    var PATRON_RFC = /^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
    var PATRON_CURP = /^[A-Z0-9]{18}$/;
    var MAXIMO_RAZON_SOCIAL = 35;

    /* Fuera del estado reactivo: son nodos del DOM, no datos que la plantilla
       tenga que observar. */
    var formularioPendiente = null;
    var focoAnterior = null;

    function personaVacia(curp) {
        return {
            curp: curp || '',
            nombre: '',
            primer_apellido: '',
            segundo_apellido: ''
        };
    }

    window.Vue.createApp({
        components: {
            alertas: window.SUIFComponentes.Alertas
        },
        data: function () {
            return {
                pagador: {
                    razonSocial: vista.pagador.razonSocial || '',
                    personaMoral: vista.pagador.personaMoral || '1',
                    regimenFiscal: vista.pagador.regimenFiscal || '',
                    codigoPostal: vista.pagador.codigoPostal || '',
                    rfc: vista.pagador.rfc || ''
                },
                participantes: (vista.participantes || []).map(function (persona) {
                    return {
                        curp: (persona.curp || '').toUpperCase(),
                        nombre: persona.nombre || '',
                        primer_apellido: persona.primer_apellido || '',
                        segundo_apellido: persona.segundo_apellido || ''
                    };
                }),
                minimo: vista.minimo || 2,
                maximo: vista.maximo || 50,
                cuota: vista.cuota || 0,
                moneda: vista.moneda || 'MXN',
                nuevaCurp: '',
                confirmando: false,
                enviando: false,
                avisoError: '',
                /* {campo: mensaje} de lo que rechazó el servidor */
                erroresServidor: {}
            };
        },

        computed: {
            /* El RFC de una persona moral mide 12 y el de una física 13. */
            longitudRfc: function () {
                return this.pagador.personaMoral === '1' ? 12 : 13;
            },

            avisoRfc: function () {
                if (!this.pagador.rfc) {
                    return null;
                }

                if (this.pagador.rfc.length !== this.longitudRfc) {
                    return 'El RFC de una persona moral tiene 12 caracteres y el de una persona física 13.';
                }

                if (!PATRON_RFC.test(this.pagador.rfc)) {
                    return 'Escribe el RFC con homoclave, como aparece en la constancia de situación fiscal.';
                }

                return null;
            },

            avisoCodigoPostal: function () {
                if (!this.pagador.codigoPostal) {
                    return null;
                }

                return /^[0-9]{5}$/.test(this.pagador.codigoPostal)
                    ? null
                    : 'El código postal debe tener exactamente 5 dígitos.';
            },

            /* La CURP repetida se avisa aquí y no por renglón: el error es del
               par, no de uno de los dos. */
            avisoDuplicados: function () {
                var vistas = {};
                var repetida = null;

                this.participantes.forEach(function (persona) {
                    if (!persona.curp) {
                        return;
                    }

                    if (vistas[persona.curp]) {
                        repetida = persona.curp;
                    }

                    vistas[persona.curp] = true;
                });

                return repetida ? 'La CURP ' + repetida + ' está repetida en la lista.' : null;
            },

            total: function () {
                return this.participantes.length * this.cuota;
            },

            totalFormateado: function () {
                return this.formatear(this.total);
            },

            cuotaFormateada: function () {
                return this.formatear(this.cuota);
            },

            puedeEnviar: function () {
                var pagadorCompleto = this.pagador.razonSocial
                    && this.pagador.razonSocial.length <= MAXIMO_RAZON_SOCIAL
                    && this.pagador.regimenFiscal
                    && this.pagador.codigoPostal
                    && this.pagador.rfc;

                if (!pagadorCompleto || this.avisoRfc || this.avisoCodigoPostal) {
                    return false;
                }

                if (this.participantes.length < this.minimo || this.participantes.length > this.maximo) {
                    return false;
                }

                if (this.avisoDuplicados) {
                    return false;
                }

                return this.participantes.every(function (persona) {
                    return !this.avisoPersona(persona);
                }, this);
            }
        },

        methods: {
            formatear: function (cantidad) {
                return new Intl.NumberFormat('es-MX', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(cantidad);
            },

            /* El servidor los guarda en mayúsculas; verlos así mientras se
               escribe evita la sorpresa de que cambien al enviar. */
            normalizarRfc: function () {
                this.pagador.rfc = this.pagador.rfc.toUpperCase().replace(/\s+/g, '');
            },

            normalizarCodigoPostal: function () {
                this.pagador.codigoPostal = this.pagador.codigoPostal.replace(/[^0-9]/g, '').slice(0, 5);
            },

            normalizarCurp: function (persona) {
                persona.curp = persona.curp.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 18);
            },

            avisoPersona: function (persona) {
                if (!persona.curp || !persona.nombre || !persona.primer_apellido || !persona.segundo_apellido) {
                    return 'Faltan datos de este participante.';
                }

                if (!PATRON_CURP.test(persona.curp)) {
                    return 'La CURP debe tener 18 letras o números.';
                }

                return null;
            },

            agregar: function (curp) {
                if (this.participantes.length >= this.maximo) {
                    return;
                }

                this.participantes.push(personaVacia((curp || '').toUpperCase().replace(/[^A-Z0-9]/g, '')));
                this.nuevaCurp = '';
            },

            /* El primer renglón es quien solicita la referencia y no se quita:
               el servidor exige que su CURP esté en la lista. */
            quitar: function (indice) {
                if (indice > 0) {
                    this.participantes.splice(indice, 1);
                }
            },

            quitarUltimo: function () {
                this.quitar(this.participantes.length - 1);
            },

            abrirConfirmacion: function (evento) {
                if (!this.puedeEnviar || this.enviando) {
                    return;
                }

                formularioPendiente = evento.target;
                focoAnterior = document.activeElement;
                this.confirmando = true;

                document.body.classList.add('refesp-modal-abierto');

                /* Arranca el foco en Volver: la acción de al lado no tiene
                   vuelta atrás. */
                this.$nextTick(function () {
                    if (this.$refs.cancelar) {
                        this.$refs.cancelar.focus();
                    }
                }.bind(this));
            },

            cerrarConfirmacion: function () {
                this.confirmando = false;
                formularioPendiente = null;
                document.body.classList.remove('refesp-modal-abierto');

                if (focoAnterior) {
                    focoAnterior.focus();
                    focoAnterior = null;
                }
            },

            confirmar: function () {
                if (!formularioPendiente || this.enviando) {
                    return;
                }

                var formulario = formularioPendiente;

                this.enviando = true;
                formularioPendiente = null;
                this.avisoError = '';
                this.erroresServidor = {};

                window.SUIF.enviarYSeguir(formulario).then(function (resultado) {
                    /* Solicitar lleva a la pantalla de la referencia, que es
                       otra: ahí sí se navega. */
                    if (resultado.navegando) {
                        return;
                    }

                    this.enviando = false;
                    this.cerrarConfirmacion();
                    this.avisoError = resultado.mensaje;
                    this.erroresServidor = resultado.errores;
                }.bind(this));
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
