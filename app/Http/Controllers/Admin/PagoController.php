<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\PagoDatosPrueba;

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
        abort_unless($datos_prueba->pago($id), 404);

        return redirect()
            ->route('admin.pagos.index')
            ->with('warning', 'El detalle del pago estará disponible próximamente.');
    }
}
