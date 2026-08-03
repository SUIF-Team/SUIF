<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\PagoDatosPrueba;
use Illuminate\Http\RedirectResponse;

/**
 * Admin\PagoController
 *
 * Migrado desde: app/controllers/admin/PagoController.php
 * Responsabilidad: validación y gestión de comprobantes de pago por el administrador.
 */
class PagoController extends Controller
{
    public function index(PagoDatosPrueba $datos_prueba)
    {
        $pagos = collect($datos_prueba->pagos())
            ->sortByDesc('fecha_envio_comprobante')
            ->map(function (array $pago): array {
                $pago['ruta_detalle'] = route('admin.pagos.show', ['id' => $pago['id']]);

                return $pago;
            })
            ->values()
            ->all();

        return view('admin.pagos', [
            'datos_vista' => ['pagos' => $pagos],
        ]);
    }

    public function show(string $id, PagoDatosPrueba $datos_prueba)
    {
        $pago = $this->obtenerPagoRevisable($id, $datos_prueba);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        return view('admin.pago-detalle', compact('pago'));
    }

    /**
     * Mantiene una ruta controlada para el comprobante mientras se implementa
     * el almacenamiento y la autorización de archivos.
     */
    public function comprobante(string $id, PagoDatosPrueba $datos_prueba): RedirectResponse
    {
        $pago = $this->obtenerPagoRevisable($id, $datos_prueba);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        return redirect()
            ->route('admin.pagos.show', ['id' => $pago['id']])
            ->with('warning', 'La visualización del comprobante estará disponible próximamente.');
    }

    /**
     * La ruta acepta enlaces temporales sin efectuar cambios de estado.
     */
    public function validar(string $id, PagoDatosPrueba $datos_prueba): RedirectResponse
    {
        return $this->redireccionAccionPendiente($id, $datos_prueba, 'La validación del pago estará disponible próximamente.');
    }

    /**
     * La ruta acepta enlaces temporales sin efectuar cambios de estado.
     */
    public function rechazar(string $id, PagoDatosPrueba $datos_prueba): RedirectResponse
    {
        return $this->redireccionAccionPendiente($id, $datos_prueba, 'El rechazo del pago estará disponible próximamente.');
    }

    private function redireccionAccionPendiente(
        string $id,
        PagoDatosPrueba $datos_prueba,
        string $mensaje
    ): RedirectResponse {
        $pago = $this->obtenerPagoRevisable($id, $datos_prueba);

        if ($pago instanceof RedirectResponse) {
            return $pago;
        }

        return redirect()
            ->route('admin.pagos.show', ['id' => $pago['id']])
            ->with('warning', $mensaje);
    }

    /**
     * Valida los requisitos previos en el servidor antes de mostrar o enlazar
     * cualquier acción del expediente de pago.
     */
    private function obtenerPagoRevisable(
        string $id,
        PagoDatosPrueba $datos_prueba
    ): array|RedirectResponse {
        $pago = $datos_prueba->pago($id);

        if (!$pago) {
            return redirect()
                ->route('admin.pagos.index')
                ->with('warning', 'El registro de pago solicitado no fue encontrado.');
        }

        $mensaje = $datos_prueba->mensajeNoDisponibleParaRevision($pago);

        if ($mensaje) {
            return redirect()
                ->route('admin.pagos.index')
                ->with('warning', $mensaje);
        }

        return $pago;
    }
}
