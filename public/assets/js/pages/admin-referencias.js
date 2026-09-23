(function () {
    'use strict';

    var navegacion = document.getElementById('admin-referencias-navegacion');

    if (navegacion && window.Vue && window.SUIFComponentes && window.SUIFComponentes.BackNavigation) {
        window.Vue.createApp({
            components: {
                BackNavigation: window.SUIFComponentes.BackNavigation
            }
        }).mount(navegacion);
    }


    /*
     * Carga del paquete de referencias.
     *
     * El catalogo y sus PDF entran juntos en un solo ZIP de hasta 50 MB, y el
     * servidor no acepta nada si algo no cuadra: antes ese viaje entero era una
     * recarga a ciegas. Ahora se ve el avance y el resultado —o el motivo del
     * rechazo— aparece aqui mismo, con la pantalla intacta.
     */
    var carga = document.querySelector('#referencias-carga-app');

    if (carga && window.Vue && window.SUIFComponentes && window.SUIFComponentes.Alertas) {
        window.Vue.createApp({
            components: {
                alertas: window.SUIFComponentes.Alertas
            },
            data: function () {
                return {
                    subiendo: false,
                    progreso: 0,
                    avisoError: '',
                    /* {nuevas, actualizadas, total} de la ultima carga */
                    resultado: null
                };
            },
            methods: {
                cargar: function (evento) {
                    if (this.subiendo) {
                        return;
                    }

                    this.subiendo = true;
                    this.progreso = 0;
                    this.avisoError = '';
                    this.resultado = null;

                    var formulario = evento.target;

                    window.SUIF.enviarConProgreso(formulario, function (porcentaje) {
                        this.progreso = porcentaje;
                    }.bind(this)).then(function (respuesta) {
                        this.subiendo = false;

                        if (!respuesta.ok) {
                            this.avisoError = window.SUIF.mensajeError(respuesta);

                            return;
                        }

                        this.resultado = respuesta.datos.importacion || null;
                        formulario.reset();
                    }.bind(this));
                }
            }
        }).mount(carga);
    }

    /* El nombre del archivo elegido se muestra dentro del propio botón. */
    Array.prototype.forEach.call(
        document.querySelectorAll('.admin-referencias-archivo input[type=file]'),
        function (campo) {
            var etiqueta = campo.parentNode.querySelector('span');

            if (!etiqueta) {
                return;
            }

            var original = etiqueta.textContent;

            campo.addEventListener('change', function () {
                etiqueta.textContent = campo.files && campo.files.length
                    ? campo.files[0].name
                    : original;
            });
        }
    );
}());
