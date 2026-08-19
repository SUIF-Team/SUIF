<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sede;
use App\Servicios\GestionSedes;
use DomainException;
use Illuminate\Http\Request;

/**
 * Admin\SedeController
 *
 * Migrado desde: app/controllers/admin/SedeController.php
 * Responsabilidad: alta, edición y gestión de sedes de aplicación desde el panel administrativo.
 * La programación de cada sede vive en Admin\GrupoController.
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
        return view('admin.sede-formulario', [
            'sede' => Sede::query()->findOrFail($id),
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

    /**
     * La sede sólo captura el lugar. Su programación se registra aparte, en
     * el módulo de grupos.
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:1000'],
            'cupo' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ], [
            'nombre.required' => 'Escribe el nombre de la sede.',
            'nombre.max' => 'El nombre de la sede no puede exceder 150 caracteres.',
            'direccion.required' => 'Escribe la dirección completa de la sede.',
            'direccion.max' => 'La dirección no puede exceder 1000 caracteres.',
            'cupo.required' => 'Indica el aforo máximo por aplicación.',
            'cupo.integer' => 'El aforo máximo debe ser un número entero.',
            'cupo.min' => 'El aforo máximo debe ser al menos 1.',
        ]);

        $datos['nombre'] = trim($datos['nombre']);
        $datos['direccion'] = trim($datos['direccion']);

        return $datos;
    }
}
