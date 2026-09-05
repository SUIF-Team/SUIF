/*
 * Catálogo de sedes del participante.
 *
 * La lista se sondea cada 15 s porque los cupos se mueven mientras la persona
 * decide, y desde el cambio de agosto de 2026 también se mueve la lista misma:
 * una aplicación que ya pasó su fecha y hora de fin deja de ofrecerse, y si era
 * la última de su sede, la sede entera se va. Por eso el servidor devuelve el
 * catálogo completo en cada sondeo y aquí se sustituye entero, en lugar de
 * parchar nodo por nodo.
 *
 * El envío va por fetch: pide confirmación —apartar el lugar no se deshace— y,
 * si el horario se llenó mientras tanto, lo dice sin recargar, con el catálogo
 * todavía delante. Sin JavaScript el formulario se envía como siempre.
 */
(function () {
    'use strict';

    var raiz = document.querySelector('#sedes-app');

    if (!raiz || !window.Vue) {
        return;
    }

    var vista;

    try {
        vista = JSON.parse(raiz.dataset.vista);
    } catch (error) {
        return;
    }

    var LAPSO_SONDEO = 15000;

    /* Fuera del estado reactivo a propósito: son nodos del DOM y el temporizador,
       no datos que la plantilla tenga que observar. */
    var intervalo = null;
    var formularioPendiente = null;
    var focoAnterior = null;

    window.Vue.createApp({
        data: function () {
            return {
                sedes: vista.sedes || [],
                buscar: vista.buscar || '',
                umbralCupoBajo: vista.umbralCupoBajo || 15,
                /* sede.id -> evaluacion_id marcada */
                seleccion: {},
                /* { sede, horario } mientras el diálogo está abierto */
                confirmacion: null,
                enviando: false,
                /* Arranca con el error del servidor para que v-text no borre
                   el que ya venía pintado en la página. */
                avisoError: raiz.dataset.error || ''
            };
        },
        methods: {
            /* Las fechas llegan como AAAA-MM-DD; se voltean a mano para no
               pasar por Date, que reinterpretaría la cadena en UTC. */
            fechaCorta: function (iso) {
                var partes = String(iso).slice(0, 10).split('-');

                return partes.length === 3
                    ? partes[2] + '/' + partes[1] + '/' + partes[0]
                    : String(iso);
            },

            /* Una aplicación puede abarcar más de un día. */
            etiquetaFecha: function (horario) {
                var inicio = this.fechaCorta(horario.fecha_inicio);
                var fin = this.fechaCorta(horario.fecha_fin);

                return inicio === fin ? inicio : inicio + '–' + fin;
            },

            claseCupo: function (horario) {
                if (!horario.con_cupo) {
                    return 'sede-cupo--lleno';
                }

                return horario.disponibles <= this.umbralCupoBajo
                    ? 'sede-cupo--bajo'
                    : 'sede-cupo--libre';
            },

            horarioElegido: function (sede) {
                var elegido = this.seleccion[sede.id];

                if (elegido === undefined || elegido === null) {
                    return null;
                }

                return sede.horarios.filter(function (horario) {
                    return horario.evaluacion_id === elegido;
                })[0] || null;
            },

            puedeEnviar: function (sede) {
                var horario = this.horarioElegido(sede);

                return !this.enviando && horario !== null && horario.con_cupo;
            },

            /* ── Confirmación ─────────────────────────────────────────────── */

            abrirConfirmacion: function (sede, evento) {
                var horario = this.horarioElegido(sede);

                if (!horario || !horario.con_cupo) {
                    return;
                }

                formularioPendiente = evento.target;
                focoAnterior = document.activeElement;
                this.confirmacion = { sede: sede, horario: horario };

                /* Con el diálogo abierto el sondeo cambiaría la tarjeta que la
                   persona está confirmando; se reanuda al cerrarlo. */
                this.detener();
                document.body.classList.add('sede-modal-abierto');

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
                document.body.classList.remove('sede-modal-abierto');

                if (focoAnterior) {
                    focoAnterior.focus();
                    focoAnterior = null;
                }

                this.iniciar();
            },

            confirmarSeleccion: function () {
                if (!formularioPendiente || this.enviando) {
                    return;
                }

                var formulario = formularioPendiente;

                this.enviando = true;
                this.detener();
                formularioPendiente = null;
                this.avisoError = '';

                window.SUIF.enviarYSeguir(formulario).then(function (resultado) {
                    /* Confirmar lleva al resumen, que es otra pantalla: ahí sí
                       se navega y conviene dejar el botón apagado mientras. */
                    if (resultado.navegando) {
                        return;
                    }

                    /* El caso que se repite: entre el último sondeo y el envío
                       alguien más tomó el lugar. Se dice aquí mismo y el
                       catálogo vuelve a moverse, sin perder dónde iba la
                       persona en la lista. */
                    this.enviando = false;
                    this.confirmacion = null;
                    document.body.classList.remove('sede-modal-abierto');
                    this.avisoError = resultado.mensaje;
                    this.iniciar();
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
            },

            /* ── Sondeo ───────────────────────────────────────────────────── */

            consultar: function () {
                var url = raiz.dataset.disponibilidadUrl
                    + '?buscar=' + encodeURIComponent(this.buscar);

                window.fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                }).then(function (respuesta) {
                    if (!respuesta.ok) {
                        throw new Error('No fue posible actualizar los cupos.');
                    }

                    return respuesta.json();
                }).then(function (datos) {
                    this.sedes = datos.sedes || [];
                    this.sanearSeleccion();
                }.bind(this)).catch(function () {
                    /* Se conserva el último estado visible; el POST vuelve a
                       validar cupo y vigencia de todas formas. */
                });
            },

            /* Lo único que el sondeo no resuelve solo: la opción marcada pudo
               desaparecer o llenarse entre una consulta y la siguiente. */
            sanearSeleccion: function () {
                this.sedes.forEach(function (sede) {
                    if (this.seleccion[sede.id] === undefined) {
                        return;
                    }

                    var horario = this.horarioElegido(sede);

                    if (!horario || !horario.con_cupo) {
                        delete this.seleccion[sede.id];
                    }
                }, this);
            },

            iniciar: function () {
                this.detener();
                this.consultar();
                intervalo = window.setInterval(this.consultar, LAPSO_SONDEO);
            },

            detener: function () {
                window.clearInterval(intervalo);
                intervalo = null;
            },

            alCambiarVisibilidad: function () {
                if (document.hidden) {
                    this.detener();

                    return;
                }

                if (!this.confirmacion) {
                    this.iniciar();
                }
            }
        },
        mounted: function () {
            document.addEventListener('visibilitychange', this.alCambiarVisibilidad);
            this.iniciar();
        },
        unmounted: function () {
            document.removeEventListener('visibilitychange', this.alCambiarVisibilidad);
            this.detener();
        }
    }).mount('#sedes-app');
}());
