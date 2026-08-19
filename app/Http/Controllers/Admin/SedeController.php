<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sede;
use App\Servicios\GestionSedes;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Admin\SedeController
 *
 * Migrado desde: app/controllers/admin/SedeController.php
 * Responsabilidad: alta, edición y gestión de sedes de aplicación desde el panel administrativo.
 */
class SedeController extends Controller
{
    public function index(Request $request, GestionSedes $gestion)
    {
        $filtros = $request->only(['buscar', 'ubicacion', 'estado']);
        $datos = $gestion->bandeja($filtros);

        return view('admin.sedes', [
            'sedes' => $datos['sedes'],
            'resumen' => $datos['resumen'],
            'filtros' => $filtros,
        ]);
    }

    public function create()
    {
        return view('admin.sede-formulario', [
            'sede' => null,
            'grupo' => null,
            'modoEdicion' => false,
        ]);
    }

    public function store(Request $request, GestionSedes $gestion)
    {
        $gestion->crear($this->validar($request));

        return redirect()
            ->route('admin.sedes.index')
            ->with('success', 'La sede se creó correctamente.');
    }

    public function edit(int $id)
    {
        $sede = Sede::query()->with('grupo')->findOrFail($id);

        return view('admin.sede-formulario', [
            'sede' => $sede,
            'grupo' => $sede->grupo,
            'modoEdicion' => true,
        ]);
    }

    public function update(Request $request, int $id, GestionSedes $gestion)
    {
        try {
            $gestion->actualizar($id, $this->validar($request));
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.sedes.index')
            ->with('success', 'La sede se actualizó correctamente.');
    }

    public function destroy(int $id, GestionSedes $gestion)
    {
        try {
            $gestion->eliminar($id);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.sedes.index')
            ->with('success', 'La sede se eliminó correctamente.');
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:1000'],
            'cupo' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'fecha_fin' => ['required', 'date_format:Y-m-d'],
            'hora_fin' => ['required', 'date_format:H:i'],
        ], [
            'nombre.required' => 'Escribe el nombre de la sede.',
            'nombre.max' => 'El nombre de la sede no puede exceder 150 caracteres.',
            'direccion.required' => 'Escribe la dirección completa de la sede.',
            'direccion.max' => 'La dirección no puede exceder 1000 caracteres.',
            'cupo.required' => 'Indica el aforo máximo de la sede.',
            'cupo.integer' => 'El aforo máximo debe ser un número entero.',
            'cupo.min' => 'El aforo máximo debe ser al menos 1.',
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

        $datos['nombre'] = trim($datos['nombre']);
        $datos['direccion'] = trim($datos['direccion']);

        return $datos;
    }
}
