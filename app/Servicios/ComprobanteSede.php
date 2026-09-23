<?php

namespace App\Servicios;

use Illuminate\Support\Carbon;

/**
 * ComprobanteSede
 *
 * Responsabilidad: preparar el contenido del comprobante de sede y horario y
 * decir qué plantilla y qué nombre de archivo le corresponden.
 *
 * No consulta la base: recibe la sede ya resuelta por GestionSedes y devuelve
 * lo que la plantilla necesita para imprimirse. El PDF se arma en memoria en
 * cada descarga, así que siempre refleja la asignación vigente.
 *
 * El mapa de la pantalla es un iframe de Google Maps y eso no viaja a un PDF,
 * así que en su lugar va un código QR con el mismo enlace: quien recibe el
 * papel abre la ubicación desde el teléfono.
 */
class ComprobanteSede
{
    public function __construct(private CodigoQr $qr)
    {
    }

    /**
     * Plantilla Blade del comprobante.
     */
    public function vista(): string
    {
        return 'pdf.sede.comprobante';
    }

    /**
     * Nombre con el que se descarga el archivo.
     */
    public function nombreArchivo(): string
    {
        return 'comprobante-sede.pdf';
    }

    /**
     * Fecha y horario legibles de la aplicación.
     *
     * Vive aquí y no en el controlador porque lo necesitan las dos salidas: la
     * pantalla de sede confirmada y este comprobante. Un grupo puede abarcar
     * más de un día, y entonces la fecha se imprime como rango.
     */
    public static function conFormato(array $sede): array
    {
        $inicio = Carbon::parse($sede['fecha_inicio'])->locale('es');
        $fin = Carbon::parse($sede['fecha_fin'])->locale('es');

        $sede['fecha'] = $inicio->translatedFormat('d \d\e F \d\e Y');

        if (!$inicio->isSameDay($fin)) {
            $sede['fecha'] .= '–'.$fin->translatedFormat('d \d\e F \d\e Y');
        }

        $sede['horario'] = $sede['hora_inicio'].'–'.$sede['hora_fin'].' h';

        return $sede;
    }

    /**
     * Enlace a la ubicación de la sede.
     *
     * Se arma igual que el de la pantalla —a partir del texto de la dirección,
     * porque SEDE no guarda coordenadas— para que el QR y el botón «Abrir en
     * Google Maps» lleven exactamente al mismo lugar.
     */
    public static function urlMapa(string $direccion): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($direccion);
    }

    /**
     * Los huecos que llena la plantilla.
     */
    public function datos(array $sede): array
    {
        $sede = self::conFormato($sede);
        $mapa = self::urlMapa((string) $sede['direccion']);

        return [
            'folio' => $sede['folio'],
            'persona' => $sede['persona'],
            'curp' => $sede['curp'],
            'sede' => $sede['nombre'],
            'direccion' => $sede['direccion'],
            'fecha' => $sede['fecha'],
            'horario' => $sede['horario'],
            'qr' => $this->qr->svgDataUri($mapa, 220),
            'recomendaciones' => config('suif.comprobante_sede.recomendaciones', []),
            'emitido' => Carbon::now()->locale('es')->translatedFormat('d \d\e F \d\e Y, H:i'),
        ];
    }
}
