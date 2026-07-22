<nav class="navbar navbar-expand-xl navbar-dark fixed-top site-navbar" data-site-navbar aria-label="Navegación principal">
    <div class="container-fluid px-3 px-xl-5">
        <div class="navbar-logos" aria-label="Instituciones responsables">
            <a href="{{ config('suif.enlaces.unam') }}" target="_blank" rel="noopener noreferrer" class="navbar-logo-link" aria-label="Visitar el sitio de la Universidad Nacional Autónoma de México (se abre en una pestaña nueva)">
                <img src="{{ asset('assets/img/logos/unam-logo.png') }}" alt="" class="navbar-logo navbar-logo-unam">
            </a>

            <a href="{{ config('suif.enlaces.fca') }}" target="_blank" rel="noopener noreferrer" class="navbar-logo-link" aria-label="Visitar el sitio de la Facultad de Contaduría y Administración (se abre en una pestaña nueva)">
                <img src="{{ asset('assets/img/logos/fca-unam-logo.png') }}" alt="" class="navbar-logo navbar-logo-fca">
            </a>

            <a href="{{ config('suif.enlaces.uif') }}" target="_blank" rel="noopener noreferrer" class="navbar-logo-link navbar-logo-link-uif" aria-label="Visitar el sitio de la Unidad de Inteligencia Financiera (se abre en una pestaña nueva)">
                <img src="{{ asset('assets/img/logos/uif-blanco.png') }}" alt="" class="navbar-logo navbar-logo-uif">
            </a>
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Abrir menú de navegación">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
            <div class="navbar-nav ms-auto">
                <a href="{{ route('home') }}#inicio" class="nav-link" data-navbar-anchor>Inicio</a>
                <a href="{{ route('home') }}#convocatoria" class="nav-link" data-navbar-anchor>Convocatoria</a>
                <a href="{{ route('home') }}#proceso" class="nav-link" data-navbar-anchor>Proceso</a>
                <a href="{{ route('home') }}#instructivo" class="nav-link" data-navbar-anchor>Instructivo</a>
                <a href="{{ route('home') }}#faq" class="nav-link" data-navbar-anchor>FAQ</a>
                <a href="{{ route('login') }}" class="btn navbar-login-button">Iniciar sesión</a>
            </div>
        </div>
    </div>
</nav>
