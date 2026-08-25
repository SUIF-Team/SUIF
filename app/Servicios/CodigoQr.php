<?php

namespace App\Servicios;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * CodigoQr
 *
 * Responsabilidad: convertir un texto en un código QR listo para incrustarse
 * en un documento.
 *
 * Se entrega como data URI y no como archivo porque los PDF de este sistema se
 * arman en memoria en cada descarga y no se guardan en ningún lado. Dompdf
 * resuelve los data URI sin necesidad de `enable_remote`, que está apagado.
 *
 * El formato es SVG a propósito: es vectorial —el QR se escanea igual de bien
 * impreso que en pantalla— y no requiere GD para generarse.
 */
class CodigoQr
{
    /**
     * Corrección de errores media: tolera que el papel se ensucie o se doble
     * sin encarecer el tamaño del código como lo haría el nivel alto.
     */
    private const CORRECCION = 'M';

    /**
     * Margen en módulos. El estándar pide cuatro; uno basta cuando el código
     * ya vive dentro de un recuadro con aire propio, como en el comprobante.
     */
    private const MARGEN = 1;

    /**
     * Código QR del texto indicado, como data URI listo para el atributo src.
     */
    public function svgDataUri(string $texto, int $tamano = 220): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($texto, $tamano));
    }

    /**
     * El SVG en crudo. Se expone aparte por si algún día hay que servirlo
     * directamente en una pantalla.
     */
    public function svg(string $texto, int $tamano = 220): string
    {
        $escritor = new Writer(new ImageRenderer(
            new RendererStyle($tamano, self::MARGEN),
            new SvgImageBackEnd()
        ));

        return $escritor->writeString(
            $texto,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            ErrorCorrectionLevel::valueOf(self::CORRECCION)
        );
    }
}
