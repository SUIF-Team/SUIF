<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\AvancePersona;
use App\Support\Admin\RevisionPagos;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoController extends Controller
{
    public function index()
    {
        $avance = $this->avanceActual();
        $pago_estado = $avance->estadoPagoVista();
        $puede_cargar = $avance->solicitudAprobada()
            && $avance->tienePago()
            && in_array($pago_estado, ['sin_cargar', 'rechazado'], true);

        return view('persona.pago', [
            'pagoEstado' => $pago_estado,
            'puedeCargar' => $puede_cargar,
            'mensajeBloqueo' => $this->mensajeBloqueo($avance, $pago_estado),
            'motivoRechazo' => $avance->motivoRechazoPago(),
            'cuota' => number_format((float) ($avance->montoPago() ?? config('suif.cuota_recuperacion', 7000)), 2, '.', ','),
            'moneda' => config('suif.moneda', 'MXN'),
            'tracker' => $this->tracker($pago_estado),
        ]);
    }

    public function subirComprobante(Request $request, RevisionPagos $revision_pagos)
    {
        $avance = $this->avanceActual();
        $pago_estado = $avance->estadoPagoVista();

        if (!$avance->solicitudAprobada() || !$avance->tienePago()
            || !in_array($pago_estado, ['sin_cargar', 'rechazado'], true)) {
            return redirect()
                ->route('persona.pago.index')
                ->with('warning', $this->mensajeBloqueo($avance, $pago_estado));
        }

        $request->validate([
            'comprobante' => ['required', 'file', 'mimes:pdf', 'max:1024'],
        ], [
            'comprobante.required' => 'Se requiere un comprobante de pago.',
            'comprobante.mimes' => 'El comprobante debe ser un archivo PDF.',
            'comprobante.max' => 'El comprobante no debe exceder los 1024 KB.',
        ]);

        $ruta = 'solicitudes/'.$avance->idSolicitud().'/'.Str::uuid().'.pdf';
        $disco = Storage::disk('comprobantes');
        $disco->putFileAs(dirname($ruta), $request->file('comprobante'), basename($ruta));

        try {
            $revision_pagos->registrarComprobanteDePersona((int) Auth::id(), $ruta);
        } catch (DomainException $exception) {
            $disco->delete($ruta);

            return redirect()
                ->route('persona.pago.index')
                ->with('warning', $exception->getMessage());
        }

        return redirect()->route('persona.pago.index')
            ->with('success', 'Tu comprobante fue enviado. El proceso de revisión puede tardar hasta 24 horas.');
    }

    private function avanceActual(): AvancePersona
    {
        return new AvancePersona(Auth::id());
    }

    private function mensajeBloqueo(AvancePersona $avance, string $pago_estado): ?string
    {
        if (!$avance->solicitudAprobada()) {
            return 'El pago estará disponible cuando se apruebe tu solicitud y documentación.';
        }

        if (!$avance->tienePago()) {
            return 'Aún no existe una referencia de pago ligada a tu solicitud.';
        }

        if ($pago_estado === 'revision') {
            return 'Tu comprobante ya está en revisión y no puede reemplazarse por ahora.';
        }

        if ($pago_estado === 'validado') {
            return 'Tu comprobante ya fue aprobado y no puede reemplazarse.';
        }

        return null;
    }

    private function tracker(string $pago_estado): array
    {
        if ($pago_estado === 'rechazado') {
            return ['pasos' => ['completo', 'completo', 'error'], 'conectores' => ['completo', 'error']];
        }

        if ($pago_estado === 'validado') {
            return ['pasos' => ['completo', 'completo', 'completo'], 'conectores' => ['completo', 'completo']];
        }

        return ['pasos' => ['completo', 'activo', 'pendiente'], 'conectores' => ['completo', 'pendiente']];
    }
}
