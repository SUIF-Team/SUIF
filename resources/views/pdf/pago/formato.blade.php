{{--
    pdf/pago/formato.blade.php
    Formato de pago · Certificación UIF, con el que la DEC emite el CFDI o el
    ticket. Los datos salen de FormatoPagoDec::datos().

    Es una réplica de la hoja «PROGRAMAS NACIONALES» del Excel de la DEC: las
    ocho columnas y el alto de cada renglón copian los de la hoja, en puntos,
    para que el papel salga como el que la DEC ya conoce. Por eso va en negro
    sobre blanco y no con los colores de SUIF. Las leyendas para llenar a mano
    («Favor de anotarlo de manera legible», «Anotar el banco») ya no van: el
    sistema escribe los datos.

    Mismo molde que pdf/sede/comprobante.blade.php: el CSS se incrusta desde
    public/assets/css/pdf/ y los logos van por ruta del sistema de archivos,
    porque Dompdf no resuelve URL con enable_remote apagado.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato de pago · Certificación UIF</title>
    <style>{!! file_get_contents(public_path('assets/css/pdf/formato-pago.css')) !!}</style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="encabezado__escudo">
                <img src="{{ public_path('assets/img/logos/unam-escudo-azul.jpg') }}" alt="Escudo de la UNAM">
            </td>
            <td class="encabezado__texto">
                UNIVERSIDAD NACIONAL AUTÓNOMA DE MÉXICO<br>
                FACULTAD DE CONTADURÍA Y ADMINISTRACIÓN<br>
                DIVISIÓN DE EDUCACIÓN CONTINUA
            </td>
            <td class="encabezado__dec">
                <img src="{{ public_path('assets/img/logos/dec.png') }}" alt="Logotipo de la División de Educación Continua">
            </td>
        </tr>
    </table>

    <p class="titulo">FORMATO DE PAGO CERTIFICACIÓN UIF</p>

    <table class="hoja">
        {{-- Sin contenido: sólo fija el ancho de las columnas A–H. --}}
        <tr class="regla">
            <td class="col-a"></td>
            <td class="col-b"></td>
            <td class="col-c"></td>
            <td class="col-d"></td>
            <td class="col-e"></td>
            <td class="col-f"></td>
            <td class="col-g"></td>
            <td class="col-h"></td>
        </tr>

        <tr class="r13"><td colspan="8"></td></tr>
        <tr class="r13">
            <td colspan="8">
                <div class="seccion">DATOS PARA FACTURAR <span class="seccion__nota">(LLENAR ÚNICAMENTE SI LO REQUIERE)</span></div>
            </td>
        </tr>
        <tr class="r8"><td colspan="8"></td></tr>
        <tr class="r13">
            <td class="rotulo">NOMBRE:</td>
            <td class="valor" colspan="7">{{ $nombre_fiscal }}</td>
        </tr>
        <tr class="r18">
            <td class="rotulo">RFC:</td>
            <td class="valor" colspan="3">{{ $rfc }}</td>
            <td></td>
            <td class="rotulo rotulo--grande">USO:</td>
            <td class="uso" colspan="2">{{ $uso }}</td>
        </tr>
        <tr class="r13"><td colspan="8"></td></tr>
        <tr class="r18">
            <td class="rotulo">C.P:</td>
            <td class="valor" colspan="3">{{ $codigo_postal }}</td>
            <td colspan="4"></td>
        </tr>
        <tr class="r8"><td colspan="8"></td></tr>
        <tr class="r27">
            <td class="regimen__rotulo" colspan="2">RÉGIMEN FISCAL:</td>
            <td class="regimen" colspan="6">{{ $regimen }}</td>
        </tr>
        <tr class="r11"><td colspan="8"></td></tr>

        <tr class="r13">
            <td colspan="8">
                <div class="seccion">DATOS PARTICIPANTE <span class="seccion__nota">(OBLIGATORIO)</span></div>
            </td>
        </tr>
        <tr class="r8"><td colspan="8"></td></tr>
        <tr class="r18">
            <td class="rotulo">NOMBRE:</td>
            <td class="valor" colspan="7">{{ $participante }}</td>
        </tr>
        <tr class="r13"><td colspan="8"></td></tr>

        <tr class="r13">
            <td colspan="8">
                <div class="seccion">INFORMACIÓN DEL EVENTO Y FORMA DE PAGO <span class="seccion__nota">(OBLIGATORIO)</span></div>
            </td>
        </tr>
        <tr class="r8"><td colspan="8"></td></tr>
        <tr class="r27">
            <td class="texto">EVENTO:</td>
            <td class="valor valor--evento" colspan="4">{{ $evento }}</td>
            <td></td>
            <td class="rotulo">IMPORTE:</td>
            <td class="valor">{{ $importe }}</td>
        </tr>
        <tr class="r18">
            <td class="texto" colspan="3">FORMA DE PAGO:</td>
            <td class="rotulo rotulo--negritas" colspan="2">REFERENCIA BANCARIA:</td>
            <td></td>
            <td class="valor" colspan="2">{{ $referencia }}</td>
        </tr>
        {{-- Las casillas van en el orden de la hoja: tarjetas a la izquierda,
             depósito y transferencia a la derecha. --}}
        <tr class="r18">
            <td class="texto" colspan="2">CREDITO</td>
            <td class="casilla"><div>{{ $forma_pago === 'Tarjeta de crédito' ? 'X' : '' }}</div></td>
            <td class="texto" colspan="3">DEPÓSITO BANCARIO</td>
            <td class="casilla"><div>{{ $forma_pago === 'Depósito bancario' ? 'X' : '' }}</div></td>
            <td></td>
        </tr>
        <tr class="r18">
            <td class="texto" colspan="2">DÉBITO</td>
            <td class="casilla"><div>{{ $forma_pago === 'Tarjeta de débito' ? 'X' : '' }}</div></td>
            <td class="texto" colspan="3">TRANSFERENCIA</td>
            <td class="casilla"><div>{{ $forma_pago === 'Transferencia' ? 'X' : '' }}</div></td>
            <td></td>
        </tr>
        {{-- «Atendido por» es una línea de firma: el nombre va debajo, como
             lo trae la hoja de la DEC. --}}
        <tr class="r18">
            <td class="rotulo">TARJETA:</td>
            <td class="valor" colspan="2">{{ $banco }}</td>
            <td class="rotulo" colspan="2">ATENDIDO POR:</td>
            <td></td>
            <td class="valor" colspan="2"></td>
        </tr>
        <tr class="r13">
            <td colspan="6"></td>
            <td class="firma" colspan="2">{{ $atendido_por }}</td>
        </tr>
        <tr class="r17"><td class="corte" colspan="8"></td></tr>
    </table>
</body>
</html>
