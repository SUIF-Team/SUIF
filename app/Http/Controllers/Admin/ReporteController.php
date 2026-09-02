<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\GestionConvocatorias;
use App\Servicios\GestionSedes;
use App\Servicios\LibroExcel;
use App\Servicios\ListaAsistencia;
use App\Support\Admin\ConsultaPagos;
use App\Support\Admin\ConsultaPreRegistros;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Admin\ReporteController
 *
 * Responsabilidad: entregar en Excel la información que hoy sólo se puede leer
 * en pantalla —pagos, registros, listas de grupo y datos de facturación— para
 * cortar caja, pasar lista y facturar fuera del sistema.
 *
 * Cada reporte lleva los datos de un módulo distinto, así que cada uno exige
 * el permiso de su módulo y no un permiso único de «reportes»: la DEC no tiene
 * por qué descargar el padrón de la UIF ni al revés. El permiso de la pantalla
 * sólo abre la puerta; lo que se ve dentro lo vuelve a decidir cada permiso.
 *
 * No consulta la base: cada reporte se resuelve en el servicio de su módulo,
 * que ya tiene armadas esas consultas. Aquí sólo se elige el reporte, se
 * ordenan las columnas y se entrega el archivo.
 */
class ReporteController extends Controller
{
    public function index(GestionConvocatorias $convocatorias, GestionSedes $sedes)
    {
        /* Mismo criterio que el tablero: lo que no se puede abrir tampoco se
           pinta. Enseñar un botón que responde 403 es peor que no enseñarlo. */
        $tarjetas = array_values(array_filter([
            [
                'clave' => 'pagos',
                'titulo' => 'Referencias bancarias pagadas',
                'descripcion' => 'Quiénes ya pagaron su referencia, con el monto cobrado y el declarado.',
                'ruta' => 'admin.reportes.pagos',
                'permiso' => 'gestionar-pagos',
                'filtro' => 'convocatoria',
            ],
            [
                'clave' => 'cfdi',
                'titulo' => 'Solicitudes de CFDI',
                'descripcion' => 'Quiénes pidieron factura y con qué datos fiscales se les emite.',
                'ruta' => 'admin.reportes.cfdi',
                'permiso' => 'gestionar-pagos',
                'filtro' => 'convocatoria',
            ],
            [
                'clave' => 'registros',
                'titulo' => 'Registros totales al sistema',
                'descripcion' => 'Un renglón por solicitud registrada, con los datos de identificación y del trámite.',
                'ruta' => 'admin.reportes.registros',
                'permiso' => 'validar-registro',
                'filtro' => 'convocatoria',
            ],
            [
                'clave' => 'grupos',
                'titulo' => 'Lista de asistencia por grupo',
                'descripcion' => 'Las personas citadas a una aplicación, con espacio para su firma.',
                'ruta' => 'admin.reportes.grupos',
                'permiso' => 'gestionar-sedes',
                'filtro' => 'grupo',
            ],
        ], fn (array $tarjeta): bool => Gate::allows($tarjeta['permiso'])));

        return view('admin.reportes', [
            'tarjetas' => $tarjetas,
            /* Los catálogos se consultan sólo si alguna tarjeta los usa: a
               quien no puede descargar nada de eso no se le cobra la consulta. */
            'convocatorias' => $this->necesita($tarjetas, 'convocatoria')
                ? $convocatorias->bandeja()['convocatorias']
                : collect(),
            'grupos' => $this->necesita($tarjetas, 'grupo')
                ? $sedes->bandejaGrupos()['grupos']
                : collect(),
        ]);
    }

    public function pagos(Request $request, ConsultaPagos $consulta, LibroExcel $excel)
    {
        $filas = $consulta->pagadas($this->convocatoriaFiltrada($request));

        return $this->entregar(
            $excel,
            $this->nombre('referencias-pagadas'),
            [
                'CURP', 'Nombre completo', 'Entidad federativa', 'Convocatoria',
                'Referencia bancaria', 'Monto cobrado', 'Monto declarado',
                'Fecha de pago', 'Fecha de validación', 'Sede', 'Fecha del grupo', 'Horario',
            ],
            array_map(fn (array $fila): array => [
                $fila['curp'],
                $fila['nombre_completo'],
                $fila['entidad_federativa'],
                $fila['convocatoria'],
                $fila['referencia_bancaria'],
                $fila['monto_cobrado'],
                $fila['monto_declarado'],
                $fila['fecha_pago'],
                $fila['fecha_validacion'],
                $fila['sede'],
                $fila['fecha_grupo'],
                $fila['horario'],
            ], $filas),
            [1 => 20, 2 => 34, 3 => 20, 4 => 30, 5 => 20, 6 => 15, 7 => 15, 8 => 14, 9 => 20, 10 => 28, 11 => 15, 12 => 18]
        );
    }

    public function cfdi(Request $request, ConsultaPagos $consulta, LibroExcel $excel)
    {
        $filas = $consulta->solicitudesCfdi($this->convocatoriaFiltrada($request));

        return $this->entregar(
            $excel,
            $this->nombre('solicitudes-cfdi'),
            [
                'CURP', 'Nombre completo', 'Convocatoria', 'Referencia bancaria',
                'Monto pagado', 'Fecha de pago', 'Razón social', 'RFC', 'Tipo de persona',
                'Régimen fiscal', 'Código postal', 'Correo de facturación', 'Datos fiscales',
            ],
            array_map(fn (array $fila): array => [
                $fila['curp'],
                $fila['nombre_completo'],
                $fila['convocatoria'],
                $fila['referencia_bancaria'],
                $fila['monto_declarado'],
                $fila['fecha_pago'],
                $fila['razon_social'],
                $fila['rfc_fiscal'],
                $fila['tipo_persona'],
                $fila['regimen_fiscal'],
                $fila['codigo_postal'],
                $fila['correo_facturacion'],
                $fila['captura'],
            ], $filas),
            [1 => 20, 2 => 34, 3 => 30, 4 => 20, 5 => 14, 6 => 14, 7 => 34, 8 => 16, 9 => 16, 10 => 30, 11 => 14, 12 => 32, 13 => 22]
        );
    }

    public function registros(Request $request, ConsultaPreRegistros $consulta, LibroExcel $excel)
    {
        $filas = $consulta->todasLasSolicitudes($this->convocatoriaFiltrada($request));

        return $this->entregar(
            $excel,
            $this->nombre('registros-totales'),
            [
                'Folio de solicitud', 'CURP', 'Nombre completo', 'RFC', 'Entidad federativa',
                'Fecha de registro', 'Convocatoria', 'Sede', 'Fecha del grupo', 'Horario',
            ],
            array_map(fn (array $fila): array => [
                $fila['folio'],
                $fila['curp'],
                $fila['nombre_completo'],
                $fila['rfc'],
                $fila['entidad_federativa'],
                $fila['fecha_registro'],
                $fila['convocatoria'],
                $fila['sede'],
                $fila['fecha_grupo'],
                $fila['horario'],
            ], $filas),
            [1 => 18, 2 => 20, 3 => 34, 4 => 16, 5 => 20, 6 => 18, 7 => 30, 8 => 28, 9 => 15, 10 => 18]
        );
    }

    public function grupo(Request $request, GestionSedes $sedes, LibroExcel $excel, ListaAsistencia $formato)
    {
        $lista = $this->lista($request, $sedes);

        return $this->entregar(
            $excel,
            /* Mismo nombre que el PDF: son el mismo documento en dos formatos.
               Aquí el archivo se identifica por el grupo y no por la fecha de
               emisión, que es lo que distingue una lista de otra. */
            $formato->nombreArchivo($lista['grupo'], 'xlsx'),
            ['N.º', 'Nombre completo', 'CURP', 'Firma'],
            array_map(fn (array $persona): array => [
                $persona['numero'],
                $persona['nombre_completo'],
                $persona['curp'],
                /* La columna de firma va vacía a propósito: se llena a mano
                   sobre el papel. El ancho es lo que la hace utilizable. */
                '',
            ], $lista['personas']),
            [1 => 6, 2 => 38, 3 => 20, 4 => 40]
        );
    }

    /**
     * La misma lista en PDF: es la que se lleva impresa a la sede.
     *
     * La respuesta se arma a mano porque download() del paquete no admite
     * cabeceras extra y este documento lleva datos personales.
     */
    public function listaFirmas(Request $request, GestionSedes $sedes, ListaAsistencia $formato)
    {
        $lista = $this->lista($request, $sedes);

        $pdf = Pdf::loadView(
            $formato->vista(),
            $formato->datos($lista['grupo'], $lista['personas'])
        )->setPaper('letter');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$formato->nombreArchivo($lista['grupo']).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Entrega el archivo y, si no se pudo generar, devuelve a la pantalla con
     * el motivo.
     *
     * Un reporte demasiado grande o un fallo del escritor no deben acabar en
     * una página en blanco: quien lo pidió tiene que leer qué pasó y poder
     * acotar el filtro. Envolver aquí evita repetir el try en los cuatro.
     *
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, string|int|float|null>>  $filas
     * @param  array<int, float|int>  $anchos
     */
    private function entregar(
        LibroExcel $excel,
        string $nombre,
        array $encabezados,
        array $filas,
        array $anchos
    ) {
        try {
            return $excel->descarga($nombre, $encabezados, $filas, $anchos);
        } catch (DomainException $error) {
            return redirect()
                ->route('admin.reportes.index')
                ->with('error', $error->getMessage());
        }
    }

    /**
     * Un grupo inexistente es un 404, no una pantalla de error: la lista se
     * pide por URL y el identificador puede venir de un enlace viejo o de un
     * grupo que se dio de baja después de que alguien guardó el enlace.
     */
    private function lista(Request $request, GestionSedes $sedes): array
    {
        $grupo = (int) $request->query('grupo', 0);

        if ($grupo <= 0) {
            abort(404, 'Selecciona el grupo del que quieres la lista.');
        }

        try {
            return $sedes->listaDeGrupo($grupo);
        } catch (DomainException $error) {
            abort(404, $error->getMessage());
        }
    }

    /**
     * El filtro es opcional: sin convocatoria elegida el reporte trae todo el
     * histórico. Se castea a entero para que un valor inventado en la URL no
     * llegue a la consulta.
     */
    private function convocatoriaFiltrada(Request $request): ?int
    {
        $convocatoria = (int) $request->query('convocatoria', 0);

        return $convocatoria > 0 ? $convocatoria : null;
    }

    /**
     * Nombre del archivo con la fecha de emisión, para que dos descargas del
     * mismo reporte no se pisen en la carpeta de descargas.
     */
    private function nombre(string $reporte): string
    {
        return $reporte.'-'.Carbon::now()->format('Y-m-d').'.xlsx';
    }

    /**
     * @param  array<int, array<string, string>>  $tarjetas
     */
    private function necesita(array $tarjetas, string $filtro): bool
    {
        foreach ($tarjetas as $tarjeta) {
            if ($tarjeta['filtro'] === $filtro) {
                return true;
            }
        }

        return false;
    }
}
