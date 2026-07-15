{{--
    layouts/landing.blade.php
    Migrado desde: app/views/layouts/landing.php
    Layout base para la página pública / landing.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SUIF — Unidad de Inteligencia Financiera')</title>
    @yield('head')
</head>
<body>
    @include('partials.header')

    @yield('content')

    @include('partials.footer')

    @yield('scripts')
</body>
</html>
