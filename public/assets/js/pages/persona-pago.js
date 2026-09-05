/*
 * Formulario del paso de pago.
 *
 * La persona declara cuánto, cuándo y a qué hora pagó, y adjunta el PDF que lo
 * respalda. Vue sólo adelanta lo que de todas formas valida el servidor y pinta
 * la confirmación del adjunto: el input de archivo va oculto (opacity: 0) para
 * poder estilizar el botón, y con él se esconde el texto nativo del navegador
 * con el nombre del archivo, así que sin esto la persona elige su PDF y la
 * pantalla no cambia en nada.
 *
 * El formulario sigue siendo HTML real con required, min, max y step: si este
 * script no llega a cargar, se envía como siempre.
 */
(function () {
    'use strict';

    var raiz = document.querySelector('#pago-form-app');

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.Alertas) {
        return;
    }

    var vista;

    try {
        vista = JSON.parse(raiz.dataset.vista);
    } catch (error) {
        return;
    }

    var LIMITE_BYTES = 1048576; /* Equivale al max:1024 (KB) del controlador. */
    var MONTO_MAXIMO = 999999; /* Equivale al max:999999 del controlador. */
    var DOS_DECIMALES = /^\d+(\.\d{1,2})?$/;

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
                montoPagado: vista.montoPagado || '',
                fechaPago: vista.fechaPago || '',
                horaPago: vista.horaPago || '',
                maxFecha: vista.maxFecha || '',
                /* { nombre, peso } del PDF elegido, o null */
                archivo: null,
                error: null
            };
        },
        computed: {
            /* Los avisos repiten los límites de las reglas del controlador; se
               adelantan aquí para que la persona no tenga que enviar el
               formulario para enterarse. */
            avisoMonto: function () {
                if (this.montoPagado === '' || this.montoPagado === null) {
                    return null;
                }

                var monto = Number(this.montoPagado);

                if (isNaN(monto)) {
                    return 'El monto pagado debe ser una cantidad.';
                }

                if (monto <= 0) {
                    return 'El monto pagado debe ser mayor que cero.';
                }

                if (monto > MONTO_MAXIMO) {
                    return 'El monto pagado excede el máximo permitido.';
                }

                if (!DOS_DECIMALES.test(String(this.montoPagado))) {
                    return 'El monto pagado admite como máximo dos decimales.';
                }

                return null;
            },

            /* Las fechas ISO se comparan como cadenas sin ambigüedad. */
            avisoFecha: function () {
                if (this.fechaPago === '') {
                    return null;
                }

                return this.fechaPago > this.maxFecha
                    ? 'La fecha de pago no puede ser posterior a hoy.'
                    : null;
            },

            puedeEnviar: function () {
                return this.archivo !== null
                    && this.montoPagado !== '' && this.avisoMonto === null
                    && this.fechaPago !== '' && this.avisoFecha === null
                    && this.horaPago !== '';
            }
        },
        methods: {
            elegirArchivo: function (evento) {
                var elegido = evento.target.files && evento.target.files[0];

                if (!elegido) {
                    this.archivo = null;
                    this.error = null;

                    return;
                }

                var motivo = motivoDeRechazo(elegido);

                if (motivo) {
                    evento.target.value = '';
                    this.archivo = null;
                    this.error = motivo;

                    return;
                }

                this.archivo = { nombre: elegido.name, peso: pesoEnKb(elegido.size) };
                this.error = null;
            },

            quitarArchivo: function () {
                if (this.$refs.entradaArchivo) {
                    this.$refs.entradaArchivo.value = '';
                    this.$refs.entradaArchivo.focus();
                }

                this.archivo = null;
                this.error = null;
            },

            /*
             * Subsanar un comprobante rechazado es el camino que se repite, y
             * cada intento fallido costaba una recarga que además vaciaba el
             * archivo ya elegido. Ahora el motivo se dice arriba y lo escrito
             * se queda. El éxito sí navega: la pantalla pasa a mostrar el pago
             * en revisión, que es otro estado.
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
    }).mount('#pago-form-app');
}());
