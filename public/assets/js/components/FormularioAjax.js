/*
 * La app mínima que necesita un formulario que ya no recarga la pantalla.
 *
 * Es lo mismo en el acceso, la recuperación de clave, los dictámenes y las
 * altas y bajas del panel: un aviso con lo que rechazó el servidor, el botón
 * apagado mientras se envía, y el envío por fetch. Sin esto cada pantalla
 * arrastraría su propia copia de las mismas veinte líneas.
 *
 * La plantilla la sigue pintando Blade y Vue compila sobre ella, así que sin
 * JavaScript el formulario se envía como siempre y el servidor valida lo mismo.
 *
 * Uso desde una vista:
 *   window.SUIF.montarFormulario('#login-app');
 *
 * Estado que queda disponible en la plantilla:
 *   avisoError       texto del último fallo
 *   avisoExito       texto de la confirmación cuando la acción no navega
 *   erroresServidor  {campo: mensaje}, para el componente <alertas>
 *   enviando         mientras hay una petición en curso
 *   enviar($event)   en el @submit.prevent del formulario
 *
 * Con `opciones` se añaden data y methods propios de la pantalla; lo que
 * traiga con el mismo nombre gana, para poder ajustar un caso sin bifurcar.
 */
(function () {
    'use strict';

    window.SUIF = window.SUIF || {};

    window.SUIF.montarFormulario = function (selector, opciones) {
        var raiz = typeof selector === 'string' ? document.querySelector(selector) : selector;

        if (!raiz || !window.Vue || !window.SUIF.enviarYSeguir) {
            return null;
        }

        opciones = opciones || {};

        var componentes = {};

        if (window.SUIFComponentes) {
            if (window.SUIFComponentes.Alertas) {
                componentes.alertas = window.SUIFComponentes.Alertas;
            }

            if (window.SUIFComponentes.BackNavigation) {
                componentes['back-navigation'] = window.SUIFComponentes.BackNavigation;
            }
        }

        var metodos = {
            enviar: function (evento) {
                if (this.enviando) {
                    return;
                }

                var formulario = evento.target;

                this.enviando = true;
                this.avisoError = '';
                this.avisoExito = '';
                this.erroresServidor = {};

                window.SUIF.enviarYSeguir(formulario, {
                    url: window.SUIF.destinoDeEnvio(formulario, evento)
                }).then(function (resultado) {
                    /* Cuando la acción lleva a otra pantalla se navega y el
                       botón se queda apagado mientras tanto. */
                    if (resultado.navegando) {
                        return;
                    }

                    this.enviando = false;

                    /* Hay acciones que terminan en la misma pantalla —pedir
                       una clave nueva, por ejemplo—: ahí el mensaje es una
                       confirmación y pintarlo de rojo diría lo contrario de lo
                       que pasó. */
                    if (resultado.ok) {
                        this.avisoExito = resultado.mensaje;
                        this.avisoError = '';
                        this.erroresServidor = {};
                    } else {
                        this.avisoExito = '';
                        this.avisoError = resultado.mensaje;
                        this.erroresServidor = resultado.errores;
                    }

                    if (typeof this.alTerminar === 'function') {
                        this.alTerminar(resultado);
                    }
                }.bind(this));
            }
        };

        Object.keys(opciones.methods || {}).forEach(function (nombre) {
            metodos[nombre] = opciones.methods[nombre];
        });

        return window.Vue.createApp({
            components: Object.assign(componentes, opciones.components || {}),
            data: function () {
                var base = {
                    /* Arrancan con lo que ya venía pintado desde el servidor,
                       para que un v-text no lo borre al montar. */
                    avisoError: raiz.dataset.error || '',
                    avisoExito: raiz.dataset.exito || '',
                    erroresServidor: {},
                    enviando: false
                };

                return Object.assign(base, opciones.data ? opciones.data(raiz) : {});
            },
            methods: metodos,
            computed: opciones.computed || {},
            mounted: function () {
                if (opciones.mounted) {
                    opciones.mounted.call(this, raiz);
                }
            }
        }).mount(raiz);
    };

    /*
     * Las pantallas que no necesitan nada más que lo de arriba se marcan en el
     * HTML con data-formulario-ajax y se montan solas: así no hace falta un
     * archivo por pantalla que repita la misma llamada.
     */
    function montarLosMarcados() {
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-formulario-ajax]'),
            function (nodo) {
                window.SUIF.montarFormulario(nodo);
            }
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', montarLosMarcados);
    } else {
        montarLosMarcados();
    }
}());
