<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\NotificacionResultado;
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

        if (in_array($estados['resultado'] ?? null, ['aprobado', 'revision', 'rechazado'], true)) {
            return redirect()->route('admin.documentos.resultado', ['id' => $id]);
        }

        if (($estados['preregistro'] ?? null) !== 'Completado') {
            return redirect()
                ->route('admin.participantes.show', ['id' => $id])
                ->with('warning', 'Completa el pre-registro antes de revisar la documentación.');
        }

        return view('admin.preregistro-documentacion', compact('participante', 'estados'));
    }

    public function validar(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados_actuales = array_merge(
            $datos_prueba->estadoInicial(),
            (array) $request->session()->get($this->claveEstado($id), [])
        );

        if (($estados_actuales['preregistro'] ?? null) !== 'Completado'
            || ($estados_actuales['documentacion'] ?? null) !== 'En revisión') {
            return redirect()
                ->route('admin.documentos.show', ['id' => $id])
                ->with('warning', 'La documentación ya fue resuelta o no está disponible para revisión.');
        }

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

    public function interrumpir(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = array_merge(
            $datos_prueba->estadoInicial(),
            (array) $request->session()->get($this->claveEstado($id), [])
        );

        if (($estados['preregistro'] ?? null) !== 'Completado'
            || ($estados['documentacion'] ?? null) !== 'En revisión') {
            return redirect()
                ->route('admin.documentos.show', ['id' => $id])
                ->with('warning', 'La documentación ya fue resuelta o no está disponible para revisión.');
        }

        $request->session()->put($this->claveEstado($id), array_merge($estados, [
            'general' => 'Rechazado',
            'documentacion' => 'Rechazado',
            'resultado' => 'rechazado',
        ]));

        return redirect()->route('admin.documentos.resultado', ['id' => $id]);
    }

    public function resultado(
        Request $request,
        string $id,
        PreRegistroDatosPrueba $datos_prueba,
        NotificacionResultado $notificacion_resultado
    ) {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = (array) $request->session()->get($this->claveEstado($id), []);

        if (!in_array($estados['resultado'] ?? null, ['aprobado', 'revision', 'rechazado'], true)) {
            return redirect()->route('admin.documentos.show', ['id' => $id]);
        }

        $notificacion = $notificacion_resultado->paraPreRegistro($participante, $estados);

        return view('admin.notificacion-resultado', [
            'participante' => $notificacion['participante'],
            'notificacion' => $notificacion,
        ]);
    }

    private function claveEstado(string $id): string
    {
        return 'suif.admin.preregistro.'.$id;
    }
}
