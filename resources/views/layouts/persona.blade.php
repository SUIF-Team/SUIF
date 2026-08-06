<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#112f4b">
    <title>@yield('title', 'SUIF') | Sistema Integral de Certificaciones</title>
    <link rel="icon" href="{{ asset('assets/img/logos/fca-unam-logo.ico') }}" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Raleway:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/navbar-sistema.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/salida-sistema.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/sidebar-persona.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/footer.css') }}">
    @yield('styles')
    @stack('styles')
</head>
<body class="pagina-sistema persona-layout d-flex min-vh-100 flex-column">
    @include('partials.navbar-sistema')

    <?php
        /* El dashboard no necesita la barra de avance: la pantalla entera ES el avance. */
        $sinSidebar = request()->routeIs('persona.dashboard');
    ?>

    <div class="persona-shell flex-grow-1 @if($sinSidebar) persona-shell--sin-sidebar @endif">
        @unless($sinSidebar)
            <aside class="persona-sidebar" aria-label="Avance del trámite">
                @include('partials.sidebar-progreso')

                <div class="persona-sidebar__bottom">
                    <div class="persona-soporte">
                        <strong>Soporte</strong>
                        <a href="mailto:{{ config('suif.soporte_correo') }}">{{ config('suif.soporte_correo') }}</a>
                    </div>
                </div>
            </aside>
        @endunless

        <main id="contenido-principal" class="persona-main">
            @unless($sinSidebar)
                <div class="persona-barra">
                    <a href="{{ route('persona.dashboard') }}" class="persona-barra__volver">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        <span>Volver a mi proceso</span>
                    </a>
                </div>
            @endunless

            {{-- @include('partials.alertas') --}}
            @yield('content')
        </main>
    </div>

    @include('partials.footer')

    @include('partials.scripts')
    @yield('scripts')
    @stack('scripts')
</body>
</html>