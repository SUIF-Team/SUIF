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
    <title>@yield('title', 'SUIF — Administración')</title>
    @yield('head')
</head>
<body>
    @include('partials.sidebar')

    <main>
        @include('partials.alertas')
        @yield('content')
    </main>

    @include('partials.footer')

    @yield('scripts')
</body>
</html>
