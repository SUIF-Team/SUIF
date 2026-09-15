<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\GestionResponsables;
use DomainException;
use Illuminate\Http\Request;

/**
 * Admin\ResponsableController
 *
 * Responsabilidad: alta, edición y baja del personal de la DEC que atiende los
 * pagos. Lo abre quien gestiona pagos, que es quien elige al responsable al
 * generar el formato de pago.
 */
class ResponsableController extends Controller
{
    public function index(GestionResponsables $gestion)
    {
        return view('admin.responsables', [
            'responsables' => $gestion->lista(),
        ]);
    }

    public function create()
    {
        return view('admin.responsable-formulario', [
            'responsable' => null,
        ]);
    }

    public function store(Request $request, GestionResponsables $gestion)
    {
        $gestion->crear($this->validar($request));

        return $this->responder(
            $request,
            'success',
            'El responsable se dio de alta correctamente.',
            route('admin.responsables.index')
        );
    }

    public function edit(int $id, GestionResponsables $gestion)
    {
        try {
            $responsable = $gestion->responsable($id);
        } catch (DomainException $exception) {
            /* Abrir el formulario es una navegación, no una acción: si el
               registro ya no existe se vuelve al listado. */
            return redirect()
                ->route('admin.responsables.index')
                ->with('error', $exception->getMessage());
        }

        return view('admin.responsable-formulario', [
            'responsable' => $responsable,
        ]);
    }

    public function update(Request $request, int $id, GestionResponsables $gestion)
    {
        try {
            $gestion->actualizar($id, $this->validar($request));
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'El responsable se actualizó correctamente.',
            route('admin.responsables.index')
        );
    }

    /**
     * La baja lo saca del selector del formato y conserva el renglón: los
     * pagos que ya atendió siguen apuntando a él.
     */
    public function destroy(Request $request, int $id, GestionResponsables $gestion)
    {
        try {
            $gestion->desactivar($id);
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'El responsable quedó dado de baja.',
            route('admin.responsables.index')
        );
    }

    public function reactivar(Request $request, int $id, GestionResponsables $gestion)
    {
        try {
            $gestion->reactivar($id);
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'El responsable volvió a estar activo.',
            route('admin.responsables.index')
        );
    }

    /**
     * Los límites salen de las columnas de RESPONSABLE, que miden 55. El
     * apellido materno es opcional: vacío se guarda como nulo.
     *
     * @return array{nombre: string, apellido_paterno: string, apellido_materno: ?string}
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:55'],
            'apellido_paterno' => ['required', 'string', 'max:55'],
            'apellido_materno' => ['nullable', 'string', 'max:55'],
        ], [
            'nombre.required' => 'Escribe el nombre del responsable.',
            'nombre.max' => 'El nombre no puede exceder 55 caracteres.',
            'apellido_paterno.required' => 'Escribe el apellido paterno.',
            'apellido_paterno.max' => 'El apellido paterno no puede exceder 55 caracteres.',
            'apellido_materno.max' => 'El apellido materno no puede exceder 55 caracteres.',
        ]);

        return [
            'nombre' => trim($datos['nombre']),
            'apellido_paterno' => trim($datos['apellido_paterno']),
            'apellido_materno' => trim((string) ($datos['apellido_materno'] ?? '')) ?: null,
        ];
    }
}
