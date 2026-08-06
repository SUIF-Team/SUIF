<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * PagoController
 *
 * Migrado desde: app/controllers/PagoController.php
 * Responsabilidad: gestión de pagos y comprobantes de personas.
 */
class PagoController extends Controller
{
    public function index(Request $request)
    {
        $estado = (array) $request->session()->get('suif.persona.estado', []);
        $pagoEstado = isset($estado['pago_estado']) ? $estado['pago_estado'] : 'sin_cargar';

        return view('persona.pago', [
            'pagoEstado' => $pagoEstado,
            'cuota' => number_format((float) config('suif.cuota_recuperacion', 7000), 2, '.', ','),
            'moneda' => config('suif.moneda', 'MXN'),
            'tracker' => $this->tracker($pagoEstado),
        ]);
    }

    public function subirComprobante(Request $request)
    {
        $this->validate($request, [
            'comprobante' => 'required|file|mimes:pdf|max:1024',
        ], [
            'comprobante.required' => 'Se requiere un comprobante de pago.',
            'comprobante.mimes' => 'El comprobante debe ser un archivo PDF.',
            'comprobante.max' => 'El comprobante no debe exceder los 1024 KB.',
        ]);

        $directorio = 'pago/comprobantes/'.sha1($request->session()->getId());
        $nombre = 'comprobante-'.time().'.pdf';
        $request->file('comprobante')->storeAs($directorio, $nombre);

        $request->session()->put('suif.persona.estado.pago_estado', 'revision');

        return redirect()->route('persona.pago.index')
            ->with('success', 'Tu comprobante fue enviado. El proceso de revisión puede tardar hasta 24 horas.');
    }

    public function demo(Request $request, $estadoDemo)
    {
        if (!config('app.debug')) {
            abort(404);
        }

        $permitidos = ['sin_cargar', 'revision', 'validado', 'rechazado'];
        if (!in_array($estadoDemo, $permitidos, true)) {
            abort(404);
        }

        $request->session()->put('suif.persona.estado.pago_estado', $estadoDemo);

        return redirect()->route('persona.pago.index');
    }

    private function tracker($pagoEstado)
    {
        if ($pagoEstado === 'rechazado') {
            return ['pasos' => ['completo', 'completo', 'error'], 'conectores' => ['completo', 'error']];
        }

        if ($pagoEstado === 'validado') {
            return ['pasos' => ['completo', 'completo', 'completo'], 'conectores' => ['completo', 'completo']];
        }

        return ['pasos' => ['completo', 'activo', 'pendiente'], 'conectores' => ['completo', 'pendiente']];
    }
}