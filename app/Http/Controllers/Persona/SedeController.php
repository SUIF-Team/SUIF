<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\ComprobanteSede;
use App\Servicios\GestionSedes;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SedeController extends Controller
{
    /**
     * A partir de aquí el cupo se muestra como «bajo». Viaja al cliente en vez
     * de repetirse en la plantilla y en el script.
     */
    private const UMBRAL_CUPO_BAJO = 15;

    public function index(Request $request, GestionSedes $gestion)
    {
        $idUsuario = (int) Auth::id();
        $seleccionada = $gestion->sedeSeleccionadaPorUsuario($idUsuario);

        if ($seleccionada) {
            return view('persona.sede', [
                'confirmada' => true,
                'sede' => ComprobanteSede::conFormato($seleccionada),
                'mapa' => ComprobanteSede::urlMapa((string) $seleccionada['direccion']),
            ]);
        }

        $buscar = trim((string) $request->query('buscar'));

        return view('persona.sede', [
            'confirmada' => false,
            'buscarActual' => $buscar,
            'vista' => [
                'sedes' => $gestion->catalogoParticipante($buscar),
                'buscar' => $buscar,
                'umbralCupoBajo' => self::UMBRAL_CUPO_BAJO,
            ],
        ]);
    }

    public function seleccionar(Request $request, GestionSedes $gestion)
    {
        $datos = $request->validate([
            'evaluacion_id' => ['required', 'integer', 'min:1'],
        ], [
            'evaluacion_id.required' => 'Selecciona el horario en el que presentarás tu evaluación.',
        ]);

        try {
            $gestion->seleccionarParaUsuario((int) Auth::id(), (int) $datos['evaluacion_id']);
        } catch (DomainException $exception) {
            return redirect()
                ->route('persona.sede.index')
                ->withErrors(['sede' => $exception->getMessage()]);
        }

        return redirect()
            ->route('persona.sede.index')
            ->with('success', 'Tu sede y horario quedaron confirmados.');
    }

    /**
     * Sondeo del catálogo mientras la persona elige.
     *
     * Devuelve el catálogo completo, no sólo los cupos: una aplicación puede
     * vencer o darse de baja con la pantalla abierta, y entonces el renglón no
     * cambia de número, desaparece. Se respeta el filtro de búsqueda vigente
     * para no reinyectar sedes que la persona ya descartó.
     */
    public function disponibilidad(Request $request, GestionSedes $gestion): JsonResponse
    {
        return response()->json([
            'sedes' => $gestion->catalogoParticipante(trim((string) $request->query('buscar'))),
        ]);
    }

    /**
     * Comprobante de la sede y el horario confirmados.
     *
     * El PDF no se guarda en ningún lado: se arma en memoria en cada clic con
     * la asignación vigente. La persona se resuelve desde la sesión, así que
     * nadie puede pedir el comprobante de alguien más.
     */
    public function comprobante(GestionSedes $gestion, ComprobanteSede $comprobante)
    {
        $seleccionada = $gestion->sedeSeleccionadaPorUsuario((int) Auth::id());

        if (!$seleccionada) {
            return redirect()
                ->route('persona.sede.index')
                ->withErrors(['sede' => 'Confirma tu sede y horario antes de generar el comprobante.']);
        }

        $pdf = Pdf::loadView($comprobante->vista(), $comprobante->datos($seleccionada))
            ->setPaper('letter');

        /* La respuesta se arma a mano porque download() del paquete no admite
           cabeceras extra y este documento lleva datos personales. */
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$comprobante->nombreArchivo().'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
