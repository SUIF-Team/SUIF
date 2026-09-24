<?php

namespace App\Http\Controllers\Admin;

use App\Consultas\ConsultaPagos;
use App\Http\Controllers\Controller;
use App\Servicios\FormatoPagoDec;
use App\Servicios\GestionResponsables;
use App\Servicios\RevisionPagos;
use App\Support\Admin\NotificacionResultado;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

    public function show(
        string $id,
        ConsultaPagos $consulta_pagos,
        NotificacionResultado $notificacion_resultado,
        GestionResponsables $gestion_responsables,
        FormatoPagoDec $formato_pago
    ) {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        /* Desde la bandeja, "Ver pago" llega aquí y no a la pantalla de
           resultado: un pago resuelto también se reanuda desde el detalle. */
        $acciones = in_array(
            $pago['estado_persistido'],
            [ConsultaPagos::COMPLETADO, ConsultaPagos::DECLINADO],
            true
        )
            ? [$notificacion_resultado->accionReanudarPago($pago['id'])]
            : [];

        $formato = $this->opcionesFormato($pago, $gestion_responsables, $formato_pago);

        return view('admin.pago-detalle', compact('pago', 'acciones', 'formato'));
    }

    /**
     * Descarga el formato de pago de la DEC ya lleno.
     *
     * Es POST y no GET porque deja escrito en el pago quién lo atendió:
     * volver a generarlo reproduce el mismo formato. La respuesta es el
     * archivo, así que el navegador lo descarga sin salir del expediente.
     */
    public function formato(
        Request $request,
        string $id,
        ConsultaPagos $consulta_pagos,
        GestionResponsables $gestion_responsables,
        FormatoPagoDec $formato_pago
    ) {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        $destino = route('admin.pagos.show', ['id' => $pago['id']]);
        $responsables = $gestion_responsables->activos();
        $motivo = $formato_pago->motivoNoDisponible($pago, $responsables);

        if ($motivo !== null) {
            return $this->responder($request, 'warning', $motivo, $destino);
        }

        $datos = $request->validate([
            'responsable' => ['required', 'integer', Rule::in(array_column($responsables, 'id'))],
        ], [
            'responsable.required' => 'Selecciona quién atendió el pago.',
            'responsable.integer' => 'Selecciona quién atendió el pago.',
            'responsable.in' => 'El responsable seleccionado ya no está activo.',
        ]);

        $responsable = collect($responsables)->firstWhere('id', (int) $datos['responsable']);

        $descarga = $formato_pago->descarga($pago, $responsable);

        /* Quien genera varios formatos seguidos casi siempre los atiende la
           misma persona: el siguiente expediente la trae preseleccionada. */
        $request->session()->put('formato_pago.responsable', $responsable['id']);

        return $descarga;
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
        Request $request,
        string $id,
        ConsultaPagos $consulta_pagos,
        RevisionPagos $revision_pagos
    ) {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        try {
            $revision_pagos->aprobar((int) $pago['id']);
        } catch (DomainException $exception) {
            return $this->responder(
                $request,
                'warning',
                $exception->getMessage(),
                route('admin.pagos.show', ['id' => $pago['id']])
            );
        }

        /* Sin mensaje: la pantalla de resultado ya explica el desenlace y el
           redirect de siempre tampoco flasheaba nada. */
        $destino = route('admin.pagos.resultado', ['id' => $pago['id']]);

        return $request->expectsJson()
            ? response()->json(['tipo' => 'success', 'mensaje' => '', 'redirigir' => $destino])
            : redirect()->to($destino);
    }

    public function rechazar(
        Request $request,
        string $id,
        ConsultaPagos $consulta_pagos,
        RevisionPagos $revision_pagos
    ) {
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
            /* El motivo del rechazo es texto escrito a mano: recargar lo
               devolvía con old() pero desde arriba de la pantalla. */
            return $this->responder(
                $request,
                'warning',
                $exception->getMessage(),
                route('admin.pagos.show', ['id' => $pago['id']])
            );
        }

        /* Sin mensaje: la pantalla de resultado ya explica el desenlace y el
           redirect de siempre tampoco flasheaba nada. */
        $destino = route('admin.pagos.resultado', ['id' => $pago['id']]);

        return $request->expectsJson()
            ? response()->json(['tipo' => 'success', 'mensaje' => '', 'redirigir' => $destino])
            : redirect()->to($destino);
    }

    /**
     * Devuelve a revisión un pago ya resuelto.
     *
     * Termina en la pantalla de detalle, no en la de resultado: el pago dejó
     * de estar resuelto y lo que sigue es volver a decidirlo.
     */
    public function reanudar(
        Request $request,
        string $id,
        ConsultaPagos $consulta_pagos,
        RevisionPagos $revision_pagos
    ) {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        try {
            $revision_pagos->reanudar((int) $pago['id']);
        } catch (DomainException $exception) {
            return $this->responder(
                $request,
                'warning',
                $exception->getMessage(),
                route('admin.pagos.show', ['id' => $pago['id']])
            );
        }

        return $this->responder(
            $request,
            'success',
            'El pago volvió a revisión.',
            route('admin.pagos.show', ['id' => $pago['id']])
        );
    }

    public function resultado(
        string $id,
        ConsultaPagos $consulta_pagos,
        NotificacionResultado $notificacion_resultado,
        GestionResponsables $gestion_responsables,
        FormatoPagoDec $formato_pago
    ) {
        $pago = $this->obtenerPago($id, $consulta_pagos);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        if (!in_array($pago['estado_persistido'], [ConsultaPagos::COMPLETADO, ConsultaPagos::DECLINADO], true)) {
            return redirect()->route('admin.pagos.show', ['id' => $pago['id']]);
        }

        $notificacion = $notificacion_resultado->paraPago($pago);

        /* Validar trae a la DEC aquí, y lo que sigue es generar el comprobante. */
        return view('admin.notificacion-resultado', [
            'persona' => $notificacion['persona'],
            'notificacion' => $notificacion,
            'formato' => $this->opcionesFormato($pago, $gestion_responsables, $formato_pago),
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

    /**
     * Lo que necesita la tarjeta «Generar comprobante», o null mientras el
     * pago no esté validado: antes no hay nada que facturar.
     *
     * @param  array<string, mixed>  $pago
     * @return array{ruta: string, motivo: ?string, responsables: array<int, array<string, mixed>>, sugerido: ?int}|null
     */
    private function opcionesFormato(
        array $pago,
        GestionResponsables $gestion_responsables,
        FormatoPagoDec $formato_pago
    ): ?array {
        if ($pago['estado_persistido'] !== ConsultaPagos::COMPLETADO) {
            return null;
        }

        $responsables = $gestion_responsables->activos();

        return [
            'ruta' => route('admin.pagos.formato', ['id' => $pago['id']]),
            'motivo' => $formato_pago->motivoNoDisponible($pago, $responsables),
            'responsables' => $responsables,
            'sugerido' => $this->responsableSugerido($pago, $responsables),
        ];
    }

    /**
     * A quién preseleccionar en «Atendido por»: al que ya quedó en el pago,
     * luego al último que eligió este administrador en su sesión y, si sólo
     * hay uno activo, a ése. Sólo cuentan los que siguen activos.
     *
     * @param  array<string, mixed>  $pago
     * @param  array<int, array<string, mixed>>  $responsables
     */
    private function responsableSugerido(array $pago, array $responsables): ?int
    {
        $activos = array_column($responsables, 'id');

        foreach ([$pago['id_responsable'], session('formato_pago.responsable')] as $candidato) {
            if ($candidato !== null && in_array((int) $candidato, $activos, true)) {
                return (int) $candidato;
            }
        }

        return count($activos) === 1 ? $activos[0] : null;
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
