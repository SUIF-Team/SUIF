<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\PreRegistroDatosPrueba;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin\DocumentacionController
 *
 * Migrado desde: app/controllers/admin/DocumentacionController.php
 * Responsabilidad: revisión y validación de documentación de participantes por el administrador.
 */
class DocumentoController extends Controller
{
    public function show(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = array_merge(
            $datos_prueba->estadoInicial(),
            (array) $request->session()->get('suif.admin.preregistro.'.$id, [])
        );

        return view('admin.preregistro-documentacion', compact('participante', 'estados'));
    }

    public function validar(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $documentos = $request->input('documentos', []);
        $documentos_requeridos = array_column($participante['documentos'], 'id');

        $request->validate([
            'documentos' => ['required', 'array'],
            'documentos.*' => ['required', 'in:aprobado,rechazado'],
        ]);

        $documentos_recibidos = array_keys($documentos);

        if (
            array_diff($documentos_requeridos, $documentos_recibidos) ||
            array_diff($documentos_recibidos, $documentos_requeridos)
        ) {
            throw ValidationException::withMessages([
                'documentos' => 'Resuelve todos los documentos requeridos antes de guardar.',
            ]);
        }

        $resultado = collect($documentos)->every(
            fn (string $estado): bool => $estado === 'aprobado'
        ) ? 'aprobado' : 'revision';

        $estados = array_merge(
            $datos_prueba->estadoInicial(),
            (array) $request->session()->get($this->claveEstado($id), []),
            [
                'preregistro' => 'Completado',
                'documentacion' => $resultado === 'aprobado' ? 'Completado' : 'En revisión',
                'documentos' => $documentos,
                'resultado' => $resultado,
            ]
        );

        $request->session()->put($this->claveEstado($id), $estados);

        return redirect()->route('admin.documentos.resultado', ['id' => $id]);
    }

    public function resultado(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = (array) $request->session()->get($this->claveEstado($id), []);
        $tipo_resultado = $estados['resultado'] ?? null;

        if (!in_array($tipo_resultado, ['aprobado', 'revision'], true)) {
            return redirect()->route('admin.documentos.show', ['id' => $id]);
        }

        return view('admin.preregistro-resultado', compact(
            'participante',
            'estados',
            'tipo_resultado'
        ));
    }

    private function claveEstado(string $id): string
    {
        return 'suif.admin.preregistro.'.$id;
    }
}
