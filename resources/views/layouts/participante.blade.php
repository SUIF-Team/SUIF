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
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/partials/navbar-sistema.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/partials/sidebar-participante.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/partials/footer.css') }}">
    @yield('styles')
    @stack('styles')
</head>
<body class="pagina-sistema participante-layout d-flex min-vh-100 flex-column">
    @include('partials.navbar-sistema')

    <div class="participante-shell flex-grow-1">
        <aside class="participante-sidebar" aria-label="Opciones de la cuenta">
            @yield('participante_sidebar')
            <div class="participante-sidebar__bottom">
                @if (auth()->check())
                    <form method="POST" action="{{ route('logout') }}">
                        {{ csrf_field() }}
                        <button type="submit" class="participante-salir">
                            <span class="participante-salir__icon" aria-hidden="true">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            </span>
                            <span>Salir</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('home') }}" class="participante-salir">
                        <span class="participante-salir__icon" aria-hidden="true">
                            <i class="fa-solid fa-arrow-left"></i>
                        </span>
                        <span>Salir</span>
                    </a>
                @endif

                <div class="participante-soporte">
                    <strong>Soporte</strong>
                    <a href="mailto:{{ config('suif.soporte_correo') }}">{{ config('suif.soporte_correo') }}</a>
                </div>
            </div>
        </aside>

        <main id="contenido-principal" class="participante-main">
            @include('partials.alertas')
            @yield('content')
        </main>
    </div>

    @include('partials.footer')

    @include('partials.scripts')
    @yield('scripts')
    @stack('scripts')
</body>
</html>
