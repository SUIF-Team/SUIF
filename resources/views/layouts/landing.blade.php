<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#112f4b">
    <title>@yield('title', 'SUIF — Unidad de Inteligencia Financiera')</title>
    <link rel="icon" href="{{ asset('assets/img/logos/fca-unam-logo.ico') }}" type="image/x-icon">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,400&family=Open+Sans:wght@300;400;500;600;700&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="{{ asset_versionado('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/partials/footer.css') }}">

    @yield('styles')
    @yield('head')
</head>
<body class="@yield('body_class', 'landing-page')">
    @include('partials.navbar')

    <main class="landing-main">
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.scripts')
    @yield('scripts')
</body>
</html>
