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

/*
 * Envío de formularios sin recargar la página.
 *
 * El <form> sigue siendo HTML real y el servidor valida exactamente lo mismo:
 * esto sólo intercepta el envío para que la pantalla se actualice en sitio. Si
 * este script no llega a cargar, el navegador lo manda como siempre.
 *
 * FormData recoge el _token que pone @csrf, los archivos de los <input
 * type="file"> y el _method de @method('PUT'|'DELETE'), así que no hay que
 * armar el cuerpo a mano ni tratar aparte los formularios con archivos.
 */
(function () {
    'use strict';

    window.SUIF = window.SUIF || {};

    /*
     * Devuelve { ok, estado, datos }. No rechaza por un error del servidor:
     * quien llama decide qué hacer con cada código, y lo único que cae en
     * catch es que la red no responda.
     */
    window.SUIF.enviar = function (formulario, opciones) {
        opciones = opciones || {};

        var cuerpo = new FormData(formulario);

        /* Los formularios que pinta Vue no pasan por @csrf. El token se toma
           del <meta> del layout para que ninguna plantilla tenga que acordarse
           de incluirlo y un olvido no se convierta en un 419. */
        if (!cuerpo.get('_token')) {
            var meta = document.querySelector('meta[name="csrf-token"]');

            if (meta) {
                cuerpo.append('_token', meta.content);
            }
        }

        return window.fetch(opciones.url || formulario.action, {
            method: (opciones.metodo || formulario.method || 'POST').toUpperCase(),
            body: cuerpo,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }).then(function (respuesta) {
            /* 419: la sesión caducó. Recargar deja que el servidor mande a
               login o pinte errors/419; cualquier otra salida deja a la persona
               frente a un botón que ya no puede funcionar. */
            if (respuesta.status === 419) {
                window.location.reload();

                return { ok: false, estado: 419, datos: {} };
            }

            /* Se lee como texto y se interpreta aquí: una respuesta vacía o una
               página de error en HTML harían fallar respuesta.json() y se
               perdería el código de estado, que es lo que hay que mostrar. */
            return respuesta.text().then(function (cuerpo) {
                var datos = {};

                try {
                    datos = cuerpo ? JSON.parse(cuerpo) : {};
                } catch (error) {
                    datos = {};
                }

                return { ok: respuesta.ok, estado: respuesta.status, datos: datos };
            });
        }).catch(function () {
            return {
                ok: false,
                estado: 0,
                datos: { mensaje: 'No fue posible conectar. Revisa tu conexión e inténtalo de nuevo.' }
            };
        });
    };

    /*
     * Envía y decide qué sigue.
     *
     * En varias pantallas el fallo se repite —el último lugar de la sede se lo
     * llevó alguien, el PDF pasa de 1 MB, el RFC no cuadra— y hasta ahora cada
     * uno costaba una recarga que además perdía el desplazamiento en formularios
     * largos. Aquí el error se queda en la pantalla; el éxito, que sí lleva a
     * otra pantalla distinta, navega a donde diga el servidor.
     *
     * Devuelve { navegando } cuando ya se fue, o el detalle del fallo para que
     * quien llama lo pinte donde corresponda.
     */
    window.SUIF.enviarYSeguir = function (formulario, opciones) {
        return window.SUIF.enviar(formulario, opciones).then(function (respuesta) {
            if (respuesta.ok && respuesta.datos.redirigir) {
                window.location.assign(respuesta.datos.redirigir);

                return { navegando: true, ok: true, mensaje: '', errores: {}, datos: respuesta.datos };
            }

            return {
                navegando: false,
                ok: respuesta.ok,
                mensaje: respuesta.ok
                    ? (respuesta.datos.mensaje || '')
                    : window.SUIF.mensajeError(respuesta),
                errores: window.SUIF.errores(respuesta.datos),
                datos: respuesta.datos
            };
        });
    };

    /*
     * Envio con aviso de avance.
     *
     * fetch no informa de cuanto lleva subido, y el paquete de referencias
     * puede pesar 50 MB: sin esto quien lo carga pulsa el boton y no vuelve a
     * saber nada hasta que termina, sin poder distinguir una subida lenta de
     * una pantalla colgada. XMLHttpRequest si lo dice, asi que es el unico
     * lugar donde se usa en vez de fetch.
     *
     * Devuelve lo mismo que enviar(): { ok, estado, datos }.
     */
    window.SUIF.enviarConProgreso = function (formulario, alAvanzar) {
        var cuerpo = new FormData(formulario);

        if (!cuerpo.get('_token')) {
            var meta = document.querySelector('meta[name="csrf-token"]');

            if (meta) {
                cuerpo.append('_token', meta.content);
            }
        }

        return new Promise(function (resolver) {
            var peticion = new XMLHttpRequest();

            peticion.open((formulario.method || 'POST').toUpperCase(), formulario.action, true);
            peticion.setRequestHeader('Accept', 'application/json');
            peticion.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            peticion.withCredentials = true;

            if (peticion.upload && typeof alAvanzar === 'function') {
                peticion.upload.addEventListener('progress', function (evento) {
                    /* lengthComputable es false cuando el navegador no conoce
                       el total; ahi no hay porcentaje que mostrar. */
                    if (evento.lengthComputable && evento.total > 0) {
                        alAvanzar(Math.round((evento.loaded / evento.total) * 100));
                    }
                });
            }

            peticion.addEventListener('load', function () {
                if (peticion.status === 419) {
                    window.location.reload();
                    resolver({ ok: false, estado: 419, datos: {} });

                    return;
                }

                var datos = {};

                try {
                    datos = peticion.responseText ? JSON.parse(peticion.responseText) : {};
                } catch (error) {
                    datos = {};
                }

                resolver({
                    ok: peticion.status >= 200 && peticion.status < 300,
                    estado: peticion.status,
                    datos: datos
                });
            });

            peticion.addEventListener('error', function () {
                resolver({
                    ok: false,
                    estado: 0,
                    datos: { mensaje: 'No fue posible conectar. Revisa tu conexion e intentalo de nuevo.' }
                });
            });

            peticion.send(cuerpo);
        });
    };

    /*
     * A donde va realmente este envio.
     *
     * Un boton con formaction manda a otra URL que la del <form>, y el proyecto
     * lo usa donde dos acciones comparten los mismos campos: en el dictamen,
     * "Guardar" e "Interrumpir" viven en el mismo formulario para que el motivo
     * escrito viaje con cualquiera de los dos. Leer solo formulario.action
     * mandaria la interrupcion a la ruta de guardar.
     */
    window.SUIF.destinoDeEnvio = function (formulario, evento) {
        var boton = evento && evento.submitter;

        return (boton && boton.getAttribute('formaction')) || formulario.action;
    };

    /*
     * Aplana el {campo: [mensajes]} de Laravel a {campo: mensaje}: las
     * pantallas enseñan un mensaje por campo, no la lista completa.
     */
    window.SUIF.errores = function (datos) {
        var errores = (datos && datos.errors) || {};
        var plano = {};

        Object.keys(errores).forEach(function (campo) {
            plano[campo] = Array.isArray(errores[campo]) ? errores[campo][0] : errores[campo];
        });

        return plano;
    };

    /*
     * Mensaje legible para cualquier respuesta que no sea correcta. El 429 lo
     * emiten los throttle de login, pre-registro y recuperación de clave: sin
     * esto el botón se quedaría mudo justo cuando hay algo que explicar.
     */
    window.SUIF.mensajeError = function (respuesta) {
        if (respuesta.datos && respuesta.datos.mensaje) {
            return respuesta.datos.mensaje;
        }

        switch (respuesta.estado) {
            case 422:
                return 'Revisa la información marcada.';
            case 429:
                return 'Demasiados intentos. Espera un momento antes de volver a intentarlo.';
            case 403:
                return 'No tienes permiso para realizar esta acción.';
            case 404:
                return 'El registro ya no está disponible.';
            default:
                return 'No fue posible completar la acción. Inténtalo de nuevo.';
        }
    };
}());
