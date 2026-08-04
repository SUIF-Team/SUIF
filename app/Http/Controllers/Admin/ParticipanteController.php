<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\NotificacionResultado;
use App\Support\Admin\OrigenBandejaAdmin;
use App\Support\Admin\PreRegistroDatosPrueba;
use App\Support\Admin\ParticipanteRegistradoDatosPrueba;
use Illuminate\Http\Request;

/**
 * Admin\ParticipanteController
 *
 * Migrado desde: app/controllers/admin/ParticipanteController.php
 * Responsabilidad: listado, búsqueda y gestión de participantes por el administrador.
 */
class ParticipanteController extends Controller
{
    public function index(
        Request $request,
        PreRegistroDatosPrueba $datos_prueba,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $participantes = collect($datos_prueba->participantes())
            ->map(function (array $participante) use ($request, $datos_prueba, $origen_bandeja): array {
                $estados = (array) $request->session()->get($this->claveEstado($participante['id']), []);

                if (($estados['resultado'] ?? null) === 'revision') {
                    $participante['estado_bandeja'] = 'En revisión';
                } elseif (($estados['resultado'] ?? null) === 'aprobado') {
                    $participante['estado_bandeja'] = 'Aceptado';
                } elseif (($estados['resultado'] ?? null) === 'rechazado') {
                    $participante['estado_bandeja'] = 'Rechazado';
                }

                $participante['clase_estado'] = $this->claseEstado($participante['estado_bandeja']);
                $participante['ruta_expediente'] = route('admin.participantes.show', [
                    'id' => $participante['id'],
                    'origen' => $origen_bandeja->contexto(OrigenBandejaAdmin::PREREGISTROS)['origen'],
                ]);

                return $participante;
            })
            ->sortByDesc('fecha_registro')
            ->values()
            ->all();

        return view('admin.participantes', [
            'datos_vista' => ['participantes' => $participantes],
        ]);
    }

    public function registrados(
        ParticipanteRegistradoDatosPrueba $datos_prueba,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $participantes = collect($datos_prueba->participantes())
            ->map(function (array $participante) use ($origen_bandeja): array {
                $participante['ruta_expediente'] = route('admin.participantes.show', [
                    'id' => $participante['id'],
                    'origen' => $origen_bandeja->contexto(OrigenBandejaAdmin::PARTICIPANTES_REGISTRADOS)['origen'],
                ]);

                return $participante;
            })
            ->sortByDesc('fecha_registro')
            ->values()
            ->all();

        return view('admin.participantes-registrados', [
            'datos_vista' => ['participantes' => $participantes],
        ]);
    }

    public function show(
        Request $request,
        string $id,
        PreRegistroDatosPrueba $datos_prueba,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['resultado'] ?? null) === 'rechazado') {
            return redirect()->route('admin.participantes.resultado', [
                'id' => $id,
                'origen' => $contexto_bandeja['origen'],
            ]);
        }

        if (($estados['preregistro'] ?? null) === 'Completado') {
            return redirect()->route('admin.documentos.show', [
                'id' => $id,
                'origen' => $contexto_bandeja['origen'],
            ]);
        }

        return view('admin.preregistro-detalle', [
            'participante' => $participante,
            'estados' => $estados,
            'contexto_bandeja' => $contexto_bandeja,
        ]);
    }

    public function aceptarPreRegistro(
        Request $request,
        string $id,
        PreRegistroDatosPrueba $datos_prueba,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        abort_unless($datos_prueba->participante($id), 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['preregistro'] ?? null) !== 'En revisión') {
            return redirect()
                ->route('admin.participantes.show', [
                    'id' => $id,
                    'origen' => $contexto_bandeja['origen'],
                ])
                ->with('warning', 'La solicitud ya fue resuelta y no puede aceptarse nuevamente.');
        }

        $request->session()->put($this->claveEstado($id), $datos_prueba->estadoAceptado());

        return redirect()->route('admin.documentos.show', [
            'id' => $id,
            'origen' => $contexto_bandeja['origen'],
        ]);
    }

    public function rechazarPreRegistro(
        Request $request,
        string $id,
        PreRegistroDatosPrueba $datos_prueba,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        abort_unless($datos_prueba->participante($id), 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['preregistro'] ?? null) !== 'En revisión') {
            return redirect()
                ->route('admin.participantes.show', [
                    'id' => $id,
                    'origen' => $contexto_bandeja['origen'],
                ])
                ->with('warning', 'La solicitud ya fue resuelta y no puede rechazarse nuevamente.');
        }

        $request->session()->put($this->claveEstado($id), [
            'general' => 'Rechazado',
            'preregistro' => 'Rechazado',
            'documentacion' => 'Pendiente',
            'resultado' => 'rechazado',
        ]);

        return redirect()->route('admin.participantes.resultado', [
            'id' => $id,
            'origen' => $contexto_bandeja['origen'],
        ]);
    }

    public function resultado(
        Request $request,
        string $id,
        PreRegistroDatosPrueba $datos_prueba,
        NotificacionResultado $notificacion_resultado,
        OrigenBandejaAdmin $origen_bandeja
    ) {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = $this->estados($request, $id, $datos_prueba);

        if (($estados['resultado'] ?? null) !== 'rechazado') {
            return redirect()->route('admin.participantes.show', [
                'id' => $id,
                'origen' => $contexto_bandeja['origen'],
            ]);
        }

        $notificacion = $notificacion_resultado->paraPreRegistro($participante, $estados);
        $notificacion = array_merge($notificacion, [
            'ruta_regreso' => $contexto_bandeja['ruta'],
            'etiqueta_regreso' => $contexto_bandeja['etiqueta'],
            'etiqueta_regreso_accesible' => $contexto_bandeja['etiqueta_accesible'],
        ]);

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

    private function claseEstado(string $estado): string
    {
        return match ($estado) {
            'Aceptado', 'Aprobado' => 'admin-bandeja-preregistros-estado-aceptado',
            'Rechazado' => 'admin-bandeja-preregistros-estado-rechazado',
            default => 'admin-bandeja-preregistros-estado-revision',
        };
    }
}
