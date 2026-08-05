(function () {
    'use strict';

    var raiz = document.querySelector('[data-pago-detalle]');

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.BackNavigation) {
        return;
    }

    window.Vue.createApp({
        components: {
            'back-navigation': window.SUIFComponentes.BackNavigation
        }
    }).mount(raiz);
}());
