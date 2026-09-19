<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;

/**
 * RegistroPlataformas
 *
 * Responsabilidad: armar el registro con el que las personas citadas a un
 * grupo se dan de alta en la plataforma donde presentan el examen. Su RFC
 * será la contraseña y su folio de solicitud, el usuario.
 *
 * El acomodo no es nuestro: es la plantilla de quien administra esa
 * plataforma y se replica tal cual —programa y período arriba, la leyenda
 * debajo y la tabla con sus encabezados textuales—. Por eso no pasa por
 * LibroExcel::descarga(), que pone el encabezado en la primera fila: arma su
 * propia hoja y sólo usa la entrega.
 *
 * No consulta la base: recibe el grupo y sus personas ya resueltos por
 * GestionSedes::listaDeGrupo(), igual que ListaAsistencia. Son las mismas
 * personas en los dos documentos.
 */
class RegistroPlataformas
{
    private const ENCABEZADOS = [
        'Nombre',
        'Apellidos',
        'Correo',
        'RFC (fungirá como contraseña)',
        'folio de registro (fungirá como Usuario)',
    ];

    /* La plantilla deja en blanco la fila 3 y arranca la tabla en la 4. */
    private const FILA_TABLA = 4;

    /* La plantilla deja Nombre y Correo al ancho por omisión, donde un correo
       no cabe; el folio lleva el encabezado más largo de todos. */
    private const ANCHOS = ['A' => 22, 'B' => 28, 'C' => 36, 'D' => 30, 'E' => 40];

    public function __construct(private LibroExcel $excel)
    {
    }

    /**
     * @param  array<string, mixed>  $grupo  El de GestionSedes::grupo().
     * @param  array<int, array<string, string>>  $personas  Las de GestionSedes::listaDeGrupo().
     * @param  array<string, mixed>|null  $convocatoria  La vigente, como la entrega GestionConvocatorias::bandeja().
     */
    public function descarga(array $grupo, array $personas, ?array $convocatoria): Response
    {
        if ($personas === []) {
            throw new DomainException('El grupo no tiene personas citadas.');
        }

        if ($convocatoria === null) {
            throw new DomainException(
                'No hay una convocatoria vigente: de ella salen el programa y el período del registro.'
            );
        }

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Hoja1');

        $programa = (string) $convocatoria['nombre'];

        $hoja->setCellValueExplicit('A1', $programa, DataType::TYPE_STRING);
        $hoja->setCellValue('E1', $this->periodo($convocatoria));
        $hoja->setCellValue('A2', 'Campos necesarios para alta en plataformas');
        $hoja->getStyle('A1:E2')->getFont()->setSize(18);

        /* El nombre de una convocatoria pasa de noventa caracteres y, a 18
           puntos, se cortaría al topar con el período de E1. Combinado hasta D
           y envuelto se lee entero; el período se alinea arriba con él. */
        $hoja->mergeCells('A1:D1');
        $hoja->getStyle('A1')->getAlignment()->setWrapText(true);
        $hoja->getStyle('A1:E1')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        // ponytail: alto estimado a ~60 caracteres por renglón, porque Excel no ajusta solo el alto de una celda combinada; medir con la fuente real si algún nombre sale cortado.
        $hoja->getRowDimension(1)->setRowHeight(23.25 * max(1, (int) ceil(mb_strlen($programa) / 60)));

        $hoja->fromArray(self::ENCABEZADOS, null, 'A'.self::FILA_TABLA);

        $fila = self::FILA_TABLA;

        foreach ($personas as $persona) {
            $fila++;

            /* Como texto explícito: un valor que empiece con «=» se guardaría
               como fórmula y Excel la evaluaría al abrir el archivo. */
            foreach (['A' => 'nombre', 'B' => 'apellidos', 'C' => 'correo', 'D' => 'rfc'] as $columna => $llave) {
                $hoja->setCellValueExplicit($columna.$fila, (string) $persona[$llave], DataType::TYPE_STRING);
            }

            $hoja->setCellValue('E'.$fila, (int) $persona['folio']);
        }

        $tabla = new Table('A'.self::FILA_TABLA.':E'.$fila, 'Participantes');
        $tabla->setStyle(
            (new TableStyle())
                ->setTheme(TableStyle::TABLE_STYLE_MEDIUM9)
                ->setShowRowStripes(true)
        );
        $hoja->addTable($tabla);

        foreach (self::ANCHOS as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        return $this->excel->entregar($libro, $this->nombreArchivo($grupo));
    }

    /**
     * Mismo esquema que la lista de asistencia —sede y fecha del grupo—: las
     * dos se piden por grupo, y quien las archiva las busca juntas.
     */
    public function nombreArchivo(array $grupo): string
    {
        $sede = Str::slug((string) $grupo['sede_nombre']) ?: 'sede';

        return 'registro-plataformas-'.$sede.'-'.$grupo['fecha_inicio'].'.xlsx';
    }

    /**
     * La vigencia de la convocatoria, escrita como en su bandeja.
     */
    private function periodo(array $convocatoria): string
    {
        return Carbon::parse($convocatoria['fecha_inicio'])->format('d/m/Y')
            .' al '.Carbon::parse($convocatoria['fecha_fin'])->format('d/m/Y');
    }
}
