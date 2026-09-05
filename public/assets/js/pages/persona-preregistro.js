/*
    persona-preregistro.js
    Formulario de datos de identificación: el alta y la edición usan el mismo.

    Son doce campos que la persona escribe de su identificación oficial, y hasta
    ahora cualquier rechazo del servidor —una CURP ya registrada, un RFC sin
    homoclave— recargaba la pantalla entera: el formulario volvía con old(), sí,
    pero desde arriba y con los <select> repintados, así que había que buscar de
    nuevo qué campo estaba mal. Ahora el envío va por fetch y el motivo aparece
    encima sin mover nada de lo escrito.

    El éxito sí navega: lleva a la pantalla de la clave de acceso, que es otra.

    Los campos siguen siendo HTML de servidor con required, maxlength y pattern.
    Sin JavaScript el formulario se envía como siempre y el servidor valida lo
    mismo; esta app sólo intercepta el envío.
*/
(function () {
    'use strict';

    var raiz = document.querySelector('#pr-datos-app');

    /* La clave se copia con el respaldo de main.js: sobre HTTP el portapapeles
       nativo no existe. Vive fuera del formulario y en otra fase de la pantalla,
       así que se conecta aparte y aunque no haya nada que montar. */
    document.addEventListener('DOMContentLoaded', function () {
        if (window.SUIF) {
            window.SUIF.conectarCopiado(
                document.querySelector('[data-copy-key]'),
                '#pr-key',
                'Clave copiada',
                'No se pudo copiar'
            );
        }
    });

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.Alertas) {
        return;
    }

    /* Fuera del estado reactivo: es un nodo del DOM, no un dato que la
       plantilla tenga que observar. */
    var formulario = document.querySelector('#pr-data-form');

    if (!formulario) {
        return;
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
                /* En el alta el botón nace apagado hasta que el formulario esté
                   completo; en la edición nace encendido. Se lee del HTML para
                   no repetir aquí la regla que ya decide Blade. */
                completo: false
            };
        },
        methods: {
            /* El botón se apaga mientras falte algo, usando la validación del
               propio navegador: las reglas ya están en los required, maxlength
               y pattern de los campos, no hace falta copiarlas. */
            revisarCompletitud: function () {
                this.completo = formulario.checkValidity();

                var boton = formulario.querySelector('button[type=submit]');

                if (boton) {
                    boton.disabled = !this.completo || this.enviando;
                }
            },

            enviar: function (evento) {
                if (this.enviando) {
                    return;
                }

                this.enviando = true;
                this.avisoError = '';
                this.erroresServidor = {};
                this.revisarCompletitud();

                window.SUIF.enviarYSeguir(evento.target).then(function (resultado) {
                    if (resultado.navegando) {
                        return;
                    }

                    this.enviando = false;
                    this.avisoError = resultado.mensaje;
                    this.erroresServidor = resultado.errores;
                    this.revisarCompletitud();

                    /* El aviso queda arriba del formulario, que puede ser más
                       alto que la pantalla: sin esto el botón respondería y la
                       persona no vería por qué. */
                    raiz.scrollIntoView({
                        block: 'start',
                        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                            ? 'auto'
                            : 'smooth'
                    });
                }.bind(this));
            }
        },
        mounted: function () {
            formulario.addEventListener('input', this.revisarCompletitud);
            formulario.addEventListener('change', this.revisarCompletitud);
            this.revisarCompletitud();
        }
    }).mount(raiz);
}());
