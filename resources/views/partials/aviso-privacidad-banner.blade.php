{{--
    Aviso que aparece al entrar al sitio. Informa, no bloquea: SUIF sólo usa la
    cookie de sesión, así que la ley pide avisar, no pedir permiso para navegar.
    Se pinta siempre y main.js lo oculta si ya se cerró antes en este navegador.
--}}
<aside class="aviso-banner" data-aviso-privacidad role="region" aria-label="Aviso de privacidad">
    <div class="aviso-banner__contenido">
        <p class="aviso-banner__texto">
            Este sistema trata datos personales para el trámite de certificación y usa una cookie
            necesaria para tu sesión. Consulta el
            <a href="{{ route('aviso-privacidad') }}">aviso de privacidad integral</a>
            o su <a href="{{ route('aviso-privacidad.simplificado') }}">versión simplificada</a>.
        </p>

        <button type="button" class="aviso-banner__boton" data-aviso-privacidad-cerrar>
            Entendido
        </button>
    </div>
</aside>
