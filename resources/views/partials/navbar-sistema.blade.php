<header class="navbar-sistema" aria-label="Identidad institucional de SUIF">
    <div class="navbar-sistema-contenido">
        <div class="navbar-sistema-identidad">
            <div class="navbar-sistema-emblemas" role="group" aria-label="Universidad Nacional Autónoma de México y Facultad de Contaduría y Administración">
                <a href="{{ config('suif.enlaces.unam') }}" target="_blank" rel="noopener noreferrer" class="navbar-logo-link" aria-label="Visitar el sitio de la Universidad Nacional Autónoma de México (se abre en una pestaña nueva)">
                    <img
                        src="{{ asset('assets/img/logos/unam-logo.png') }}"
                        alt=""
                        class="navbar-sistema-logo navbar-sistema-logo-unam">
                </a>
                <a href="{{ config('suif.enlaces.fca') }}" target="_blank" rel="noopener noreferrer" class="navbar-logo-link" aria-label="Visitar el sitio de la Facultad de Contaduría y Administración (se abre en una pestaña nueva)">
                    <img
                        src="{{ asset('assets/img/logos/fca-unam-logo.png') }}"
                        alt=""
                        class="navbar-sistema-logo navbar-sistema-logo-fca">
                </a>
            </div>

            <span class="navbar-sistema-divisor" aria-hidden="true"></span>

            <p class="navbar-sistema-titulo">
                Sistema Integral<br>
                de Certificaciones
            </p>
        </div>

        <a href="{{ config('suif.enlaces.uif') }}" target="_blank" rel="noopener noreferrer" class="navbar-logo-link navbar-sistema-logo-link-uif" aria-label="Visitar el sitio de la Unidad de Inteligencia Financiera (se abre en una pestaña nueva)">
            <img
                src="{{ asset('assets/img/logos/uif-blanco.png') }}"
                alt=""
                class="navbar-sistema-logo-uif">
        </a>
    </div>
</header>
