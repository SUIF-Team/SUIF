<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * LibroExcel
 *
 * Responsabilidad: convertir un encabezado y unas filas en el archivo .xlsx
 * que el administrador descarga.
 *
 * Es una sola clase para los cuatro reportes porque lo único que cambia entre
 * ellos son las columnas. La consulta de cada reporte vive en el servicio de
 * su módulo; aquí no se sabe qué significan los datos, sólo cómo escribirlos.
 *
 * A diferencia de los PDF del sistema, no hay una clase por documento con
 * vista() y datos(): un PDF necesita plantilla Blade y una hoja de cálculo no.
 */
class LibroExcel
{
    /**
     * Tope de renglones por archivo.
     *
     * PhpSpreadsheet arma el libro entero en memoria —cada celda es un objeto—
     * así que un reporte desbocado se lleva el memory_limit por delante y
     * devuelve una página en blanco. Vale más un aviso en español que un 500
     * sin explicación.
     */
    private const MAX_FILAS = 50000;

    private const TIPO_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, string|int|float|null>>  $filas
     * @param  array<int, float|int>  $anchos  Número de columna (base 1) => ancho.
     */
    public function descarga(
        string $nombre_archivo,
        array $encabezados,
        array $filas,
        array $anchos = []
    ): Response {
        if (count($filas) > self::MAX_FILAS) {
            throw new DomainException(
                'El reporte supera los '.number_format(self::MAX_FILAS).' renglones. '
                .'Acota el filtro por convocatoria y vuelve a intentarlo.'
            );
        }

        $libro = new Spreadsheet();

        try {
            $contenido = $this->escribir($libro, $encabezados, $filas, $anchos);
        } finally {
            /* Sin esto las hojas quedan enlazadas al libro por referencias
               circulares y el recolector no las suelta: en un worker que
               atiende varias descargas seguidas, la memoria se acumula. */
            $libro->disconnectWorksheets();
            unset($libro);
        }

        return response($contenido, 200, [
            'Content-Type' => self::TIPO_XLSX,
            'Content-Disposition' => 'attachment; filename="'.$this->nombreSeguro($nombre_archivo).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, string|int|float|null>>  $filas
     * @param  array<int, float|int>  $anchos
     */
    private function escribir(
        Spreadsheet $libro,
        array $encabezados,
        array $filas,
        array $anchos
    ): string {
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Reporte');

        $hoja->fromArray($encabezados, null, 'A1');

        if ($filas !== []) {
            $hoja->fromArray(array_values($filas), null, 'A2');
        }

        $ultima_columna = $hoja->getHighestColumn();

        $hoja->getStyle('A1:'.$ultima_columna.'1')->getFont()->setBold(true);

        /* Los anchos se declaran a mano en cada reporte y no con setAutoSize:
           el ajuste automático mide cada celda con las métricas de la fuente a
           través de GD y es, con diferencia, lo más caro de generar el libro. */
        foreach ($anchos as $columna => $ancho) {
            $hoja->getColumnDimensionByColumn($columna)->setWidth($ancho);
        }

        /* Con el encabezado congelado la tabla se lee sin perder de vista qué
           es cada columna, que es justo lo que se hace con un reporte largo. */
        $hoja->freezePane('A2');

        return $this->volcar($libro);
    }

    /**
     * Escribe el libro y devuelve sus bytes.
     *
     * Pasa por un archivo temporal en lugar de por php://output porque una
     * respuesta en streaming manda el 200 y los encabezados antes de escribir:
     * si el escritor falla a media hoja, la persona recibe un .xlsx corrupto
     * con estatus de éxito y sin ningún aviso. Bufferizando, ese fallo es un
     * error normal que la aplicación todavía puede reportar.
     *
     * No cuesta memoria adicional apreciable: el libro completo ya estaba en
     * RAM antes de empezar a escribir.
     */
    private function volcar(Spreadsheet $libro): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'suif-reporte-');

        if ($ruta === false) {
            throw new DomainException('No se pudo preparar el archivo del reporte. Inténtalo de nuevo.');
        }

        try {
            $escritor = new Xlsx($libro);
            /* No se escriben fórmulas; recalcularlas sólo cuesta tiempo. */
            $escritor->setPreCalculateFormulas(false);
            $escritor->save($ruta);

            $contenido = file_get_contents($ruta);

            if ($contenido === false) {
                throw new DomainException('El archivo del reporte no pudo leerse después de generarse.');
            }

            return $contenido;
        } catch (Throwable $error) {
            if ($error instanceof DomainException) {
                throw $error;
            }

            throw new DomainException('El reporte no pudo generarse: '.$error->getMessage(), 0, $error);
        } finally {
            @unlink($ruta);
        }
    }

    /**
     * El nombre viaja dentro de Content-Disposition, así que un salto de línea
     * o una comilla en él parten el encabezado en dos. Mismo criterio que
     * Admin\DocumentoController::nombreSeguro().
     *
     * Se corta en el primer salto en vez de sólo quitarlo: si alguien lograra
     * colar una cabecera, pegar sus restos al nombre daría un archivo con
     * basura en el título en lugar de un nombre limpio.
     *
     * Str::ascii translitera antes de filtrar, de modo que «aplicación» quede
     * como «aplicacion» y no como «aplicaci n».
     */
    private function nombreSeguro(string $nombre): string
    {
        $limpio = preg_replace(
            '/[^A-Za-z0-9 ._-]/',
            '',
            Str::ascii(basename(preg_split('/[\r\n]/', $nombre)[0] ?? ''))
        );
        $limpio = trim((string) $limpio);

        if ($limpio === '' || $limpio === '.xlsx') {
            return 'reporte.xlsx';
        }

        return str_ends_with(mb_strtolower($limpio), '.xlsx') ? $limpio : $limpio.'.xlsx';
    }
}
