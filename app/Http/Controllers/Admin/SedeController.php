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
            'horarios' => [],
            'modoEdicion' => false,
        ]);
    }

    public function store(Request $request, GestionSedes $gestion)
    {
        try {
            $gestion->crear($this->validar($request));
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.sedes.index')
            ->with('success', 'La sede se creó correctamente.');
    }

    public function edit(int $id)
    {
        $sede = Sede::query()->with('grupos')->findOrFail($id);

        return view('admin.sede-formulario', [
            'sede' => $sede,
            'horarios' => $sede->grupos
                ->map(fn ($grupo): array => [
                    'grupo_id' => $grupo->grup_id_grupo,
                    'hora_inicio' => substr((string) $grupo->grup_hora_inicio, 0, 5),
                    'fecha_inicio' => $grupo->grup_fecha_inicio?->format('Y-m-d') ?? '',
                    'hora_fin' => substr((string) $grupo->grup_hora_fin, 0, 5),
                    'fecha_fin' => $grupo->grup_fecha_fin?->format('Y-m-d') ?? '',
                ])
                ->all(),
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
            'horarios' => ['required', 'array', 'min:1'],
            'horarios.*.grupo_id' => ['nullable', 'integer', 'min:1'],
            'horarios.*.fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'horarios.*.hora_inicio' => ['required', 'date_format:H:i'],
            'horarios.*.fecha_fin' => ['required', 'date_format:Y-m-d'],
            'horarios.*.hora_fin' => ['required', 'date_format:H:i'],
        ], [
            'nombre.required' => 'Escribe el nombre de la sede.',
            'nombre.max' => 'El nombre de la sede no puede exceder 150 caracteres.',
            'direccion.required' => 'Escribe la dirección completa de la sede.',
            'direccion.max' => 'La dirección no puede exceder 1000 caracteres.',
            'cupo.required' => 'Indica el aforo máximo por aplicación.',
            'cupo.integer' => 'El aforo máximo debe ser un número entero.',
            'cupo.min' => 'El aforo máximo debe ser al menos 1.',
            'horarios.required' => 'Registra al menos una aplicación del examen.',
            'horarios.min' => 'Registra al menos una aplicación del examen.',
            'horarios.*.fecha_inicio.required' => 'Indica la fecha de inicio.',
            'horarios.*.hora_inicio.required' => 'Indica la hora de inicio.',
            'horarios.*.fecha_fin.required' => 'Indica la fecha de fin.',
            'horarios.*.hora_fin.required' => 'Indica la hora de fin.',
        ]);

        $intervalos = [];

        foreach ($datos['horarios'] as $posicion => $horario) {
            $inicio = Carbon::createFromFormat(
                'Y-m-d H:i',
                $horario['fecha_inicio'].' '.$horario['hora_inicio'],
                config('app.timezone')
            );
            $fin = Carbon::createFromFormat(
                'Y-m-d H:i',
                $horario['fecha_fin'].' '.$horario['hora_fin'],
                config('app.timezone')
            );

            if ($fin->lessThanOrEqualTo($inicio)) {
                throw ValidationException::withMessages([
                    "horarios.{$posicion}.fecha_fin" => 'La fecha y hora de fin deben ser posteriores al inicio.',
                ]);
            }

            foreach ($intervalos as $previo) {
                if ($inicio->lessThan($previo['fin']) && $previo['inicio']->lessThan($fin)) {
                    throw ValidationException::withMessages([
                        "horarios.{$posicion}.fecha_inicio" => 'Esta aplicación se empalma con otra de la misma sede.',
                    ]);
                }
            }

            $intervalos[] = ['inicio' => $inicio, 'fin' => $fin];
        }

        $datos['nombre'] = trim($datos['nombre']);
        $datos['direccion'] = trim($datos['direccion']);
        $datos['horarios'] = array_values($datos['horarios']);

        return $datos;
    }
}
