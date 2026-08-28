<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\AvancePersona;
use App\Servicios\CatalogoReferencias;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * ReferenciaController
 *
 * Migrado desde: app/controllers/ReferenciaController.php
 * Responsabilidad: entrega y consulta de la referencia bancaria de pago.
 */
class ReferenciaController extends Controller
{
    public function index(CatalogoReferencias $catalogo)
    {
        $avance = new AvancePersona(Auth::id());
        $referencia = $avance->tienePago() ? $catalogo->referenciaDePersona((int) Auth::id()) : null;

        /* El inventario sólo importa mientras no haya referencia entregada. */
        $pendiente = $referencia === null && $avance->solicitudAprobada();

        return view('persona.referencia', [
            'referencia' => $referencia,
            'solicitudAprobada' => $avance->solicitudAprobada(),
            'hayDisponibles' => $pendiente && $catalogo->resumen()['entregables'] > 0,
            'cuota' => number_format(
                (float) ($referencia['monto'] ?? config('suif.cuota_recuperacion', 7000)),
                2,
                '.',
                ','
            ),
            'moneda' => config('suif.moneda', 'MXN'),
        ]);
    }

    /**
     * Entrega una referencia libre del catálogo y la liga a la solicitud.
     */
    public function generar(CatalogoReferencias $catalogo): RedirectResponse
    {
        try {
            $catalogo->asignar((int) Auth::id());
        } catch (DomainException $exception) {
            return redirect()
                ->route('persona.referencia.index')
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('persona.referencia.index')
            ->with('success', 'Tu referencia bancaria quedó asignada. Es única y personal.');
    }

    /**
     * Descarga el formato PDF para pagar en ventanilla.
     */
    public function formato(CatalogoReferencias $catalogo)
    {
        $referencia = $catalogo->referenciaDePersona((int) Auth::id());

        abort_unless($referencia && $referencia['ruta_formato'], 404);

        $nombre = preg_replace('/[^A-Za-z0-9-]/', '', $referencia['referencia']);

        $respuesta = response()->download(
            Storage::disk('referencias')->path($referencia['ruta_formato']),
            'referencia-'.$nombre.'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );

        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-store');

        return $respuesta;
    }
}
