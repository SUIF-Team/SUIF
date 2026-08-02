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
            <footer class="admin-preregistro-tarjeta admin-preregistro-barra-atras">
                <a class="admin-preregistro-enlace-atras" :href="destino" :aria-label="etiquetaAccesible || etiqueta">
                    <span class="admin-preregistro-icono-atras" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M15 18l-6-6 6-6"></path>
                        </svg>
                    </span>
                    <span>{{ etiqueta }}</span>
                </a>
            </footer>
        `
    };
}());
