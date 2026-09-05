<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\AvancePersona;
use App\Servicios\ComprobanteFiscal;
use App\Servicios\GestionClaves;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * FacturacionController
 *
 * Responsabilidad: los datos con los que se emite el CFDI de la
 * certificación. Se llega aquí sólo desde el paso de pago, con el pago
 * validado y con CFDI ya elegido; cualquier otro camino se rechaza.
 */
class FacturacionController extends Controller
{
    public function index(ComprobanteFiscal $comprobante_fiscal, GestionClaves $claves)
    {
        $avance = $this->avanceActual();

        $bloqueo = $this->mensajeBloqueo($avance);

        if ($bloqueo !== null) {
            return redirect()->route('persona.pago.index')->with('warning', $bloqueo);
        }

        /* El CFDI puede ir a un correo distinto del de la cuenta —el de una
           empresa, por ejemplo—, pero lo más común es que sea el mismo: se
           propone y la persona lo cambia si hace falta. */
        $correo_sugerido = $claves->correoPrincipal((int) $avance->idPersona()) ?: '';

        return view('persona.facturacion', [
            'regimenes' => $comprobante_fiscal->regimenesFiscales(),
            'formulario' => [
                'razonSocial' => old('razon_social', ''),
                'personaMoral' => old('persona_moral', '0'),
                'regimenFiscal' => (string) old('regimen_fiscal', ''),
                'codigoPostal' => old('codigo_postal', ''),
                'rfc' => old('rfc', ''),
                'correoCfdi' => old('correo_cfdi', $correo_sugerido),
            ],
        ]);
    }

    public function store(Request $request, ComprobanteFiscal $comprobante_fiscal)
    {
        /* Se normaliza antes de validar para que las reglas de longitud no
           cuenten espacios y el RFC se compare siempre en mayúsculas. */
        $request->merge([
            'razon_social' => trim((string) $request->input('razon_social')),
            'rfc' => mb_strtoupper(trim((string) $request->input('rfc')), 'UTF-8'),
            'codigo_postal' => trim((string) $request->input('codigo_postal')),
            'correo_cfdi' => mb_strtolower(trim((string) $request->input('correo_cfdi')), 'UTF-8'),
        ]);

        /* El RFC no reutiliza la regla del pre-registro: aquélla es sólo de
           persona física. Aquí también se factura a morales, cuyo RFC tiene
           tres letras iniciales y doce caracteres. El & aparece en razones
           sociales. Las longitudes salen de las columnas de DATO_FISCAL. */
        $datos = $request->validate([
            'razon_social' => ['required', 'string', 'max:35'],
            'persona_moral' => ['required', 'in:0,1'],
            'regimen_fiscal' => ['required', 'integer', 'exists:regimen_fiscal,refi_id_regimen_fiscal'],
            'codigo_postal' => ['required', 'digits:5'],
            'rfc' => [
                'required',
                'string',
                'regex:/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/u',
                $request->input('persona_moral') === '1' ? 'size:12' : 'size:13',
            ],
            'correo_cfdi' => ['required', 'email', 'max:65'],
        ], [
            'razon_social.required' => 'Escribe el nombre o la razón social a la que se emitirá el CFDI.',
            'razon_social.max' => 'El nombre o razón social no debe exceder los 35 caracteres.',
            'persona_moral.required' => 'Indica si facturas como persona moral o como persona física.',
            'persona_moral.in' => 'Indica si facturas como persona moral o como persona física.',
            'regimen_fiscal.required' => 'Selecciona tu régimen fiscal.',
            'regimen_fiscal.integer' => 'Selecciona un régimen fiscal válido.',
            'regimen_fiscal.exists' => 'El régimen fiscal seleccionado no existe.',
            'codigo_postal.required' => 'Escribe el código postal de tu domicilio fiscal.',
            'codigo_postal.digits' => 'El código postal debe tener exactamente 5 dígitos.',
            'rfc.required' => 'Escribe el RFC al que se emitirá el CFDI.',
            'rfc.size' => 'El RFC de una persona moral tiene 12 caracteres y el de una persona física 13.',
            'rfc.regex' => 'Escribe el RFC con homoclave, como aparece en la constancia de situación fiscal.',
            'correo_cfdi.required' => 'Escribe el correo al que enviaremos tu CFDI.',
            'correo_cfdi.email' => 'Escribe un correo válido.',
            'correo_cfdi.max' => 'El correo no debe exceder los 65 caracteres.',
        ]);

        try {
            $comprobante_fiscal->guardarDatosFiscales((int) Auth::id(), $datos);
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
            'Tus datos de facturación quedaron registrados. Tu CFDI se enviará a '.$datos['correo_cfdi'].'.',
            route('persona.pago.index')
        );
    }

    private function avanceActual(): AvancePersona
    {
        return new AvancePersona(Auth::id());
    }

    /**
     * Por qué no se puede capturar. Null significa que sí se puede.
     */
    private function mensajeBloqueo(AvancePersona $avance): ?string
    {
        if ($avance->estadoPagoVista() !== 'validado') {
            return 'Tu pago debe estar validado para capturar tus datos de facturación.';
        }

        if ($avance->comprobanteElegido() !== ComprobanteFiscal::CFDI) {
            return 'Selecciona la opción CFDI en tu pago para capturar tus datos de facturación.';
        }

        if ($avance->tieneDatosFiscales()) {
            return 'Tus datos de facturación ya fueron registrados y no pueden modificarse.';
        }

        return null;
    }
}
