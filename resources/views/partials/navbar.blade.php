<?php
// Determinar la página activa
$pagina_actual = basename($_SERVER['PHP_SELF']);

$activo_inicio = ''; // Inicio ya no queda seleccionado por defecto

$activo_directiva = ($pagina_actual == 'nosotros.php') ? 'active' : '';
$activo_voces = ($pagina_actual == 'voces.php') ? 'active' : '';
$activo_beneficios = ($pagina_actual == 'beneficios.php') ? 'active' : '';

$activo_eventos = in_array($pagina_actual, array('eventos.php', 'evento.php')) ? 'active' : '';
$activo_proyectos = ($pagina_actual == 'proyectos.php') ? 'active' : '';

$activo_nosotros = in_array($pagina_actual, array('nosotros.php', 'voces.php', 'beneficios.php')) ? 'active' : '';
$activo_iniciativas = in_array($pagina_actual, array('eventos.php', 'proyectos.php', 'evento.php')) ? 'active' : '';
?>
<!-- Navbar Start -->
<nav class="navbar navbar-expand-custom navbar-dark fixed-top px-custom-5">

    <!-- Logos: UNAM → FCA → SEFCA -->
    <div class="navbar-logos">
        <a href="https://www.unam.mx/" target="_blank" class="navbar-logo-link">
            <img src="{{ asset('assets/img/logos/unam-logo.png') }}" alt="UNAM" class="navbar-logo navbar-logo-unam">
        </a>
        <a href="https://www.fca.unam.mx/" target="_blank" class="navbar-logo-link">
            <img src="{{ asset('assets/img/logos/fca-unam-logo.png') }}" alt="FCA" class="navbar-logo navbar-logo-fca">
        </a>
        <a href="index.php" class="navbar-logo-link">
            <img src="{{ asset('assets/img/logos/uif-blanco.png') }}" alt="Unidad de Inteligencia Financiera" class="navbar-logo navbar-logo-uif">
        </a>
    </div>

    <button type="button" class="navbar-toggler me-4" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Abrir menú">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarCollapse">
        <div class="navbar-nav ms-auto px-4 py-2 p-custom-0">
            <a href="#inicio" class="nav-item nav-link">Inicio</a>
            <a href="#convocatoria" class="nav-item nav-link">Convocatoria</a>
            <a href="#proceso" class="nav-item nav-link">Proceso</a>
            <a href="#instructivo" class="nav-item nav-link">Instructivo</a>
            <a href="#faq" class="nav-item nav-link">FAQ</a>

            <!-- Nosotros Dropdown -->
            <!-- <div class="nav-item dropdown nav-dropdown-desktop">
                <a href="#" class="nav-link dropdown-toggle <?php echo $activo_nosotros; ?>" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    Nosotros <i class="fa fa-angle-down ms-1"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-desktop">
                    <a href="#" class="dropdown-item dropdown-back"><i class="fa fa-chevron-left me-2"></i> Volver atrás</a>
                    <a href="nosotros.php" class="dropdown-item <?php echo $activo_directiva; ?>">Directiva</a>
                    <a href="voces.php" class="dropdown-item <?php echo $activo_voces; ?>">Voces</a>
                    <a href="beneficios.php" class="dropdown-item <?php echo $activo_beneficios; ?>">Beneficios</a>
                </div>
            </div> -->

            <!-- Iniciativas Dropdown -->
            <!-- <div class="nav-item dropdown nav-dropdown-desktop">
                <a href="#" class="nav-link dropdown-toggle <?php echo $activo_iniciativas; ?>" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    Iniciativas <i class="fa fa-angle-down ms-1"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-desktop">
                    <a href="#" class="dropdown-item dropdown-back"><i class="fa fa-chevron-left me-2"></i> Volver atrás</a>
                    <a href="eventos.php" class="dropdown-item <?php echo $activo_eventos; ?>">Eventos</a>
                    <a href="proyectos.php" class="dropdown-item <?php echo $activo_proyectos; ?>">Proyectos</a>
                </div>
            </div> -->

            <a href="#" class="afiliacion-btn btn btn-pill btn-blue">Iniciar Sesión</a>
        </div>
    </div>
</nav>
<!-- Navbar End -->
