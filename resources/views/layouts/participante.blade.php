{{--
    layouts/participante.blade.php
    Migrado desde: app/views/layouts/participante.php
    Layout base para el panel del participante autenticado.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SUIF — Mi Panel')</title>
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
