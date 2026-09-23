<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\GestionSedes;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Admin\GrupoController
 *
 * Responsabilidad: alta, edición y baja de los grupos de aplicación. Cada
 * grupo es una aplicación del examen en una sede, con su propio horario, y es
 * lo que saca a la sede del estado «Por programar».
 */
class GrupoController extends Controller
{
    public function index(Request $request, GestionSedes $gestion)
    {
        $filtros = $request->only(['sede', 'estado']);
        $datos = $gestion->bandejaGrupos($filtros);

        return view('admin.grupos', [
            'grupos' => $datos['grupos'],
            'resumen' => $datos['resumen'],
            'sedes' => $gestion->sedesParaGrupo(),
            'filtros' => $filtros,
        ]);
    }

    public function create(Request $request, GestionSedes $gestion)
    {
        return view('admin.grupo-formulario', [
            'grupo' => null,
            'sedes' => $gestion->sedesParaGrupo(),
            'sedePreseleccionada' => $request->query('sede'),
            'modoEdicion' => false,
        ]);
    }

    public function store(Request $request, GestionSedes $gestion)
    {
        try {
            $gestion->crearGrupo($this->validar($request, $gestion));
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'El grupo se creó correctamente.',
            route('admin.grupos.index')
        );
    }

    public function edit(int $id, GestionSedes $gestion)
    {
        try {
            $grupo = $gestion->grupo($id);
        } catch (DomainException $exception) {
            /* Abrir el formulario es una navegación, no una acción: si el
               registro ya no existe se vuelve al listado como siempre. */
            return redirect()
                ->route('admin.grupos.index')
                ->with('error', $exception->getMessage());
        }

        return view('admin.grupo-formulario', [
            'grupo' => $grupo,
            'sedes' => $gestion->sedesParaGrupo(),
            'sedePreseleccionada' => null,
            'modoEdicion' => true,
        ]);
    }

    public function update(Request $request, int $id, GestionSedes $gestion)
    {
        try {
            $gestion->actualizarGrupo($id, $this->validar($request, $gestion, $id));
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'El grupo se actualizó correctamente.',
            route('admin.grupos.index')
        );
    }

    public function destroy(Request $request, int $id, GestionSedes $gestion)
    {
        try {
            $gestion->eliminarGrupoPorId($id);
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'El grupo se eliminó correctamente.',
            route('admin.grupos.index')
        );
    }

    private function validar(Request $request, GestionSedes $gestion, ?int $id = null): array
    {
        $datos = $request->validate([
            'sede_id' => ['required', 'integer', 'min:1', 'exists:sede,sede_id_sede'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'fecha_fin' => ['required', 'date_format:Y-m-d'],
            'hora_fin' => ['required', 'date_format:H:i'],
        ], [
            'sede_id.required' => 'Elige la sede en la que se aplicará el examen.',
            'sede_id.exists' => 'La sede seleccionada ya no está disponible.',
            'fecha_inicio.required' => 'Indica la fecha de inicio.',
            'hora_inicio.required' => 'Indica la hora de inicio.',
            'fecha_fin.required' => 'Indica la fecha de fin.',
            'hora_fin.required' => 'Indica la hora de fin.',
        ]);

        $inicio = Carbon::createFromFormat(
            'Y-m-d H:i',
            $datos['fecha_inicio'].' '.$datos['hora_inicio'],
            config('app.timezone')
        );
        $fin = Carbon::createFromFormat(
            'Y-m-d H:i',
            $datos['fecha_fin'].' '.$datos['hora_fin'],
            config('app.timezone')
        );

        if ($fin->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha y hora de fin deben ser posteriores al inicio.',
            ]);
        }

        if ($gestion->seEmpalma((int) $datos['sede_id'], $inicio, $fin, $id)) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'Este grupo se empalma con otro de la misma sede.',
            ]);
        }

        return $datos;
    }
}
