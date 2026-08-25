<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\AvancePersona;
use App\Servicios\CatalogoReferencias;
use App\Support\Admin\RevisionPagos;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoController extends Controller
{
    public function index(CatalogoReferencias $catalogo)
    {
        $avance = $this->avanceActual();
        $pago_estado = $avance->estadoPagoVista();
        $puede_cargar = $avance->solicitudAprobada()
            && $avance->tienePago()
            && in_array($pago_estado, ['sin_cargar', 'rechazado'], true);

        $monto_esperado = $this->montoEsperado($avance, $catalogo);

        return view('persona.pago', [
            'pagoEstado' => $pago_estado,
            'puedeCargar' => $puede_cargar,
            'mensajeBloqueo' => $this->mensajeBloqueo($avance, $pago_estado),
            'motivoRechazo' => $avance->motivoRechazoPago(),
            'cuota' => number_format($monto_esperado, 2, '.', ','),
            'vistaFormulario' => $this->vistaFormulario($monto_esperado),
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

        /* La validación corre antes de escribir el archivo: un formulario
           incompleto no debe dejar basura en el disco. */
        $datos = $request->validate([
            'comprobante' => ['required', 'file', 'mimes:pdf', 'max:1024'],
            'monto_pagado' => ['required', 'numeric', 'min:0.01', 'max:999999', 'decimal:0,2'],
            'fecha_pago' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'hora_pago' => ['required', 'date_format:H:i'],
        ], [
            'comprobante.required' => 'Se requiere un comprobante de pago.',
            'comprobante.mimes' => 'El comprobante debe ser un archivo PDF.',
            'comprobante.max' => 'El comprobante no debe exceder los 1024 KB.',
            'monto_pagado.required' => 'Indica el monto que pagaste.',
            'monto_pagado.numeric' => 'El monto pagado debe ser una cantidad.',
            'monto_pagado.min' => 'El monto pagado debe ser mayor que cero.',
            'monto_pagado.max' => 'El monto pagado excede el máximo permitido.',
            'monto_pagado.decimal' => 'El monto pagado admite como máximo dos decimales.',
            'fecha_pago.required' => 'Indica la fecha en que realizaste el pago.',
            'fecha_pago.date_format' => 'La fecha de pago no tiene un formato válido.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser posterior a hoy.',
            'hora_pago.required' => 'Indica la hora en que realizaste el pago.',
            'hora_pago.date_format' => 'La hora de pago no tiene un formato válido.',
        ]);

        $ruta = 'solicitudes/'.$avance->idSolicitud().'/'.Str::uuid().'.pdf';
        $disco = Storage::disk('comprobantes');
        $disco->putFileAs(dirname($ruta), $request->file('comprobante'), basename($ruta));

        try {
            $revision_pagos->registrarComprobanteDePersona((int) Auth::id(), $ruta, [
                'monto_pagado' => $datos['monto_pagado'],
                'fecha_pago' => $datos['fecha_pago'],
                'hora_pago' => $datos['hora_pago'],
            ]);
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

    /**
     * Estado inicial del formulario: la cuota prellenada y lo que la persona
     * había capturado si el servidor rechazó el envío anterior.
     */
    private function vistaFormulario(float $monto_esperado): array
    {
        return [
            'montoPagado' => old('monto_pagado', number_format($monto_esperado, 2, '.', '')),
            'fechaPago' => old('fecha_pago', ''),
            'horaPago' => old('hora_pago', ''),
            'maxFecha' => now()->toDateString(),
        ];
    }

    /**
     * Lo que la persona tiene que pagar.
     *
     * Sale del catálogo de referencias y no de PAGO_MONTO_PAGADO, porque esa
     * columna guarda lo que la persona declaró haber pagado desde que captura
     * su pago en esta misma pantalla.
     */
    private function montoEsperado(AvancePersona $avance, CatalogoReferencias $catalogo): float
    {
        $referencia = $avance->tienePago()
            ? $catalogo->referenciaDePersona((int) Auth::id())
            : null;

        return (float) ($referencia['monto'] ?? config('suif.cuota_recuperacion', 7000));
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
