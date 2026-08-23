{{--
    pdf/preregistro/layout.blade.php
    Estructura común de los cuatro formatos oficiales del pre-registro.
    Cada formato aporta su asunto, la continuación del párrafo de apertura y
    su cuerpo; el resto —lugar y fecha, destinatario, datos de la persona,
    cierre y línea de firma— es idéntico en los cuatro.

    El CSS se incrusta desde public/assets/css/pdf/ porque Dompdf no resuelve
    hojas de estilo por URL con enable_remote apagado.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('asunto')</title>
    <style>{!! file_get_contents(public_path('assets/css/pdf/preregistro-formato.css')) !!}</style>
</head>
<body>
    <p class="fecha">{{ $lugar }}, a {{ $dia }} de {{ $mes }} de 2024</p>

    <p class="asunto"><strong>Asunto:</strong> @yield('asunto')</p>

    <p class="destinatario">
        <strong>
            Unidad de Inteligencia Financiera de la<br>
            Secretaría de Hacienda y Crédito Público (UIF)<br>
            Presente
        </strong>
    </p>

    <p>
        Quien suscribe, <span class="dato">{{ $nombre }}</span> con R.F.C.
        <span class="dato">{{ $rfcBase }}</span> y homoclave
        <span class="dato">{{ $homoclave }}</span>, @yield('apertura')
    </p>

    @yield('cuerpo')

    <p class="atentamente"><strong>Atentamente</strong></p>

    <p class="firma">Nombre y firma de la Persona participante</p>
</body>
</html>
