<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\NotificacionResultado;
use App\Support\Admin\PreRegistroDatosPrueba;
use Illuminate\Http\Request;

/**
 * Admin\ParticipanteController
 *
 * Migrado desde: app/controllers/admin/ParticipanteController.php
 * Responsabilidad: listado, búsqueda y gestión de participantes por el administrador.
 */
class ParticipanteController extends Controller
{
    public function index(Request $request, PreRegistroDatosPrueba $datos_prueba)
    {
        $participantes = collect($datos_prueba->participantes())
            ->map(function (array $participante) use ($request, $datos_prueba): array {
                $estados = (array) $request->session()->get($this->claveEstado($participante['id']), []);

                if (($estados['resultado'] ?? null) === 'revision') {
                    $participante['estado_bandeja'] = 'En revisión';
                } elseif (($estados['resultado'] ?? null) === 'aprobado') {
                    $participante['estado_bandeja'] = 'Aceptado';
                } elseif (($estados['resultado'] ?? null) === 'rechazado') {
                    $participante['estado_bandeja'] = 'Rechazado';
                }

                $participante['ruta_expediente'] = route('admin.participantes.show', ['id' => $participante['id']]);

                return $participante;
            })
            ->sortByDesc('fecha_registro')
            ->values()
            ->all();

        return view('admin.participantes', [
            'datos_vista' => ['participantes' => $participantes],
        ]);
    }

    public function show(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['resultado'] ?? null) === 'rechazado') {
            return redirect()->route('admin.participantes.resultado', ['id' => $id]);
        }

        if (($estados['preregistro'] ?? null) === 'Completado') {
            return redirect()->route('admin.documentos.show', ['id' => $id]);
        }

        return view('admin.preregistro-detalle', [
            'participante' => $participante,
            'estados' => $estados,
        ]);
    }

    public function aceptarPreRegistro(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        abort_unless($datos_prueba->participante($id), 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['preregistro'] ?? null) !== 'En revisión') {
            return redirect()
                ->route('admin.participantes.show', ['id' => $id])
                ->with('warning', 'La solicitud ya fue resuelta y no puede aceptarse nuevamente.');
        }

        $request->session()->put($this->claveEstado($id), $datos_prueba->estadoAceptado());

        return redirect()->route('admin.documentos.show', ['id' => $id]);
    }

    public function rechazarPreRegistro(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        abort_unless($datos_prueba->participante($id), 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['preregistro'] ?? null) !== 'En revisión') {
            return redirect()
                ->route('admin.participantes.show', ['id' => $id])
                ->with('warning', 'La solicitud ya fue resuelta y no puede rechazarse nuevamente.');
        }

        $request->session()->put($this->claveEstado($id), [
            'general' => 'Rechazado',
            'preregistro' => 'Rechazado',
            'documentacion' => 'Pendiente',
            'resultado' => 'rechazado',
        ]);

        return redirect()->route('admin.participantes.resultado', ['id' => $id]);
    }

    public function resultado(
        Request $request,
        string $id,
        PreRegistroDatosPrueba $datos_prueba,
        NotificacionResultado $notificacion_resultado
    ) {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['resultado'] ?? null) !== 'rechazado') {
            return redirect()->route('admin.participantes.show', ['id' => $id]);
        }

        $notificacion = $notificacion_resultado->paraPreRegistro($participante, $estados);

        return view('admin.notificacion-resultado', [
            'participante' => $notificacion['participante'],
            'notificacion' => $notificacion,
        ]);
    }

    private function estados(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba): array
    {
        return array_merge(
            $datos_prueba->estadoInicial(),
            (array) $request->session()->get($this->claveEstado($id), [])
        );
    }

    private function claveEstado(string $id): string
    {
        return 'suif.admin.preregistro.'.$id;
    }
}
