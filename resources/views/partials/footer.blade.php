<footer class="site-footer">
    <div class="site-footer__brand">
        <div class="container site-footer__brand-inner">
            <div class="site-footer__institution" aria-label="Universidad Nacional Autónoma de México">
                <img src="{{ asset('assets/img/logos/unam-logo.png') }}" alt="Escudo de la UNAM" class="site-footer__unam-logo">
                <span class="site-footer__divider" aria-hidden="true"></span>
                <div class="site-footer__anniversary">
                    <span>475+</span>
                    <small>Universidad<br>de México</small>
                </div>
            </div>

            <div class="site-footer__social" aria-label="Redes sociales">
                <a href="https://www.facebook.com/SEFCAUNAM" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                    <i class="fab fa-facebook-f" aria-hidden="true"></i>
                </a>
                <a href="https://www.youtube.com/@SEFCA" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                    <i class="fab fa-youtube" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="site-footer__legal">
        <div class="container site-footer__legal-grid">
            <p class="site-footer__copyright">
                Hecho en México<br>
                D.R. &copy; {{ date('Y') }}
            </p>

            <p>
                Esta página puede ser reproducida con fines no lucrativos, siempre y cuando no se mutile, se cite la fuente completa y su dirección electrónica. De otra forma requiere permiso previo por escrito de la institución.
                <a href="{{ config('suif.enlaces.aviso_privacidad') }}" target="_blank" rel="noopener noreferrer">Aviso de privacidad</a>.
                Sitio web administrado por el Centro de Informática de la Facultad de Contaduría y Administración
                (<a href="{{ config('suif.enlaces.cifca') }}" target="_blank" rel="noopener noreferrer">CIFCA</a>).<br>
                <a href="{{ config('suif.enlaces.documento_seguridad') }}" target="_blank" rel="noopener noreferrer">Documento de seguridad</a>
                <span aria-hidden="true"> | </span>
                <a href="{{ config('suif.enlaces.instrumento_juridico') }}" target="_blank" rel="noopener noreferrer">Instrumento jurídico</a>
                <span aria-hidden="true"> | </span>
                <a href="{{ config('suif.enlaces.aviso_privacidad_simplificado') }}" target="_blank" rel="noopener noreferrer">Aviso de privacidad simplificado</a>
            </p>
        </div>
    </div>
</footer>

<button type="button" class="back-to-top" data-back-to-top aria-label="Volver al inicio de la página">
    <i class="fas fa-arrow-up" aria-hidden="true"></i>
</button>
