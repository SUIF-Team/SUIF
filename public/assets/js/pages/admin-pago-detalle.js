(function () {
    'use strict';

    var raiz = document.querySelector('[data-pago-detalle]');

    if (!raiz || !window.Vue || !window.SUIFComponentes
        || !window.SUIFComponentes.BackNavigation || !window.SUIFComponentes.Alertas) {
        return;
    }

    /* El motivo se lee del DOM antes de montar: Blade ya escribió ahí el
       old('motivo_rechazo') y en cuanto Vue toma el textarea el v-model lo
       reemplazaría con el valor del estado. */
    var campoMotivo = raiz.querySelector('#motivo-rechazo');

    window.Vue.createApp({
        components: {
            'back-navigation': window.SUIFComponentes.BackNavigation,
            alertas: window.SUIFComponentes.Alertas
        },
        data: function () {
            return {
                motivo: campoMotivo ? campoMotivo.value : '',
                /* Si la validación de servidor falló, el panel reaparece
                   abierto con lo que se había capturado. */
                rechazoAbierto: raiz.hasAttribute('data-rechazo-abierto'),
                avisoError: '',
                /* {campo: mensaje} de lo que rechazo el servidor */
                erroresServidor: {},
                enviando: false
            };
        },
        computed: {
            motivoValido: function () {
                return this.motivo.trim() !== '';
            }
        },
        methods: {
            abrirRechazo: function () {
                this.rechazoAbierto = true;

                this.$nextTick(function () {
                    if (this.$refs.motivo) {
                        this.$refs.motivo.focus();
                    }
                }.bind(this));
            },
            cerrarRechazo: function () {
                this.rechazoAbierto = false;
            },

            /*
             * Validar y rechazar salen de aqui sin recargar.
             *
             * El motivo del rechazo se escribe a mano y puede llegar a dos mil
             * caracteres: si el servidor lo rechazaba, la pantalla se recargaba
             * y el panel volvia a abrirse desde arriba. Ahora el aviso aparece
             * y lo redactado se queda donde estaba.
             */
            enviar: function (evento) {
                if (this.enviando) {
                    return;
                }

                this.enviando = true;
                this.avisoError = '';
                this.erroresServidor = {};

                window.SUIF.enviarYSeguir(evento.target, {
                    url: window.SUIF.destinoDeEnvio(evento.target, evento)
                }).then(function (resultado) {
                    /* Resolver el pago lleva a la pantalla de resultado, que es
                       otra: ahi si se navega. */
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
