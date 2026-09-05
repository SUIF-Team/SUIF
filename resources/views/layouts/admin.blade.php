{{--
    layouts/admin.blade.php
    Migrado desde: app/views/layouts/admin.php
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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @include('partials.fuentes')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/navbar-sistema.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/footer.css') }}">

    @yield('styles')
    @yield('head')
</head>
<body class="pagina-sistema d-flex min-vh-100 flex-column">
    @include('partials.navbar-sistema')

    @include('partials.sidebar')

    <main class="flex-grow-1">
        @include('partials.alertas')
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.scripts')
    @yield('scripts')
</body>
</html>
