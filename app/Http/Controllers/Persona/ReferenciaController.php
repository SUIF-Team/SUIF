<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\AvancePersona;
use App\Servicios\CatalogoReferencias;
use App\Servicios\ComprobanteFiscal;
use App\Servicios\ReferenciaEspecial;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ReferenciaController
 *
 * Migrado desde: app/controllers/ReferenciaController.php
 * Responsabilidad: entrega y consulta de la referencia bancaria de pago.
 *
 * El paso arranca en un selector —index()— que explica los dos caminos de pago
 * y encamina a la persona. El individual entrega una referencia propia del
 * catálogo; el especial —especial()— captura al pagador y a sus participantes
 * para que la DEC emita una sola referencia por el total del grupo.
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
           necesita su número y su formato, no volver a elegir camino. Lo mismo
           si el pago compartido ya existe y sólo falta que la DEC lo emita:
           elegir de nuevo no la llevaría a ningún lado. */
        if ($avance->tienePago()) {
            return redirect()->route('persona.referencia.individual');
        }

        return view('persona.referencia-seleccion', [
            'solicitudAprobada' => $avance->solicitudAprobada(),
        ]);
    }

    /**
     * Camino individual: obtener la referencia propia, consultarla y bajar el PDF.
     *
     * También es la pantalla donde termina el camino especial: una vez que hay
     * pago, lo que la persona necesita ver es su referencia —o el aviso de que
     * todavía se está emitiendo—, venga del camino que venga.
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
     * Camino especial: datos del tercero que paga y de las personas que cubre.
     */
    public function especial(ComprobanteFiscal $comprobante_fiscal)
    {
        $avance = new AvancePersona(Auth::id());

        if (!$avance->solicitudAprobada()) {
            return redirect()->route('persona.referencia.index');
        }

        /* Con pago ya ligado no hay nada que capturar: o tiene su referencia o
           está esperando a que la DEC la emita, y las dos cosas se ven allá. */
        if ($avance->tienePago()) {
            return redirect()->route('persona.referencia.individual');
        }

        return view('persona.referencia-especial', [
            'regimenes' => $comprobante_fiscal->regimenesFiscales(),
            'vista' => [
                'pagador' => [
                    'razonSocial' => old('razon_social', ''),
                    'personaMoral' => (string) old('persona_moral', '1'),
                    'regimenFiscal' => (string) old('regimen_fiscal', ''),
                    'codigoPostal' => old('codigo_postal', ''),
                    'rfc' => old('rfc', ''),
                ],
                'participantes' => $this->participantesCapturados($avance),
                'minimo' => ReferenciaEspecial::MINIMO_PARTICIPANTES,
                'maximo' => ReferenciaEspecial::MAXIMO_PARTICIPANTES,
                'cuota' => (float) config('suif.cuota_recuperacion', 7000),
                'moneda' => config('suif.moneda', 'MXN'),
            ],
        ]);
    }

    /**
     * Registra la solicitud de referencia especial. La emisión es de la DEC.
     */
    public function solicitarEspecial(Request $request, ReferenciaEspecial $referencia_especial): RedirectResponse
    {
        $request->merge([
            'razon_social' => trim((string) $request->input('razon_social')),
            'rfc' => mb_strtoupper(trim((string) $request->input('rfc')), 'UTF-8'),
            'codigo_postal' => trim((string) $request->input('codigo_postal')),
        ]);

        /* Mismas reglas que persona/facturacion: es el mismo renglón de
           DATO_FISCAL y las longitudes salen de sus columnas. */
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
            'participantes' => [
                'required',
                'array',
                'min:'.ReferenciaEspecial::MINIMO_PARTICIPANTES,
                'max:'.ReferenciaEspecial::MAXIMO_PARTICIPANTES,
            ],
            'participantes.*.curp' => ['required', 'string', 'size:18', 'regex:/^[A-Za-z0-9]{18}$/'],
            'participantes.*.nombre' => ['required', 'string', 'max:45'],
            'participantes.*.primer_apellido' => ['required', 'string', 'max:45'],
            'participantes.*.segundo_apellido' => ['required', 'string', 'max:45'],
        ], [
            'razon_social.required' => 'Escribe el nombre o la razón social de quien realizará el pago.',
            'razon_social.max' => 'El nombre o razón social no debe exceder los 35 caracteres.',
            'persona_moral.required' => 'Indica si quien paga es persona moral o persona física.',
            'persona_moral.in' => 'Indica si quien paga es persona moral o persona física.',
            'regimen_fiscal.required' => 'Selecciona el régimen fiscal de quien paga.',
            'regimen_fiscal.integer' => 'Selecciona un régimen fiscal válido.',
            'regimen_fiscal.exists' => 'El régimen fiscal seleccionado no existe.',
            'codigo_postal.required' => 'Escribe el código postal del domicilio fiscal de quien paga.',
            'codigo_postal.digits' => 'El código postal debe tener exactamente 5 dígitos.',
            'rfc.required' => 'Escribe el RFC de quien realizará el pago.',
            'rfc.size' => 'El RFC de una persona moral tiene 12 caracteres y el de una persona física 13.',
            'rfc.regex' => 'Escribe el RFC con homoclave, como aparece en la constancia de situación fiscal.',
            'participantes.required' => 'Captura a las personas a las que se les pagará la certificación.',
            'participantes.min' => 'La referencia especial cubre al menos :min participantes.',
            'participantes.max' => 'La referencia especial admite como máximo :max participantes.',
            'participantes.*.curp.required' => 'Falta la CURP de un participante.',
            'participantes.*.curp.size' => 'La CURP debe tener 18 caracteres.',
            'participantes.*.curp.regex' => 'La CURP sólo puede contener letras y números.',
            'participantes.*.nombre.required' => 'Falta el nombre de un participante.',
            'participantes.*.primer_apellido.required' => 'Falta el primer apellido de un participante.',
            'participantes.*.segundo_apellido.required' => 'Falta el segundo apellido de un participante.',
        ]);

        try {
            $resultado = $referencia_especial->solicitar(
                (int) Auth::id(),
                $datos,
                array_values($datos['participantes'])
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('persona.referencia.especial')
                ->withInput()
                ->withErrors(['participantes' => $exception->getMessage()]);
        }

        return redirect()->route('persona.referencia.individual')->with(
            'success',
            'Registramos tu solicitud para '.$resultado['participantes'].' participantes. '
            .'La Dirección emitirá la referencia y te avisaremos por correo en cuanto esté lista.'
        );
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

    /**
     * Lo que se pinta en la lista de participantes: lo que la persona había
     * capturado si el servidor rechazó el envío, y si no, ella misma. Quien
     * pide la referencia siempre queda incluida, así que su renglón no se
     * escribe a mano.
     *
     * @return array<int, array<string, string>>
     */
    private function participantesCapturados(AvancePersona $avance): array
    {
        $capturados = old('participantes');

        if (is_array($capturados) && $capturados !== []) {
            return array_values(array_map(fn ($fila): array => [
                'curp' => (string) ($fila['curp'] ?? ''),
                'nombre' => (string) ($fila['nombre'] ?? ''),
                'primer_apellido' => (string) ($fila['primer_apellido'] ?? ''),
                'segundo_apellido' => (string) ($fila['segundo_apellido'] ?? ''),
            ], $capturados));
        }

        $persona = DB::table('persona')
            ->where('pers_id_persona', $avance->idPersona())
            ->select('pers_curp', 'pers_nombre', 'pers_apellido_paterno', 'pers_apellido_materno')
            ->first();

        return [[
            'curp' => (string) ($persona->pers_curp ?? ''),
            'nombre' => (string) ($persona->pers_nombre ?? ''),
            'primer_apellido' => (string) ($persona->pers_apellido_paterno ?? ''),
            'segundo_apellido' => (string) ($persona->pers_apellido_materno ?? ''),
        ]];
    }
}
