<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ConsultaPagos;
use App\Support\Admin\NotificacionResultado;
use App\Support\Admin\RevisionPagos;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PagoController extends Controller
{
    public function index(ConsultaPagos $consulta_pagos)
    {
        $pagos = collect($consulta_pagos->bandeja())
            ->map(function (array $pago): array {
                $pago['ruta_detalle'] = route('admin.pagos.show', ['id' => $pago['id']]);

                return $pago;
            })
            ->all();

        return view('admin.pagos', [
            'datos_vista' => ['pagos' => $pagos],
        ]);
    }

    public function show(string $id, ConsultaPagos $consulta_pagos)
    {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        return view('admin.pago-detalle', compact('pago'));
    }

    /**
     * Sirve el comprobante privado sólo si corresponde al pago visible.
     */
    public function comprobante(string $id, ConsultaPagos $consulta_pagos)
    {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            abort(404);
        }

        $ruta = $this->rutaComprobante($id, $consulta_pagos);

        abort_unless($ruta, 404);

        $nombre = str_replace(["\r", "\n", '"'], '', basename($ruta));

        $respuesta = response()->file(Storage::disk('comprobantes')->path($ruta), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-store');

        return $respuesta;
    }

    public function validar(
        string $id,
        ConsultaPagos $consulta_pagos,
        RevisionPagos $revision_pagos
    ): RedirectResponse {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        try {
            $revision_pagos->aprobar((int) $pago['id']);
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.pagos.show', ['id' => $pago['id']])
                ->with('warning', $exception->getMessage());
        }

        return redirect()->route('admin.pagos.resultado', ['id' => $pago['id']]);
    }

    public function rechazar(
        Request $request,
        string $id,
        ConsultaPagos $consulta_pagos,
        RevisionPagos $revision_pagos
    ): RedirectResponse {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        $datos = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:2000'],
        ], [
            'motivo_rechazo.required' => 'Escribe el motivo del rechazo.',
            'motivo_rechazo.max' => 'El motivo del rechazo no debe exceder 2000 caracteres.',
        ]);

        try {
            $revision_pagos->rechazar((int) $pago['id'], $datos['motivo_rechazo']);
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.pagos.show', ['id' => $pago['id']])
                ->withInput()
                ->with('warning', $exception->getMessage());
        }

        return redirect()->route('admin.pagos.resultado', ['id' => $pago['id']]);
    }

    public function resultado(
        string $id,
        ConsultaPagos $consulta_pagos,
        NotificacionResultado $notificacion_resultado
    ) {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        if (!in_array($pago['estado_persistido'], [ConsultaPagos::COMPLETADO, ConsultaPagos::DECLINADO], true)) {
            return redirect()->route('admin.pagos.show', ['id' => $pago['id']]);
        }

        $notificacion = $notificacion_resultado->paraPago($pago);

        return view('admin.notificacion-resultado', [
            'persona' => $notificacion['persona'],
            'notificacion' => $notificacion,
        ]);
    }

    private function obtenerPago(string $id, ConsultaPagos $consulta_pagos): array|RedirectResponse
    {
        if (!ctype_digit($id)) {
            return redirect()
                ->route('admin.pagos.index')
                ->with('warning', 'El registro de pago solicitado no fue encontrado.');
        }

        $pago = $consulta_pagos->pago((int) $id);

        if (!$pago) {
            return redirect()
                ->route('admin.pagos.index')
                ->with('warning', 'El registro de pago solicitado no fue encontrado.');
        }

        return $pago;
    }

    private function rutaComprobante(string $id, ConsultaPagos $consulta_pagos): ?string
    {
        if (!ctype_digit($id)) {
            return null;
        }

        $pago = $consulta_pagos->pago((int) $id);

        if (!$pago || !$pago['comprobante_disponible']) {
            return null;
        }

        $ruta = \Illuminate\Support\Facades\DB::table('pago')
            ->where('pago_id_pago', (int) $id)
            ->value('pago_comprobante_path');

        return is_string($ruta) && $consulta_pagos->archivoDisponible($ruta)
            ? $ruta
            : null;
    }
}
