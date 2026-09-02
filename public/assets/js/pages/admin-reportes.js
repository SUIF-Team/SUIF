(function () {
    'use strict';

    /* Lo único que necesita esta pantalla: montar el botón de regreso. Los
       reportes se piden con formularios GET, que el navegador ya resuelve
       solo, así que aquí no hay filtros ni estado que administrar. */
    var navegacion = document.getElementById('admin-reportes-navegacion');

    if (navegacion && window.Vue && window.SUIFComponentes && window.SUIFComponentes.BackNavigation) {
        window.Vue.createApp({
            components: {
                BackNavigation: window.SUIFComponentes.BackNavigation
            }
        }).mount(navegacion);
    }
}());
