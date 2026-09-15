<?php

namespace App\Servicios;

use App\Support\Admin\ConsultaPagos;
use DomainException;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * FormatoPagoDec
 *
 * Responsabilidad: llenar el «Formato de pago · Certificación UIF» con el que
 * la DEC emite el CFDI o el ticket de un pago ya validado.
 *
 * La plantilla es el Excel de la propia DEC y se edita como lo que es, un zip
 * de XML. PhpSpreadsheet, que usa LibroExcel, al abrir y guardar un libro sólo
 * conserva las imágenes: aquí se llevaría las cuatro casillas de la forma de
 * pago y los marcos de las secciones, que son formas dibujadas. Reescribiendo
 * sólo las celdas del formato y la casilla marcada, lo demás queda como lo
 * entregó la DEC.
 *
 * La plantilla se versiona sin los nombres del personal que traía la DEC —el
 * «Atendido por» de G29, la lista de la hoja oculta Hoja1 y el autor del
 * archivo—, porque AGENTS.md no admite datos reales en Git. Si la DEC manda
 * otra versión, se limpia igual antes de comitearla.
 *
 * ponytail: CASILLAS y las celdas de celdas() están atadas a esta versión de
 * la plantilla. Si la DEC la cambia, se actualizan aquí; FormatoPagoTest lee
 * el archivo generado y falla si algo deja de caer donde debe.
 */
class FormatoPagoDec
{
    /* Formas de pago que llevan banco: el formato lo pide bajo «TARJETA».
       El catálogo se consulta por nombre, como los demás del sistema. */
    public const CON_TARJETA = ['Tarjeta de crédito', 'Tarjeta de débito'];

    private const PLANTILLA = 'plantillas/formato-pago-uif.xlsx';

    /* La hoja visible, «PROGRAMAS NACIONALES», y el dibujo de sus casillas. */
    private const HOJA = 'xl/worksheets/sheet4.xml';

    private const DIBUJO = 'xl/drawings/drawing4.xml';

    /* La celda sobre la que está dibujada la casilla de cada forma de pago. */
    private const CASILLAS = [
        'Tarjeta de crédito' => 'C26',
        'Tarjeta de débito' => 'C27',
        'Depósito bancario' => 'G26',
        'Transferencia' => 'G27',
    ];

    private const NS_HOJA = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_DIBUJO = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

    private const NS_TRAZO = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const TIPO_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Formas de pago para el formulario del comprobante, en el orden de las
     * casillas del formato.
     *
     * @return array<int, array{id: int, nombre: string, conTarjeta: bool}>
     */
    public function metodosPago(): array
    {
        return DB::table('metodo_pago')
            ->orderBy('mepa_id_metodo_pago')
            ->get()
            ->map(fn (object $metodo): array => [
                'id' => (int) $metodo->mepa_id_metodo_pago,
                'nombre' => (string) $metodo->mepa_metodo_pago,
                'conTarjeta' => in_array($metodo->mepa_metodo_pago, self::CON_TARJETA, true),
            ])
            ->all();
    }

    /**
     * Bancos para el formulario del comprobante. Van en el orden del script,
     * que deja «Otro» al final.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public function bancos(): array
    {
        return DB::table('banco')
            ->orderBy('banc_id_banco')
            ->get()
            ->map(fn (object $banco): array => [
                'id' => (int) $banco->banc_id_banco,
                'nombre' => (string) $banco->banc_banco,
            ])
            ->all();
    }

    /**
     * Por qué todavía no se puede generar el formato; null si ya se puede.
     *
     * @param  array<string, mixed>  $pago  ConsultaPagos::pago()
     * @param  array<int, array<string, mixed>>  $responsables  GestionResponsables::activos()
     */
    public function motivoNoDisponible(array $pago, array $responsables): ?string
    {
        if ($pago['estado_persistido'] !== ConsultaPagos::COMPLETADO) {
            return 'El formato se genera cuando el pago está validado.';
        }

        /* Sin datos fiscales la DEC no puede facturar: el formato saldría con
           la sección de facturación vacía y el uso «gastos en general». */
        if ($pago['uso_cfdi'] === true && $pago['datos_fiscales'] === null) {
            return 'La persona eligió CFDI y todavía no captura sus datos de facturación.';
        }

        if ($responsables === []) {
            return 'No hay responsables activos. Da de alta a quien atiende los pagos en «Responsables de pago».';
        }

        return null;
    }

    /**
     * Genera el formato, deja registrado quién atendió el pago y lo entrega
     * como descarga.
     *
     * @param  array<string, mixed>  $pago  ConsultaPagos::pago()
     * @param  array{id: int, nombre_completo: string}  $responsable
     */
    public function descarga(array $pago, array $responsable): Response
    {
        /* El evento es la convocatoria de la solicitud. Las solicitudes de un
           pago compartido son todas de la misma. */
        $evento = (string) DB::table('solicitud as s')
            ->join('convocatoria as cv', 'cv.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->where('s.soli_id_pago', (int) $pago['id'])
            ->orderBy('s.soli_id_solicitud')
            ->value('cv.conv_nombre');

        $contenido = $this->libro(
            resource_path(self::PLANTILLA),
            $this->celdas($pago, $responsable['nombre_completo'], $evento),
            self::CASILLAS[$pago['metodo_pago'] ?? ''] ?? null
        );

        /* Se escribe después de generar: si la plantilla falla, el pago no se
           queda con el responsable de un formato que nunca existió. */
        DB::table('pago')
            ->where('pago_id_pago', (int) $pago['id'])
            ->update(['pago_id_responsable' => $responsable['id']]);

        /* La referencia sale del CSV de la DEC: se deja sólo lo alfanumérico
           para que no pueda partir el encabezado. */
        $referencia = preg_replace('/[^A-Za-z0-9]/', '', (string) $pago['referencia_bancaria']) ?: $pago['id'];

        return response($contenido, 200, [
            'Content-Type' => self::TIPO_XLSX,
            'Content-Disposition' => 'attachment; filename="formato-pago-'.$referencia.'.xlsx"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Qué va en cada celda del formato.
     *
     * - CFDI: las tres secciones, con el uso «gastos en general».
     * - Ticket, o un pago anterior a que la elección fuera obligatoria: de la
     *   sección de facturación sólo se llena el uso, «sin efectos fiscales».
     * - Pago compartido de una referencia especial: en el participante va el
     *   nombre o razón social de quien paga, no el de una de las personas.
     *
     * @param  array<string, mixed>  $pago  ConsultaPagos::pago()
     * @return array<string, string|float>  Celda (B10) => valor.
     */
    public function celdas(array $pago, string $atendido_por, string $evento): array
    {
        $fiscales = $pago['datos_fiscales'];
        $cfdi = $pago['uso_cfdi'] === true && $fiscales !== null;

        $participante = $pago['pago_grupal'] && $fiscales !== null
            ? $fiscales['razon_social']
            : implode(' ', array_filter([$pago['nombre'], $pago['primer_apellido'], $pago['segundo_apellido']]));

        $celdas = [
            'G11' => $cfdi ? 'Gastos en general' : 'Sin efectos fiscales',
            'B20' => $participante,
            'B24' => $evento,
            'H24' => (float) $pago['monto_pagado'],
            'G25' => (string) $pago['referencia_bancaria'],
            'B28' => in_array($pago['metodo_pago'], self::CON_TARJETA, true) ? (string) $pago['banco'] : '',
            'G29' => $atendido_por,
        ];

        if ($cfdi) {
            $celdas['B10'] = $fiscales['razon_social'];
            $celdas['B11'] = $fiscales['rfc'];
            $celdas['B13'] = $fiscales['codigo_postal'];
            $celdas['C15'] = $fiscales['regimen_fiscal'];
        }

        /* El formato se llena en mayúsculas, como lo llena la DEC a mano. Lo
           vacío no se escribe: la celda conserva lo que trae la plantilla. */
        $celdas = array_map(
            fn (string|float $valor): string|float => is_string($valor) ? mb_strtoupper(trim($valor), 'UTF-8') : $valor,
            $celdas
        );

        return array_filter($celdas, fn (string|float $valor): bool => $valor !== '');
    }

    /**
     * Copia la plantilla, escribe las celdas, marca la casilla y devuelve los
     * bytes del .xlsx. No toca la base ni el framework, así que se puede
     * probar suelta.
     *
     * @param  array<string, string|float>  $celdas  Celda (B10) => valor.
     * @param  string|null  $casilla  Celda sobre la que está la casilla (C26).
     */
    public function libro(string $plantilla, array $celdas, ?string $casilla): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'suif-formato-');

        if ($ruta === false || !@copy($plantilla, $ruta)) {
            throw new DomainException('No se pudo preparar el formato de pago. Inténtalo de nuevo.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($ruta) !== true) {
                throw new DomainException('La plantilla del formato de pago no se pudo abrir.');
            }

            $zip->addFromString(self::HOJA, $this->escribirCeldas($this->parte($zip, self::HOJA), $celdas));

            if ($casilla !== null) {
                $zip->addFromString(self::DIBUJO, $this->marcarCasilla($this->parte($zip, self::DIBUJO), $casilla));
            }

            if (!$zip->close()) {
                throw new DomainException('El formato de pago no pudo guardarse.');
            }

            $contenido = file_get_contents($ruta);

            if ($contenido === false) {
                throw new DomainException('El formato de pago no pudo leerse después de generarse.');
            }

            return $contenido;
        } finally {
            @unlink($ruta);
        }
    }

    private function parte(ZipArchive $zip, string $nombre): string
    {
        $xml = $zip->getFromName($nombre);

        if (!is_string($xml) || $xml === '') {
            throw new DomainException('La plantilla del formato de pago no trae '.$nombre.'.');
        }

        return $xml;
    }

    /**
     * Las celdas ya existen en la plantilla con su estilo: sólo cambia su
     * contenido. El texto va en línea (inlineStr) para no tocar la tabla de
     * cadenas compartidas, y el importe como número para que lo pinte el
     * formato de moneda que ya trae la celda.
     *
     * @param  array<string, string|float>  $celdas
     */
    private function escribirCeldas(string $xml, array $celdas): string
    {
        $documento = $this->documento($xml);
        $xpath = new DOMXPath($documento);
        $xpath->registerNamespace('m', self::NS_HOJA);

        foreach ($celdas as $referencia => $valor) {
            $celda = $xpath->query('/m:worksheet/m:sheetData/m:row/m:c[@r="'.$referencia.'"]')->item(0);

            if (!$celda instanceof DOMElement) {
                throw new DomainException('La plantilla del formato de pago no tiene la celda '.$referencia.'.');
            }

            while ($celda->firstChild !== null) {
                $celda->removeChild($celda->firstChild);
            }

            if (is_float($valor)) {
                $celda->removeAttribute('t');
                $celda->appendChild($documento->createElementNS(self::NS_HOJA, 'v', number_format($valor, 2, '.', '')));

                continue;
            }

            $texto = $documento->createElementNS(self::NS_HOJA, 't');
            $texto->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            /* createTextNode escapa & y <: una razón social puede traerlos. */
            $texto->appendChild($documento->createTextNode($valor));

            $en_linea = $documento->createElementNS(self::NS_HOJA, 'is');
            $en_linea->appendChild($texto);

            $celda->setAttribute('t', 'inlineStr');
            $celda->appendChild($en_linea);
        }

        return $documento->saveXML();
    }

    /**
     * La «X» va dentro de la casilla y no en la celda de abajo: la casilla es
     * un rectángulo con relleno blanco y taparía el texto de la celda.
     */
    private function marcarCasilla(string $xml, string $celda): string
    {
        /* Las casillas están en las columnas C y G: basta con una letra. */
        $columna = ord($celda[0]) - ord('A');
        $renglon = (int) substr($celda, 1) - 1;

        $documento = $this->documento($xml);
        $xpath = new DOMXPath($documento);
        $xpath->registerNamespace('xdr', self::NS_DIBUJO);

        $forma = $xpath->query(
            '//xdr:twoCellAnchor[xdr:from/xdr:col="'.$columna.'" and xdr:from/xdr:row="'.$renglon.'"]/xdr:sp'
        )->item(0);

        if (!$forma instanceof DOMElement) {
            throw new DomainException('La plantilla del formato de pago no tiene la casilla de '.$celda.'.');
        }

        /* Sin márgenes y centrada: la casilla mide nueve puntos. */
        $caja = $documento->createElementNS(self::NS_TRAZO, 'a:bodyPr');
        foreach (['wrap' => 'none', 'lIns' => '0', 'tIns' => '0', 'rIns' => '0', 'bIns' => '0', 'anchor' => 'ctr'] as $atributo => $valor) {
            $caja->setAttribute($atributo, $valor);
        }

        $alineacion = $documento->createElementNS(self::NS_TRAZO, 'a:pPr');
        $alineacion->setAttribute('algn', 'ctr');

        $letra = $documento->createElementNS(self::NS_TRAZO, 'a:rPr');
        $letra->setAttribute('lang', 'es-MX');
        $letra->setAttribute('sz', '800');
        $letra->setAttribute('b', '1');

        $tramo = $documento->createElementNS(self::NS_TRAZO, 'a:r');
        $tramo->appendChild($letra);
        $tramo->appendChild($documento->createElementNS(self::NS_TRAZO, 'a:t', 'X'));

        $parrafo = $documento->createElementNS(self::NS_TRAZO, 'a:p');
        $parrafo->appendChild($alineacion);
        $parrafo->appendChild($tramo);

        $cuerpo = $documento->createElementNS(self::NS_DIBUJO, 'xdr:txBody');
        $cuerpo->appendChild($caja);
        $cuerpo->appendChild($documento->createElementNS(self::NS_TRAZO, 'a:lstStyle'));
        $cuerpo->appendChild($parrafo);

        $forma->appendChild($cuerpo);

        return $documento->saveXML();
    }

    private function documento(string $xml): DOMDocument
    {
        $documento = new DOMDocument();

        if (!@$documento->loadXML($xml, LIBXML_NONET)) {
            throw new DomainException('La plantilla del formato de pago está dañada.');
        }

        return $documento;
    }
}
