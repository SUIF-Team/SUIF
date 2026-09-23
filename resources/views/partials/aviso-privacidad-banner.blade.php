{{--
    Aviso que aparece al entrar al sitio. Informa, no bloquea: la ley pide
    avisar, no pedir permiso para navegar.

    Quien ya lo cerró no lo recibe: main.js deja una cookie al cerrarlo y aquí
    se consulta antes de pintar. Antes la marca vivía en localStorage, que el
    servidor no puede leer, así que el aviso salía siempre y se escondía después
    de cargar; se veía parpadear en cada recarga, y en todas las pantallas,
    porque el parcial va en el pie.

    Sin JavaScript el aviso se ve igual, sólo que no se puede cerrar.
--}}
@if(! request()->cookie('suif_aviso_privacidad'))
    <aside class="aviso-banner" data-aviso-privacidad role="region" aria-label="Aviso de privacidad">
        <div class="aviso-banner__contenido">
            <p class="aviso-banner__texto">
                Este sistema trata datos personales para el trámite de certificación y usa una cookie
                necesaria para tu sesión. Consulta el
                <a href="{{ config('suif.enlaces.aviso_privacidad') }}" target="_blank" rel="noopener noreferrer">aviso de privacidad integral</a>
                o su <a href="{{ config('suif.enlaces.aviso_privacidad_simplificado') }}" target="_blank" rel="noopener noreferrer">versión simplificada</a>.
            </p>

            <button type="button" class="aviso-banner__boton" data-aviso-privacidad-cerrar>
                Entendido
            </button>
        </div>
    </aside>
@endif
