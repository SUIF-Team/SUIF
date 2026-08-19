<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\GestionSedes;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SedeController extends Controller
{
    public function index(Request $request, GestionSedes $gestion)
    {
        $idUsuario = (int) Auth::id();
        $seleccionada = $gestion->sedeSeleccionadaPorUsuario($idUsuario);

        if ($seleccionada) {
            $fechaInicio = Carbon::parse($seleccionada['fecha_inicio'])->locale('es');
            $fechaFin = Carbon::parse($seleccionada['fecha_fin'])->locale('es');
            $seleccionada['fecha'] = $fechaInicio
                ->locale('es')
                ->translatedFormat('d \d\e F \d\e Y');
            if (!$fechaInicio->isSameDay($fechaFin)) {
                $seleccionada['fecha'] .= '–'.$fechaFin->translatedFormat('d \d\e F \d\e Y');
            }
            $seleccionada['horario'] = $seleccionada['hora_inicio'].'–'.$seleccionada['hora_fin'].' h';

            return view('persona.sede', [
                'confirmada' => true,
                'sede' => $seleccionada,
            ]);
        }

        $buscar = trim((string) $request->query('buscar'));

        return view('persona.sede', [
            'confirmada' => false,
            'sedes' => $gestion->catalogoParticipante($buscar),
            'buscarActual' => $buscar,
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

    public function disponibilidad(GestionSedes $gestion): JsonResponse
    {
        return response()->json([
            'sedes' => $gestion->disponibilidadParticipante(),
        ]);
    }
}
