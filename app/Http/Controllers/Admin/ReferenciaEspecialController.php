<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\ReferenciaEspecial;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin\ReferenciaEspecialController
 *
 * Responsabilidad: la bandeja donde la DEC ve qué referencias especiales le
 * pidieron y les asigna una del catálogo.
 *
 * La solicitud la levanta la persona desde su trámite; aquí sólo se emite. El
 * permiso es el mismo del catálogo —Gestionar Referencias— porque es la misma
 * decisión: a quién se le entrega qué número.
 */
class ReferenciaEspecialController extends Controller
{
    public function index(ReferenciaEspecial $referencias)
    {
        return view('admin.referencias-especiales', [
            'solicitudes' => $referencias->pendientes(),
        ]);
    }

    public function show(string $id, ReferenciaEspecial $referencias)
    {
        $solicitud = $referencias->detalle((int) $id);

        abort_if($solicitud === null, 404);

        return view('admin.referencia-especial-detalle', [
            'solicitud' => $solicitud,
        ]);
    }

    public function emitir(string $id, Request $request, ReferenciaEspecial $referencias): RedirectResponse
    {
        $datos = $request->validate([
            'referencia' => ['required', 'integer'],
        ], [
            'referencia.required' => 'Selecciona la referencia bancaria que se va a entregar.',
            'referencia.integer' => 'Selecciona una referencia válida.',
        ]);

        try {
            $resultado = $referencias->emitir((int) $id, (int) $datos['referencia']);
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.referencias.especiales.show', $id)
                ->with('warning', $exception->getMessage());
        }

        return redirect()->route('admin.referencias.especiales.index')->with(
            'success',
            'Se entregó la referencia '.$resultado['referencia'].'. Se avisó a '
            .$resultado['avisados'].' participantes por correo.'
        );
    }
}
