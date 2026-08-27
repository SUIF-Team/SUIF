{{--
    pdf/sede/comprobante.blade.php
    Comprobante de la sede y el horario que la persona confirmó.

    El CSS se incrusta desde public/assets/css/pdf/ y los logos se referencian
    por ruta del sistema de archivos porque Dompdf no resuelve URL con
    enable_remote apagado. public_path() cae dentro del chroot del paquete.

    El mapa de la pantalla es un iframe de Google Maps y eso no viaja a un PDF:
    aquí va un QR con el mismo enlace, para abrir la ubicación desde el teléfono.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de sede y horario</title>
    <style>{!! file_get_contents(public_path('assets/css/pdf/comprobante-sede.css')) !!}</style>
</head>
<body>
    <table class="membrete">
        <tr>
            <td class="membrete__escudos">
                <img src="{{ public_path('assets/img/logos/fca-unam-azul.png') }}" alt="Escudos de la UNAM y de la FCA">
            </td>
            <td class="membrete__texto">
                <strong>UNIVERSIDAD NACIONAL AUTÓNOMA DE MÉXICO</strong>
                <strong>FACULTAD DE CONTADURÍA Y ADMINISTRACIÓN</strong>
                <span>División de Educación Continua</span>
            </td>
            <td class="membrete__uif">
                <img src="{{ public_path('assets/img/logos/uif.png') }}" alt="Logotipo de la UIF">
            </td>
        </tr>
    </table>

    <p class="titulo">Comprobante de sede y horario</p>
    <p class="titulo__folio">Folio de solicitud {{ $folio }} · Emitido el {{ $emitido }} h</p>

    <div class="bloque">
        <p class="bloque__titulo">Persona participante</p>
        <table class="datos">
            <tr>
                <th>Nombre</th>
                <td>{{ $persona }}</td>
            </tr>
            <tr>
                <th>CURP</th>
                <td>{{ $curp }}</td>
            </tr>
        </table>
    </div>

    <div class="bloque">
        <p class="bloque__titulo">Aplicación de la evaluación</p>
        <table class="aplicacion">
            <tr>
                <td class="aplicacion__datos">
                    <table class="datos">
                        <tr>
                            <th>Sede</th>
                            <td>{{ $sede }}</td>
                        </tr>
                        <tr>
                            <th>Dirección</th>
                            <td>{{ $direccion }}</td>
                        </tr>
                        <tr>
                            <th>Fecha</th>
                            <td>{{ $fecha }}</td>
                        </tr>
                        <tr>
                            <th>Horario</th>
                            <td>{{ $horario }}</td>
                        </tr>
                    </table>
                </td>
                <td class="aplicacion__mapa">
                    <img src="{{ $qr }}" alt="Código QR con la ubicación de la sede">
                    <p>Escanea para abrir la ubicación en Google Maps</p>
                </td>
            </tr>
        </table>
    </div>

    @if($recomendaciones)
        <div class="bloque">
            <p class="bloque__titulo">Recomendaciones para el día de la evaluación</p>
            <table class="recomendaciones">
                @foreach($recomendaciones as $recomendacion)
                    <tr class="{{ $loop->first ? 'recomendaciones__primera' : '' }} {{ $loop->last ? 'recomendaciones__ultima' : '' }}">
                        <td class="recomendaciones__vineta">·</td>
                        <td>{{ $recomendacion }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <p class="pie">
        <strong>Este comprobante no sustituye a la identificación oficial.</strong>
        Preséntalo impreso el día de la evaluación junto con una identificación
        oficial vigente. La ubicación del mapa es aproximada y se obtiene de la
        dirección registrada de la sede.
    </p>
</body>
</html>
