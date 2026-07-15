{{--
    layouts/auth.blade.php
    Migrado desde: app/views/layouts/auth.php
    Layout base para las vistas de autenticación (login, registro).
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SUIF — Acceso')</title>
    @yield('head')
</head>
<body>
    @yield('content')

    @yield('scripts')
</body>
</html>
