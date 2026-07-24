<?php

namespace App\Http\Controllers\Participante;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $usuario = Auth::user();

        $participante = [
            'nombre' => ($usuario && !empty($usuario->name))
                ? $usuario->name
                : $request->session()->get('suif.participante.nombre', 'Juana García Martínez'),
            'folio' => ($usuario && isset($usuario->folio) && !empty($usuario->folio))
                ? $usuario->folio
                : $request->session()->get('suif.participante.folio', 'FCA-2026-01842'),
        ];

        $estado = array_merge([
            'preregistro_completo' => false,
            'referencia_generada' => false,
            'pago_estado' => 'sin_cargar',
            'sede_seleccionada' => false,
            'resultado_publicado' => false,
            'certificado_disponible' => false,
        ], (array) $request->session()->get('suif.participante.estado', []));

        $pasos = $this->construirPasos($estado);
        $tramite = $this->estadoGeneral($estado);

        return view('participante.dashboard', compact('participante', 'estado', 'pasos', 'tramite'));
    }

    private function construirPasos(array $estado)
    {
        $pre = !empty($estado['preregistro_completo']);
        $ref = !empty($estado['referencia_generada']);
        $pago = isset($estado['pago_estado']) ? $estado['pago_estado'] : 'sin_cargar';
        $pagoValidado = $pago === 'validado';
        $sede = !empty($estado['sede_seleccionada']);
        $resultado = !empty($estado['resultado_publicado']);
        $certificado = !empty($estado['certificado_disponible']);
        $cuota = number_format((float) config('suif.cuota_recuperacion', 7000), 2, '.', ',');

        $pasos = [];
        $pasos[] = $this->paso(1, 'Pre-registro',
            'Completa tus datos personales y carga los documentos requeridos.',
            $pre ? 'completed' : 'active', $pre ? 'Completado' : 'En proceso',
            'Continuar', 'participante.preregistro.index', !$pre);

        $pasos[] = $this->paso(2, 'Obtener referencia',
            'Genera tu referencia de pago por $'.$cuota.' '.config('suif.moneda', 'MXN').'.',
            !$pre ? 'locked' : ($ref ? 'completed' : 'active'),
            !$pre ? 'Pendiente' : ($ref ? 'Generada' : 'Disponible'),
            'Continuar', 'participante.referencia.index', $pre && !$ref);

        $pasos[] = $this->pasoPago($ref, $pago);

        $pasos[] = $this->paso(4, 'Selección de sede y horario',
            $pagoValidado ? 'Selecciona una sede y un horario disponible.' : 'Disponible cuando el administrador valide tu pago.',
            !$pagoValidado ? 'locked' : ($sede ? 'completed' : 'active'),
            !$pagoValidado ? 'Pendiente' : ($sede ? 'Seleccionada' : 'Disponible'),
            'Continuar', 'participante.sede.index', $pagoValidado && !$sede);

        $pasos[] = $this->paso(5, 'Resultados',
            $resultado ? 'Tu resultado ya fue publicado y está disponible para consulta.' : 'Disponible cuando el administrador publique tu resultado.',
            $resultado ? 'available' : 'locked', $resultado ? 'Disponible' : 'Pendiente',
            'Continuar', 'participante.resultados', $resultado);

        $pasos[] = $this->paso(6, 'Certificado',
            $certificado ? 'Tu certificado está disponible para consulta y descarga.' : 'Disponible cuando el administrador emita tu certificado.',
            $certificado ? 'available' : 'locked', $certificado ? 'Disponible' : 'Pendiente',
            'Continuar', 'participante.certificado', $certificado);

        return $pasos;
    }

    private function pasoPago($referenciaGenerada, $pago)
    {
        if (!$referenciaGenerada) {
            return $this->paso(3, 'Pago', 'Disponible después de generar tu referencia.', 'locked', 'Pendiente', 'Continuar', 'participante.pago.index', false);
        }
        if ($pago === 'validado') {
            return $this->paso(3, 'Pago', 'Tu comprobante fue validado por el administrador.', 'completed', 'Validado', 'Continuar', 'participante.pago.index', false);
        }
        if ($pago === 'revision') {
            return $this->paso(3, 'Pago', 'Tu comprobante está siendo revisado.', 'review', 'En revisión', 'Continuar', 'participante.pago.index', true);
        }
        if ($pago === 'rechazado') {
            return $this->paso(3, 'Pago', 'El comprobante tiene observaciones. Corrígelo y vuelve a cargarlo.', 'warning', 'Rechazado', 'Continuar', 'participante.pago.index', true);
        }
        return $this->paso(3, 'Pago', 'Carga tu comprobante para que el administrador valide el pago.', 'active', 'Disponible', 'Continuar', 'participante.pago.index', true);
    }

    private function paso($numero, $titulo, $descripcion, $estado, $etiqueta, $accion, $ruta, $habilitado)
    {
        return compact('numero', 'titulo', 'descripcion', 'estado', 'etiqueta', 'accion', 'ruta', 'habilitado');
    }

    private function estadoGeneral(array $estado)
    {
        if (!empty($estado['certificado_disponible'])) return ['texto' => 'Certificado disponible', 'clase' => 'completed'];
        if (!empty($estado['resultado_publicado'])) return ['texto' => 'Resultado publicado', 'clase' => 'available'];
        if (isset($estado['pago_estado']) && $estado['pago_estado'] === 'rechazado') return ['texto' => 'Corrección requerida', 'clase' => 'warning'];
        if (isset($estado['pago_estado']) && $estado['pago_estado'] === 'revision') return ['texto' => 'Pago en revisión', 'clase' => 'review'];
        return ['texto' => 'Trámite en proceso', 'clase' => 'active'];
    }
}
