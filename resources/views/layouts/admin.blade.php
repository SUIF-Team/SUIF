{{--
    layouts/admin.blade.php
    Layout base para el panel administrativo.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SUIF — Administración')</title>
    <link rel="icon" href="{{ asset('assets/img/logos/fca-unam-logo.ico') }}" type="image/x-icon">

    @include('partials.estilos-cdn')
    @include('partials.fuentes')
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/componentes.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/navbar-sistema.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/footer.css') }}">

    @yield('styles')
</head>
<body class="pagina-sistema d-flex min-vh-100 flex-column">
    @include('partials.navbar-sistema')

    <main class="flex-grow-1">
        @include('partials.alertas')
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.scripts')
    @yield('scripts')
</body>
</html>
