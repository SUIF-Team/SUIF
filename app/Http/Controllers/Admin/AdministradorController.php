<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\GestionAdministradores;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin\AdministradorController
 *
 * Responsabilidad: alta, edición y baja de quienes operan el sistema, desde el
 * propio panel administrativo.
 *
 * Hasta ahora el único camino era el comando de consola suif:crear-admin, que
 * daba de alta un administrador con todos los privilegios. Aquí se elige de qué
 * área es, y por eso el módulo lo abre sólo quien tiene "Gestionar usuarios".
 */
class AdministradorController extends Controller
{
    public function index(Request $request, GestionAdministradores $gestion)
    {
        $filtros = $request->only(['buscar', 'rol', 'estatus']);
        $datos = $gestion->bandeja($filtros);

        return view('admin.administradores', [
            'administradores' => $datos['administradores'],
            'resumen' => $datos['resumen'],
            'roles' => $gestion->rolesAsignables(),
            'filtros' => $filtros,
        ]);
    }

    public function create(GestionAdministradores $gestion)
    {
        return view('admin.administrador-formulario', [
            'administrador' => null,
            'roles' => $gestion->rolesAsignables(),
            'entidades' => $this->entidades(),
            'modoEdicion' => false,
        ]);
    }

    public function store(Request $request, GestionAdministradores $gestion)
    {
        try {
            $gestion->crear($this->validar($request, false));
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.administradores.index')
            ->with('success', 'El administrador se creó correctamente.');
    }

    public function edit(int $id, GestionAdministradores $gestion)
    {
        try {
            $administrador = $gestion->administrador($id);
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.administradores.index')
                ->with('error', $exception->getMessage());
        }

        return view('admin.administrador-formulario', [
            'administrador' => $administrador,
            'roles' => $gestion->rolesAsignables(),
            'entidades' => $this->entidades(),
            'modoEdicion' => true,
        ]);
    }

    public function update(Request $request, int $id, GestionAdministradores $gestion)
    {
        try {
            $gestion->actualizar($id, $this->validar($request, true, $id), (int) auth()->id());
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.administradores.index')
            ->with('success', 'El administrador se actualizó correctamente.');
    }

    /**
     * La baja retira el acceso y conserva el renglón: es lo que deja rastro de
     * quién dictaminó cada expediente.
     */
    public function destroy(int $id, GestionAdministradores $gestion)
    {
        try {
            $gestion->desactivar($id, (int) auth()->id());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.administradores.index')
            ->with('success', 'El administrador quedó sin acceso al sistema.');
    }

    public function reactivar(int $id, GestionAdministradores $gestion)
    {
        try {
            $gestion->reactivar($id);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.administradores.index')
            ->with('success', 'El administrador recuperó su acceso.');
    }

    /**
     * La clave sólo es obligatoria al dar de alta. Al editar, dejarla vacía
     * conserva la que ya tenía: corregir un apellido no debería obligar a
     * inventarle una contraseña nueva a nadie.
     */
    private function validar(Request $request, bool $modoEdicion, ?int $id = null): array
    {
        $request->merge([
            'curp' => mb_strtoupper(trim((string) $request->input('curp')), 'UTF-8'),
        ]);

        /* La CURP se comprueba dos veces a propósito: aquí para que el error
           salga junto al campo, y otra vez dentro de la transacción del
           servicio, porque PERSONA.PERS_CURP no tiene índice único. */
        $reglaCurp = ['required', 'string', 'size:18', 'regex:/^[A-Z0-9]{18}$/'];
        $reglaCurp[] = Rule::unique('persona', 'pers_curp')->ignore($id, 'pers_id_usuario');

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:45'],
            'primer_apellido' => ['required', 'string', 'max:45'],
            'segundo_apellido' => ['required', 'string', 'max:45'],
            'curp' => $reglaCurp,
            'entidad_federativa' => ['required', 'string', 'size:3', 'exists:entidad_federativa,enfe_clave_inegi'],
            'rol_id' => ['required', 'integer'],
            'clave' => $modoEdicion
                ? ['nullable', 'string', 'min:8', 'max:255']
                : ['required', 'string', 'min:8', 'max:255'],
        ], [
            'nombre.required' => 'Escribe el nombre del administrador.',
            'nombre.max' => 'El nombre no puede exceder 45 caracteres.',
            'primer_apellido.required' => 'Escribe el apellido paterno.',
            'primer_apellido.max' => 'El apellido paterno no puede exceder 45 caracteres.',
            'segundo_apellido.required' => 'Escribe el apellido materno.',
            'segundo_apellido.max' => 'El apellido materno no puede exceder 45 caracteres.',
            'curp.required' => 'Escribe la CURP con la que iniciará sesión.',
            'curp.size' => 'La CURP debe contener exactamente 18 caracteres.',
            'curp.regex' => 'La CURP sólo puede contener letras y números.',
            'curp.unique' => 'Esa CURP ya está registrada en el sistema.',
            'entidad_federativa.required' => 'Selecciona la entidad federativa.',
            'entidad_federativa.exists' => 'Selecciona una entidad federativa válida.',
            'rol_id.required' => 'Selecciona el tipo de administrador.',
            'clave.required' => 'Escribe la clave de acceso.',
            'clave.min' => 'La clave de acceso debe tener al menos 8 caracteres.',
        ]);

        $datos['nombre'] = trim($datos['nombre']);
        $datos['primer_apellido'] = trim($datos['primer_apellido']);
        $datos['segundo_apellido'] = trim($datos['segundo_apellido']);
        $datos['clave'] = (string) ($datos['clave'] ?? '');
        $datos['rol_id'] = (int) $datos['rol_id'];

        return $datos;
    }

    /**
     * Catálogo para el selector, con la clave INEGI que guarda PERSONA.
     *
     * @return array<int, array{clave: string, nombre: string}>
     */
    private function entidades(): array
    {
        return DB::table('entidad_federativa')
            ->orderBy('enfe_entidad_federativa')
            ->get()
            ->map(fn (object $entidad): array => [
                'clave' => (string) $entidad->enfe_clave_inegi,
                'nombre' => (string) $entidad->enfe_entidad_federativa,
            ])
            ->all();
    }
}
