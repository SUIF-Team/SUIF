<?php

namespace App\Servicios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ListaAsistencia
 *
 * Responsabilidad: preparar la lista de firmas de un grupo y decir qué
 * plantilla y qué nombre de archivo le corresponden.
 *
 * No consulta la base: recibe el grupo y sus personas ya resueltos por
 * GestionSedes::listaDeGrupo(). Mismo molde que ComprobanteSede, porque
 * resuelve el mismo problema: un PDF que se arma en memoria al descargarlo.
 *
 * Existe en papel y no sólo en Excel porque las firmas se recogen a mano el
 * día del examen, y la hoja impresa tiene que salir siempre igual, sin
 * depender de la configuración de página de quien la mande a imprimir.
 */
class ListaAsistencia
{
    /**
     * Plantilla Blade de la lista.
     */
    public function vista(): string
    {
        return 'pdf.reportes.lista-asistencia';
    }

    /**
     * Nombre con el que se descarga el archivo.
     *
     * Lleva sede y fecha porque en una convocatoria se imprimen muchas listas
     * y, si todas se llaman igual, quien las junta no distingue una de otra.
     *
     * La extensión es un parámetro para que el Excel y el PDF de la misma
     * lista se llamen igual: son el mismo documento en dos formatos, y quien
     * los archiva espera encontrarlos juntos.
     */
    public function nombreArchivo(array $grupo, string $extension = 'pdf'): string
    {
        /* Str::ascii antes de filtrar: si no, «Centro de aplicación Copilco»
           acaba como «centro-de-aplicaci-n-copilco», con un hueco donde iba
           la acentuada. */
        $sede = preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii((string) $grupo['sede_nombre']));
        $sede = trim(mb_strtolower((string) $sede), '-');

        return 'lista-asistencia-'.($sede ?: 'sede').'-'.$grupo['fecha_inicio'].'.'.$extension;
    }

    /**
     * Los huecos que llena la plantilla.
     *
     * @param  array<int, array<string, string>>  $personas
     */
    public function datos(array $grupo, array $personas): array
    {
        return [
            'sede' => $grupo['sede_nombre'],
            'direccion' => $grupo['sede_direccion'],
            'fecha' => $this->fechaLegible($grupo),
            'horario' => $grupo['hora_inicio'].'–'.$grupo['hora_fin'].' h',
            'personas' => $personas,
            'total' => count($personas),
            'emitido' => Carbon::now()->locale('es')->translatedFormat('d \d\e F \d\e Y, H:i'),
            'css' => file_get_contents(public_path('assets/css/pdf/lista-asistencia.css')),
        ];
    }

    /**
     * Un grupo puede abarcar más de un día; entonces la fecha es un rango.
     * Mismo criterio que ComprobanteSede::conFormato().
     */
    private function fechaLegible(array $grupo): string
    {
        $inicio = Carbon::parse($grupo['fecha_inicio'])->locale('es');
        $fin = Carbon::parse($grupo['fecha_fin'])->locale('es');

        $fecha = $inicio->translatedFormat('d \d\e F \d\e Y');

        if (!$inicio->isSameDay($fin)) {
            $fecha .= '–'.$fin->translatedFormat('d \d\e F \d\e Y');
        }

        return $fecha;
    }
}
