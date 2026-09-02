{{--
    pdf/reportes/lista-asistencia.blade.php
    Lista de firmas de un grupo, para pasar asistencia el día del examen.

    Mismo molde que pdf/sede/comprobante.blade.php: el CSS se incrusta desde
    public/assets/css/pdf/ y los logos van por ruta del sistema de archivos,
    porque Dompdf no resuelve URL con enable_remote apagado.

    El encabezado de la tabla se repite en cada página gracias a <thead>: una
    lista de cien personas ocupa varias hojas y la última no puede quedarse sin
    saber qué columna es cuál.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de asistencia</title>
    <style>{!! $css !!}</style>
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

    <p class="titulo">Lista de asistencia</p>
    <p class="titulo__pie">{{ $total }} {{ $total === 1 ? 'persona citada' : 'personas citadas' }} · Emitida el {{ $emitido }} h</p>

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

    @if ($total === 0)
        <p class="vacio">Todavía no hay personas que hayan elegido este horario.</p>
    @else
        <table class="lista">
            <thead>
                <tr>
                    <th class="lista__numero">N.º</th>
                    <th class="lista__nombre">Nombre completo</th>
                    <th class="lista__curp">CURP</th>
                    <th class="lista__firma">Firma</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($personas as $persona)
                    <tr>
                        <td class="lista__numero">{{ $persona['numero'] }}</td>
                        <td class="lista__nombre">{{ $persona['nombre_completo'] }}</td>
                        <td class="lista__curp">{{ $persona['curp'] }}</td>
                        <td class="lista__firma"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="cierre">
            <tr>
                <td>
                    <span class="cierre__linea"></span>
                    <span class="cierre__rotulo">Nombre y firma de quien aplica</span>
                </td>
                <td>
                    <span class="cierre__linea"></span>
                    <span class="cierre__rotulo">Fecha de cierre de la lista</span>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
