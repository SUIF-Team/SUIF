(function () {
    'use strict';

    window.SUIFComponentes = window.SUIFComponentes || {};

    /*
     * Espeja partials/alertas.blade.php y el bloque de $errors de las vistas.
     *
     * Hasta ahora el mensaje flash llegaba junto con la página nueva que traía
     * el redirect; en las pantallas que ya no recargan hay que pintarlo en
     * sitio, y sin él la acción se quedaría sin confirmación visible.
     *
     * Las clases se reciben de fuera: el componente aporta la estructura y el rol
     * de accesibilidad, no el aspecto. Lo que se le pasa es la caja compartida
     * (notificacion notificacion--exito|--error) de partials/componentes.css;
     * mientras queden pantallas sin migrar, alguna sigue enviando la suya.
     */
    window.SUIFComponentes.Alertas = {
        props: {
            mensaje: {
                type: String,
                default: ''
            },
            /* success | error | warning, los mismos de session() en Blade. */
            tipo: {
                type: String,
                default: 'success'
            },
            /* {campo: mensaje}, tal como lo devuelve window.SUIF.errores. */
            errores: {
                type: Object,
                default: null
            },
            clase: {
                type: String,
                default: ''
            }
        },
        computed: {
            hayErrores: function () {
                return this.errores !== null && Object.keys(this.errores).length > 0;
            },

            visible: function () {
                return this.mensaje !== '' || this.hayErrores;
            },

            listaErrores: function () {
                return Object.keys(this.errores || {}).map(function (campo) {
                    return { campo: campo, texto: this.errores[campo] };
                }, this);
            },

            /* Un fallo se anuncia interrumpiendo; una confirmación espera a que
               el lector de pantalla termine lo que venía diciendo. */
            rol: function () {
                return this.tipo === 'success' ? 'status' : 'alert';
            },

            clases: function () {
                if (this.clase) {
                    return this.clase;
                }

                var papeles = { success: 'exito', error: 'error', warning: 'advertencia' };

                return 'notificacion notificacion--' + (papeles[this.tipo] || 'info');
            },

            /* El ícono acompaña al color: sin él, quien no distingue el verde del
               rojo sólo tiene el texto. */
            icono: function () {
                return this.tipo === 'success'
                    ? 'fa-solid fa-circle-check'
                    : 'fa-solid fa-circle-exclamation';
            }
        },
        template: `
            <div v-if="visible" :class="clases" :role="rol">
                <i class="notificacion__icono" :class="icono" aria-hidden="true"></i>
                <div>
                    <p v-if="mensaje">{{ mensaje }}</p>
                    <template v-if="hayErrores">
                        <strong>Corrige estos datos:</strong>
                        <ul>
                            <li v-for="error in listaErrores" :key="error.campo">{{ error.texto }}</li>
                        </ul>
                    </template>
                </div>
            </div>
        `
    };
}());
