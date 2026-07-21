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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/partials/footer.css') }}">

    @yield('head')
</head>
<body class="d-flex min-vh-100 flex-column">
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
