<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\CatalogoReferencias;
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
                'titulo' => 'Referencias bancarias',
                'descripcion' => 'El catálogo completo con el estado de cada referencia. '
                    .'Acotar por convocatoria deja fuera las que aún no se asignan: '
                    .'todavía no pertenecen a ninguna.',
                'ruta' => 'admin.reportes.pagos',
                'permiso' => 'gestionar-pagos',
                'filtros' => ['convocatoria', 'estado'],
            ],
            [
                'clave' => 'cfdi',
                'titulo' => 'Solicitudes de CFDI',
                'descripcion' => 'Un renglón por factura a emitir: quién la pide y con qué datos fiscales. '
                    .'Una empresa que inscribió a varias personas paga una sola vez y se le factura una sola vez.',
                'ruta' => 'admin.reportes.cfdi',
                'permiso' => 'gestionar-pagos',
                'filtros' => ['convocatoria', 'mes'],
            ],
            [
                'clave' => 'registros',
                'titulo' => 'Registros totales al sistema',
                'descripcion' => 'Un renglón por solicitud resuelta, en orden de folio, '
                    .'con los datos de identificación, del trámite y su estado.',
                'ruta' => 'admin.reportes.registros',
                'permiso' => 'validar-registro',
                'filtros' => ['convocatoria'],
            ],
            [
                'clave' => 'grupos',
                'titulo' => 'Lista de asistencia por grupo',
                'descripcion' => 'Las personas citadas a una aplicación, con espacio para su firma.',
                'ruta' => 'admin.reportes.grupos',
                'permiso' => 'gestionar-sedes',
                'filtros' => ['grupo'],
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
            /* «Todas» al frente: el reporte sin filtro es el catálogo entero. */
            'estados' => ['' => 'Todas'] + CatalogoReferencias::ESTADOS_REPORTE,
        ]);
    }

    public function pagos(Request $request, CatalogoReferencias $catalogo, LibroExcel $excel)
    {
        $estado = $this->estadoFiltrado($request);
        $filas = $catalogo->reporte($this->convocatoriaFiltrada($request), $estado);

        return $this->entregar(
            $excel,
            $this->nombre('referencias-'.($estado ?: 'todas')),
            [
                'Referencia bancaria', 'Estado', 'Monto cobrado', 'Vigencia', 'Fecha de asignación',
                'CURP', 'Nombre completo', 'Entidad federativa', 'Convocatoria',
                'Monto declarado', 'Fecha de pago', 'Fecha de validación',
                'Sede', 'Fecha del grupo', 'Horario',
            ],
            array_map(fn (array $fila): array => [
                $fila['referencia'],
                $fila['estado'],
                $fila['monto_cobrado'],
                $fila['vigencia'],
                $fila['fecha_asignacion'],
                $fila['curp'],
                $fila['nombre_completo'],
                $fila['entidad_federativa'],
                $fila['convocatoria'],
                $fila['monto_declarado'],
                $fila['fecha_pago'],
                $fila['fecha_validacion'],
                $fila['sede'],
                $fila['fecha_grupo'],
                $fila['horario'],
            ], $filas),
            [1 => 20, 2 => 14, 3 => 15, 4 => 14, 5 => 18, 6 => 20, 7 => 34, 8 => 20, 9 => 30,
                10 => 15, 11 => 14, 12 => 20, 13 => 28, 14 => 15, 15 => 18],
            /* El nombre de la convocatoria no cabe en su ancho y taparía a las
               columnas de la derecha si se derramara. */
            [9]
        );
    }

    public function cfdi(Request $request, ConsultaPagos $consulta, LibroExcel $excel)
    {
        $filas = $consulta->solicitudesCfdi(
            $this->convocatoriaFiltrada($request),
            $this->mesFiltrado($request)
        );

        return $this->entregar(
            $excel,
            $this->nombre('solicitudes-cfdi'),
            [
                'Razón social', 'RFC', 'Tipo de persona', 'Régimen fiscal', 'Código postal',
                'Correo de facturación', 'Convocatoria', 'Referencia bancaria',
                'Monto pagado', 'Fecha de pago', 'Datos fiscales',
            ],
            array_map(fn (array $fila): array => [
                $fila['razon_social'],
                $fila['rfc_fiscal'],
                $fila['tipo_persona'],
                $fila['regimen_fiscal'],
                $fila['codigo_postal'],
                $fila['correo_facturacion'],
                $fila['convocatoria'],
                $fila['referencia_bancaria'],
                $fila['monto_declarado'],
                $fila['fecha_pago'],
                $fila['captura'],
            ], $filas),
            [1 => 34, 2 => 16, 3 => 16, 4 => 30, 5 => 14, 6 => 32, 7 => 30, 8 => 20,
                9 => 14, 10 => 14, 11 => 22],
            [7]
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
                'Fecha de registro', 'Convocatoria', 'Sede', 'Fecha del grupo', 'Horario', 'Estado',
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
                $fila['estado'],
            ], $filas),
            [1 => 18, 2 => 20, 3 => 34, 4 => 16, 5 => 20, 6 => 18, 7 => 30, 8 => 28, 9 => 15,
                10 => 18, 11 => 16],
            [7]
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
     * @param  array<int, int>  $ajustar
     */
    private function entregar(
        LibroExcel $excel,
        string $nombre,
        array $encabezados,
        array $filas,
        array $anchos,
        array $ajustar = []
    ) {
        try {
            return $excel->descarga($nombre, $encabezados, $filas, $anchos, $ajustar);
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
     * Estado con el que se acota el reporte de referencias. Un valor que no
     * esté en el catálogo se ignora y devuelve el catálogo entero, que es lo
     * mismo que no filtrar: la URL se puede escribir a mano.
     */
    private function estadoFiltrado(Request $request): string
    {
        $estado = (string) $request->query('estado', '');

        return array_key_exists($estado, CatalogoReferencias::ESTADOS_REPORTE) ? $estado : '';
    }

    /**
     * Mes calendario del pago, en 'YYYY-MM'. Es lo que devuelve
     * <input type="month">; cualquier otra cosa se descarta y trae todo.
     */
    private function mesFiltrado(Request $request): string
    {
        $mes = (string) $request->query('mes', '');

        return preg_match('/^\d{4}-\d{2}$/', $mes) === 1 ? $mes : '';
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
     * @param  array<int, array<string, mixed>>  $tarjetas
     */
    private function necesita(array $tarjetas, string $filtro): bool
    {
        foreach ($tarjetas as $tarjeta) {
            if (in_array($filtro, $tarjeta['filtros'], true)) {
                return true;
            }
        }

        return false;
    }
}
