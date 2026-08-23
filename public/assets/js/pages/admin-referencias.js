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
