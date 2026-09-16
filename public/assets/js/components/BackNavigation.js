(function () {
    'use strict';

    window.SUIFComponentes = window.SUIFComponentes || {};

    window.SUIFComponentes.BackNavigation = {
        props: {
            destino: {
                type: String,
                required: true
            },
            etiqueta: {
                type: String,
                default: 'Atrás'
            },
            etiquetaAccesible: {
                type: String,
                default: ''
            }
        },
        template: `
            <footer class="tarjeta admin-preregistro-barra-atras">
                <a class="boton boton--secundario" :href="destino" :aria-label="etiquetaAccesible || etiqueta">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>{{ etiqueta }}</span>
                </a>
            </footer>
        `
    };
}());
