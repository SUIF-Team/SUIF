/* Comportamientos compartidos del navbar y footer públicos. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var navbar = document.querySelector('[data-site-navbar]');
        var navbarCollapse = document.getElementById('navbarCollapse');
        var navbarToggler = navbar ? navbar.querySelector('.navbar-toggler') : null;
        var navbarAnchors = navbar ? navbar.querySelectorAll('[data-navbar-anchor]') : [];
        var backToTop = document.querySelector('[data-back-to-top]');

        function updateScrollState() {
            var hasScrolled = window.pageYOffset > 40;

            if (navbar) {
                navbar.classList.toggle('is-scrolled', hasScrolled);
            }

            if (backToTop) {
                backToTop.classList.toggle('is-visible', window.pageYOffset > 320);
            }
        }

        function closeMobileNavigation() {
            if (!navbarCollapse || window.innerWidth >= 1200 || !navbarCollapse.classList.contains('show')) {
                return;
            }

            if (window.bootstrap && window.bootstrap.Collapse) {
                window.bootstrap.Collapse.getOrCreateInstance(navbarCollapse, { toggle: false }).hide();
                return;
            }

            navbarCollapse.classList.remove('show');

            if (navbarToggler) {
                navbarToggler.setAttribute('aria-expanded', 'false');
            }
        }

        Array.prototype.forEach.call(navbarAnchors, function (anchor) {
            anchor.addEventListener('click', closeMobileNavigation);
        });

        if (backToTop) {
            backToTop.addEventListener('click', function () {
                var behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
                window.scrollTo({ top: 0, behavior: behavior });
            });
        }

        window.addEventListener('scroll', updateScrollState, { passive: true });
        updateScrollState();
    });
}());

/*
 * Copiado al portapapeles.
 *
 * navigator.clipboard sólo existe en contextos seguros: sobre HTTP —como el
 * servidor de despliegue, que se sirve por IP— es undefined y el botón se
 * quedaba sin hacer nada, dejando en el portapapeles lo que ya hubiera antes.
 * Por eso hay respaldo con execCommand y el resultado se devuelve para que la
 * pantalla avise si no se pudo copiar.
 */
(function () {
    'use strict';

    window.SUIF = window.SUIF || {};

    function copiarConRespaldo(texto) {
        var area = document.createElement('textarea');

        area.value = texto;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        area.style.opacity = '0';

        document.body.appendChild(area);
        area.select();
        area.setSelectionRange(0, area.value.length);

        var copiado = false;

        try {
            copiado = document.execCommand('copy');
        } catch (error) {
            copiado = false;
        }

        document.body.removeChild(area);

        return copiado;
    }

    /* Devuelve una promesa que resuelve a true sólo si el texto se copió. */
    window.SUIF.copiarTexto = function (texto) {
        texto = String(texto == null ? '' : texto).trim();

        if (texto === '') {
            return Promise.resolve(false);
        }

        if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(texto).then(function () {
                return true;
            }).catch(function () {
                return copiarConRespaldo(texto);
            });
        }

        return Promise.resolve(copiarConRespaldo(texto));
    };

    /*
     * Conecta un botón con el elemento cuyo texto debe copiarse y le cambia la
     * etiqueta según el resultado.
     */
    window.SUIF.conectarCopiado = function (boton, selectorOrigen, textoExito, textoError) {
        if (!boton) {
            return;
        }

        var etiqueta = boton.querySelector('span') || boton;
        var original = etiqueta.textContent;
        var temporizador;

        boton.addEventListener('click', function () {
            var origen = document.querySelector(selectorOrigen);

            if (!origen) {
                return;
            }

            window.SUIF.copiarTexto(origen.textContent).then(function (copiado) {
                etiqueta.textContent = copiado ? textoExito : textoError;
                boton.classList.toggle('is-error', !copiado);

                window.clearTimeout(temporizador);
                temporizador = window.setTimeout(function () {
                    etiqueta.textContent = original;
                    boton.classList.remove('is-error');
                }, 2500);
            });
        });
    };
}());
/*
 * Aviso de privacidad al entrar al sitio.
 *
 * El banner se pinta siempre y aquí se oculta cuando ya se cerró: al revés,
 * quien navegue sin JavaScript no lo vería nunca. localStorage lanza excepción
 * en algunos modos privados; si falla, el aviso simplemente vuelve a salir.
 */
(function () {
    'use strict';

    var CLAVE = 'suif.aviso-privacidad';

    function yaSeCerro() {
        try {
            return window.localStorage.getItem(CLAVE) === '1';
        } catch (error) {
            return false;
        }
    }

    function recordarCierre() {
        try {
            window.localStorage.setItem(CLAVE, '1');
        } catch (error) {
            /* Sin almacenamiento disponible el aviso se volverá a mostrar. */
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.querySelector('[data-aviso-privacidad]');

        if (!banner) {
            return;
        }

        if (yaSeCerro()) {
            banner.hidden = true;
            return;
        }

        var cerrar = banner.querySelector('[data-aviso-privacidad-cerrar]');

        if (cerrar) {
            cerrar.addEventListener('click', function () {
                banner.hidden = true;
                recordarCierre();
            });
        }
    });
}());
