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
