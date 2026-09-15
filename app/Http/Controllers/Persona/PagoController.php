<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\AvancePersona;
use App\Servicios\CatalogoReferencias;
use App\Servicios\ComprobanteFiscal;
use App\Servicios\FormatoPagoDec;
use App\Support\Admin\RevisionPagos;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PagoController extends Controller
{
    public function index(CatalogoReferencias $catalogo, FormatoPagoDec $formato_pago)
    {
        $avance = $this->avanceActual();
        $pago_estado = $avance->estadoPagoVista();
        $puede_cargar = $avance->solicitudAprobada()
            && $avance->referenciaAsignada()
            && in_array($pago_estado, ['sin_cargar', 'rechazado'], true);

        $monto_esperado = $this->montoEsperado($avance, $catalogo);

        return view('persona.pago', [
            'pagoEstado' => $pago_estado,
            'puedeCargar' => $puede_cargar,
            'mensajeBloqueo' => $this->mensajeBloqueo($avance, $pago_estado),
            'motivoRechazo' => $avance->motivoRechazoPago(),
            'cuota' => number_format($monto_esperado, 2, '.', ','),
            /* Sólo se arma con el formulario a la vista: trae los catálogos de
               la forma de pago, que en las demás pantallas no hacen falta. */
            'vistaFormulario' => $puede_cargar
                ? $this->vistaFormulario($monto_esperado, $avance, $formato_pago)
                : [],
            'moneda' => config('suif.moneda', 'MXN'),
            'tracker' => $this->tracker($pago_estado),
            'comprobanteFiscal' => $this->comprobanteFiscalVista($avance, $pago_estado),
        ]);
    }

    public function subirComprobante(Request $request, RevisionPagos $revision_pagos, FormatoPagoDec $formato_pago)
    {
        $avance = $this->avanceActual();
        $pago_estado = $avance->estadoPagoVista();

        if (!$avance->solicitudAprobada() || !$avance->referenciaAsignada()
            || !in_array($pago_estado, ['sin_cargar', 'rechazado'], true)) {
            return $this->responder(
                $request,
                'warning',
                $this->mensajeBloqueo($avance, $pago_estado),
                route('persona.pago.index')
            );
        }

        /* La forma de pago y el comprobante que se pide van al formato con el
           que la DEC emite el CFDI o el ticket: se piden aquí para que ya
           estén cuando valide el pago. El banco, sólo con tarjeta. La elección
           del comprobante, sólo si todavía no hay una: la referencia especial
           nace con CFDI y al subsanar ya quedó guardada. */
        $eleccion = $avance->comprobanteElegido();
        $metodos = $formato_pago->metodosPago();
        $con_tarjeta = array_column(
            array_filter($metodos, fn (array $metodo): bool => $metodo['conTarjeta']),
            'id'
        );

        /* La validación corre antes de escribir el archivo: un formulario
           incompleto no debe dejar basura en el disco. */
        $datos = $request->validate([
            'comprobante' => ['required', 'file', 'mimes:pdf', 'max:1024'],
            'monto_pagado' => ['required', 'numeric', 'min:0.01', 'max:999999', 'decimal:0,2'],
            'fecha_pago' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'hora_pago' => ['required', 'date_format:H:i'],
            'metodo_pago' => ['required', 'integer', Rule::in(array_column($metodos, 'id'))],
            'banco' => [
                Rule::requiredIf(in_array((int) $request->input('metodo_pago'), $con_tarjeta, true)),
                'nullable',
                'integer',
                'exists:banco,banc_id_banco',
            ],
            'comprobante_fiscal' => [
                Rule::requiredIf($eleccion === null),
                'nullable',
                Rule::in([ComprobanteFiscal::TICKET, ComprobanteFiscal::CFDI]),
            ],
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
            'metodo_pago.required' => 'Indica cómo pagaste.',
            'metodo_pago.integer' => 'Indica cómo pagaste.',
            'metodo_pago.in' => 'Indica cómo pagaste.',
            'banco.required' => 'Selecciona el banco de tu tarjeta.',
            'banco.integer' => 'Selecciona un banco de la lista.',
            'banco.exists' => 'Selecciona un banco de la lista.',
            'comprobante_fiscal.required' => 'Indica si necesitas ticket o CFDI.',
            'comprobante_fiscal.in' => 'Indica si necesitas ticket o CFDI.',
        ]);

        $ruta = 'solicitudes/'.$avance->idSolicitud().'/'.Str::uuid().'.pdf';
        $disco = Storage::disk('comprobantes');
        $disco->putFileAs(dirname($ruta), $request->file('comprobante'), basename($ruta));

        try {
            $revision_pagos->registrarComprobanteDePersona((int) Auth::id(), $ruta, [
                'monto_pagado' => $datos['monto_pagado'],
                'fecha_pago' => $datos['fecha_pago'],
                'hora_pago' => $datos['hora_pago'],
                'metodo_pago' => (int) $datos['metodo_pago'],
                'banco' => in_array((int) $datos['metodo_pago'], $con_tarjeta, true) ? (int) $datos['banco'] : null,
                'uso_cfdi' => $eleccion === null ? $datos['comprobante_fiscal'] === ComprobanteFiscal::CFDI : null,
            ]);
        } catch (DomainException $exception) {
            $disco->delete($ruta);

            return $this->responder(
                $request,
                'warning',
                $exception->getMessage(),
                route('persona.pago.index')
            );
        }

        /* Con CFDI y sin datos de facturación, lo que sigue es capturarlos:
           así la DEC los tiene cuando valide el pago. */
        if (($eleccion ?? $datos['comprobante_fiscal']) === ComprobanteFiscal::CFDI && !$avance->tieneDatosFiscales()) {
            return $this->responder(
                $request,
                'success',
                'Tu comprobante fue enviado. Ahora captura los datos con los que se emitirá tu CFDI.',
                route('persona.facturacion.index')
            );
        }

        return $this->responder(
            $request,
            'success',
            'Tu comprobante fue enviado. El proceso de revisión puede tardar hasta 24 horas.',
            route('persona.pago.index')
        );
    }

    /**
     * El comprobante que la persona quiere de su pago, elegido después de la
     * validación. Hoy se elige al subir el comprobante; esta ruta queda para
     * los pagos validados antes de ese cambio. La elección es definitiva.
     */
    public function elegirComprobante(Request $request, ComprobanteFiscal $comprobante_fiscal)
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:ticket,cfdi'],
        ], [
            'tipo.required' => 'Selecciona si quieres ticket o CFDI.',
            'tipo.in' => 'Selecciona si quieres ticket o CFDI.',
        ]);

        try {
            $comprobante_fiscal->registrarEleccion((int) Auth::id(), $datos['tipo']);
        } catch (DomainException $exception) {
            return $this->responder(
                $request,
                'warning',
                $exception->getMessage(),
                route('persona.pago.index')
            );
        }

        return $this->responder(
            $request,
            'success',
            $datos['tipo'] === ComprobanteFiscal::CFDI
            ? 'Elegiste CFDI. Captura tus datos de facturación para que podamos emitirlo y enviártelo por correo electrónico.'
            : 'Elegiste ticket. Te lo haremos llegar por correo electrónico.',
            route('persona.pago.index')
        );
    }

    private function avanceActual(): AvancePersona
    {
        return new AvancePersona(Auth::id());
    }

    /**
     * Estado inicial del formulario: la cuota prellenada, los catálogos de la
     * forma de pago y lo que la persona había capturado si el servidor rechazó
     * el envío anterior. Va en un solo arreglo porque la vista lo pasa entero
     * a @json.
     */
    private function vistaFormulario(float $monto_esperado, AvancePersona $avance, FormatoPagoDec $formato_pago): array
    {
        return [
            'montoPagado' => old('monto_pagado', number_format($monto_esperado, 2, '.', '')),
            'fechaPago' => old('fecha_pago', ''),
            'horaPago' => old('hora_pago', ''),
            'maxFecha' => now()->toDateString(),
            'metodosPago' => $formato_pago->metodosPago(),
            'bancos' => $formato_pago->bancos(),
            'metodoPago' => (string) old('metodo_pago', ''),
            'banco' => (string) old('banco', ''),
            'comprobanteFiscal' => (string) old('comprobante_fiscal', ''),
            /* Ya elegido, el comprobante se muestra fijo y no se vuelve a pedir. */
            'eleccion' => $avance->comprobanteElegido(),
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

        if (!$avance->referenciaAsignada()) {
            /* El pago compartido existe desde que la empresa capturó a sus
               participantes; el número lo emite la DEC después. */
            return $avance->tienePago()
                ? 'Tu referencia especial todavía no ha sido emitida. Te avisaremos por correo en cuanto esté lista.'
                : 'Aún no existe una referencia de pago ligada a tu solicitud.';
        }

        if ($pago_estado === 'revision') {
            return 'Tu comprobante ya está en revisión y no puede reemplazarse por ahora.';
        }

        if ($pago_estado === 'validado') {
            return 'Tu comprobante ya fue aprobado y no puede reemplazarse.';
        }

        return null;
    }

    /**
     * Estado del bloque de comprobante. Con la elección hecha —hoy se hace al
     * subir el comprobante— muestra cuál fue y, si es CFDI sin datos, el
     * enlace para capturarlos, también durante la revisión. El selector sólo
     * aparece en pagos validados sin elección, anteriores a ese cambio.
     */
    private function comprobanteFiscalVista(AvancePersona $avance, string $pago_estado): array
    {
        $eleccion = $avance->comprobanteElegido();

        return [
            'visible' => $pago_estado === 'validado' || $eleccion !== null,
            /* Elegido ya no hay nada que confirmar: sin esto la pantalla
               descargaría Vue para no hacer nada. */
            'puedeElegir' => $pago_estado === 'validado' && $eleccion === null,
            'eleccion' => $eleccion,
            'tieneDatosFiscales' => $avance->tieneDatosFiscales(),
            'urlFormulario' => route('persona.facturacion.index'),
        ];
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
