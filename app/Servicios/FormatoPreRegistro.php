<?php

namespace App\Servicios;

use Illuminate\Support\Carbon;

/**
 * FormatoPreRegistro
 *
 * Responsabilidad: preparar el contenido variable de un formato oficial del
 * pre-registro y decir qué plantilla y qué nombre de archivo le corresponden.
 *
 * No consulta la base ni guarda nada: recibe los datos que ya leyó el
 * controlador y devuelve lo que la plantilla necesita para imprimirse. Los
 * formatos se generan al vuelo en cada descarga, así que siempre reflejan los
 * datos vigentes de la persona.
 */
class FormatoPreRegistro
{
    private $slug;

    private $datos;

    public function __construct($slug, array $datos)
    {
        $this->slug = $slug;
        $this->datos = $datos;
    }

    /**
     * Plantilla Blade que corresponde al formato.
     */
    public function vista()
    {
        return 'pdf.preregistro.'.$this->slug;
    }

    /**
     * Nombre con el que se descarga el archivo.
     */
    public function nombreArchivo()
    {
        return $this->slug.'.pdf';
    }

    /**
     * Los huecos que llena el sistema: lugar y fecha de emisión, nombre
     * completo, RFC sin homoclave y homoclave. Lo demás —la firma autógrafa y
     * la casilla «(si / no)» del consentimiento— se llena a mano.
     *
     * El año no se calcula: va escrito en la plantilla como 2024, igual que
     * las fechas del Diario Oficial que cita cada formato.
     */
    public function datos()
    {
        $hoy = Carbon::now()->locale('es');
        $rfc = $this->rfc();

        return [
            'lugar' => $this->valor('entidad_federativa'),
            'dia' => $hoy->translatedFormat('j'),
            'mes' => $hoy->translatedFormat('F'),
            'nombre' => $this->nombreCompleto(),
            'rfcBase' => mb_substr($rfc, 0, 10, 'UTF-8'),
            'homoclave' => mb_substr($rfc, 10, 3, 'UTF-8'),
        ];
    }

    /**
     * Nombre y apellidos en el orden en que se firman los formatos.
     * Se descartan las partes vacías para no dejar espacios de más.
     */
    private function nombreCompleto()
    {
        $partes = array_filter([
            $this->valor('nombre'),
            $this->valor('primer_apellido'),
            $this->valor('segundo_apellido'),
        ], fn ($parte): bool => $parte !== '');

        return implode(' ', $partes);
    }

    /**
     * El RFC se guarda ya normalizado, pero se vuelve a limpiar aquí para que
     * el formato no dependa de por dónde llegaron los datos. Si viniera vacío
     * o incompleto, mb_substr devuelve cadena vacía y el formato sale con la
     * raya en blanco en lugar de reventar.
     */
    private function rfc()
    {
        return mb_strtoupper($this->valor('rfc'), 'UTF-8');
    }

    private function valor($clave)
    {
        return isset($this->datos[$clave]) ? trim((string) $this->datos[$clave]) : '';
    }
}
