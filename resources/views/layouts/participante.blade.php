<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SUIF') | Sistema Integral de Certificaciones</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/participante-dashboard.css') }}">
    @stack('styles')
</head>
<body class="suif-shell">
<header class="suif-header">
    <div class="suif-header__left">
        <img src="{{ asset('assets/img/logos/fca-unam-logo.png') }}" alt="UNAM y FCA" class="suif-header__fca">
        <span class="suif-header__line"></span>
        <p>Sistema Integral<br>de Certificaciones</p>
    </div>
    <img src="{{ asset('assets/img/logos/uif-blanco.png') }}" alt="Unidad de Inteligencia Financiera México" class="suif-header__uif">
</header>

<div class="suif-body">
    <aside class="suif-sidebar">
        <div class="suif-sidebar__bottom">
            <form method="POST" action="{{ route('logout') }}">
                {{ csrf_field() }}
                <button type="submit" class="suif-logout">
                    <span class="suif-logout__icon">↪</span><span>Salir</span>
                </button>
            </form>
            <div class="suif-support">
                <strong>Soporte</strong>
                <a href="mailto:{{ config('suif.soporte_correo') }}">{{ config('suif.soporte_correo') }}</a>
            </div>
        </div>
    </aside>
    <main class="suif-main">
        @include('partials.alertas')
        @yield('content')
    </main>
</div>

<footer class="suif-footer">
    <div class="suif-footer__brand">
        <img src="{{ asset('assets/img/logos/unam-logo.png') }}" alt="UNAM">
        <div class="suif-475"><span>475+</span><small>Universidad<br>de México</small></div>
    </div>
    <div class="suif-footer__legal">
        <p>Hecho en México<br>D.R. © {{ date('Y') }}</p>
        <p>Esta página puede ser reproducida con fines no lucrativos, siempre y cuando no se mutile, se cite la fuente completa y su dirección electrónica.<br>
        De otra forma requiere permiso previo por escrito de la institución. <a href="{{ config('suif.enlaces.aviso_privacidad') }}">Aviso de privacidad</a>. Sitio web administrado por el Centro de Informática de la Facultad de Contaduría y Administración (<a href="{{ config('suif.enlaces.cifca') }}">CIFCA</a>).<br>
        <a href="{{ config('suif.enlaces.documento_seguridad') }}">Documento de seguridad</a> | <a href="{{ config('suif.enlaces.instrumento_juridico') }}">Instrumento jurídico</a> | <a href="{{ config('suif.enlaces.aviso_privacidad_simplificado') }}">Aviso de privacidad simplificado</a></p>
    </div>
</footer>
@stack('scripts')
</body>
</html>
