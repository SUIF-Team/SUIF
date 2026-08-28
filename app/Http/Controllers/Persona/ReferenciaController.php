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
 *
 * El paso arranca en un selector —index()— que explica los dos caminos de pago
 * y encamina a la persona. Por ahora sólo está construido el individual, que
 * vive en individual(): es el que entrega una referencia propia y su formato.
 */
class ReferenciaController extends Controller
{
    /**
     * Selector entre referencia individual y referencia especial.
     */
    public function index()
    {
        $avance = new AvancePersona(Auth::id());

        /* Con la referencia ya entregada el selector no decide nada: la persona
           necesita su número y su formato, no volver a elegir camino. */
        if ($avance->tienePago()) {
            return redirect()->route('persona.referencia.individual');
        }

        return view('persona.referencia-seleccion', [
            'solicitudAprobada' => $avance->solicitudAprobada(),
        ]);
    }

    /**
     * Camino individual: obtener la referencia propia, consultarla y bajar el PDF.
     */
    public function individual(CatalogoReferencias $catalogo)
    {
        $avance = new AvancePersona(Auth::id());

        /* Sin solicitud aprobada no hay nada que obtener; el aviso de espera
           vive en el selector. Se conserva lo que traiga la sesión: si venimos
           de una asignación rechazada porque la aprobación se revocó, el motivo
           tiene que llegar hasta allá y no morir en este rebote. */
        if (!$avance->solicitudAprobada()) {
            session()->reflash();

            return redirect()->route('persona.referencia.index');
        }

        $referencia = $avance->tienePago() ? $catalogo->referenciaDePersona((int) Auth::id()) : null;

        return view('persona.referencia', [
            'referencia' => $referencia,
            /* El inventario sólo importa mientras no haya referencia entregada. */
            'hayDisponibles' => $referencia === null && $catalogo->resumen()['entregables'] > 0,
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
                ->route('persona.referencia.individual')
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('persona.referencia.individual')
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
