(function () {
    'use strict';

    var raiz = document.querySelector('[data-pago-detalle]');

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.BackNavigation) {
        return;
    }

    /* El motivo se lee del DOM antes de montar: Blade ya escribió ahí el
       old('motivo_rechazo') y en cuanto Vue toma el textarea el v-model lo
       reemplazaría con el valor del estado. */
    var campoMotivo = raiz.querySelector('#motivo-rechazo');

    window.Vue.createApp({
        components: {
            'back-navigation': window.SUIFComponentes.BackNavigation
        },
        data: function () {
            return {
                motivo: campoMotivo ? campoMotivo.value : '',
                /* Si la validación de servidor falló, el panel reaparece
                   abierto con lo que se había capturado. */
                rechazoAbierto: raiz.hasAttribute('data-rechazo-abierto')
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
            }
        }
    }).mount(raiz);
}());
