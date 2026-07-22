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

        /* Sustituir el estado de sesión por modelos cuando se defina la persistencia. */
        $estado = $this->normalizarEstado(
            (array) $request->session()->get('suif.participante.estado', [])
        );

        return $this->mostrarDashboard($participante, $estado);
    }

    /**
     * Permite revisar los estados visuales sin autenticación únicamente en local.
     */
    public function demo($escenario = 'inicio')
    {
        if (!app()->environment('local')) {
            abort(404);
        }

        $escenarios = $this->escenariosDemo();

        if (!array_key_exists($escenario, $escenarios)) {
            abort(404);
        }

        $participante = [
            'nombre' => 'Juana García Martínez',
            'folio' => 'FCA-2026-01842',
        ];

        return $this->mostrarDashboard(
            $participante,
            $this->normalizarEstado($escenarios[$escenario]),
            true
        );
    }

    private function mostrarDashboard(array $participante, array $estado, $modoDemo = false)
    {
        $pasos = $this->construirPasos($estado);
        $tramite = $this->estadoGeneral($estado);

        return view('participante.dashboard', compact(
            'participante',
            'estado',
            'pasos',
            'tramite',
            'modoDemo'
        ));
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
            $pre ? 'completed' : 'in-progress',
            $pre ? 'Revisar' : 'Continuar', 'participante.preregistro.create', true);

        $pasos[] = $this->paso(2, 'Obtener referencia',
            'Genera tu referencia de pago por $'.$cuota.' '.config('suif.moneda', 'MXN').'.',
            !$pre ? 'pending' : ($ref ? 'completed' : 'in-progress'),
            $ref ? 'Consultar' : 'Generar', 'participante.referencia.index', $pre);

        $pasos[] = $this->pasoPago($ref, $pago);

        $pasos[] = $this->pasoSede($pago, $pagoValidado, $sede);

        /* SUIF no administra ni aplica exámenes; solo consulta resultados publicados. */
        $pasos[] = $this->pasoResultados($sede, $resultado);

        $pasos[] = $this->paso(6, 'Certificado',
            $certificado ? 'Tu certificado está disponible para consulta y descarga.' : 'Disponible cuando el administrador emita tu certificado.',
            $certificado ? 'completed' : 'pending',
            'Descargar', 'participante.certificado', $certificado);

        return $pasos;
    }

    private function pasoPago($referenciaGenerada, $pago)
    {
        if (!$referenciaGenerada) {
            return $this->paso(3, 'Pago', 'Disponible después de generar tu referencia.', 'pending', 'Continuar', 'participante.pago.index', false);
        }
        if ($pago === 'validado') {
            return $this->paso(3, 'Pago', 'Tu comprobante fue validado por el equipo administrativo.', 'completed', 'Consultar', 'participante.pago.index', true);
        }
        if ($pago === 'revision') {
            return $this->paso(3, 'Pago', 'Tu comprobante fue enviado y está siendo validado.', 'completed', 'Consultar', 'participante.pago.index', true);
        }
        if ($pago === 'rechazado') {
            return $this->paso(3, 'Pago', 'El comprobante tiene observaciones. Corrígelo y vuelve a cargarlo.', 'rejected', 'Corregir', 'participante.pago.index', true);
        }
        return $this->paso(3, 'Pago', 'Carga tu comprobante para que el equipo administrativo valide el pago.', 'in-progress', 'Continuar', 'participante.pago.index', true);
    }

    private function pasoSede($pago, $pagoValidado, $sede)
    {
        if ($pago === 'revision') {
            return $this->paso(4, 'Selección de sede y horario',
                'Se habilitará cuando termine la validación de tu pago.',
                'pending', 'Seleccionar', 'participante.sede.index', false);
        }

        if (!$pagoValidado) {
            return $this->paso(4, 'Selección de sede y horario',
                'Disponible cuando el equipo administrativo valide tu pago.',
                'pending', 'Seleccionar', 'participante.sede.index', false);
        }

        return $this->paso(4, 'Selección de sede y horario',
            $sede ? 'Tu sede y horario quedaron registrados.' : 'Selecciona una sede y un horario disponible.',
            $sede ? 'completed' : 'in-progress',
            $sede ? 'Consultar' : 'Seleccionar', 'participante.sede.index', true);
    }

    private function pasoResultados($sede, $resultado)
    {
        if (!$sede) {
            return $this->paso(5, 'Resultados',
                'Disponible después de seleccionar tu sede y horario.',
                'pending', 'Consultar', 'participante.resultados', false);
        }

        if (!$resultado) {
            return $this->paso(5, 'Resultados',
                'Tu selección quedó registrada. Espera la publicación de tu resultado.',
                'pending', 'Consultar', 'participante.resultados', false);
        }

        return $this->paso(5, 'Resultados',
            'Tu resultado ya fue publicado y está disponible para consulta.',
            'completed',
            'Consultar', 'participante.resultados', true);
    }

    private function paso($numero, $titulo, $descripcion, $estado, $accion, $ruta, $habilitado)
    {
        $etiqueta = $this->etiquetaEstado($estado);

        return compact('numero', 'titulo', 'descripcion', 'estado', 'etiqueta', 'accion', 'ruta', 'habilitado');
    }

    private function estadoGeneral(array $estado)
    {
        if (!empty($estado['certificado_disponible'])) {
            return $this->presentacionEstado('completed');
        }

        if ($estado['pago_estado'] === 'rechazado') {
            return $this->presentacionEstado('rejected');
        }

        if (!empty($estado['resultado_publicado'])) {
            return $this->presentacionEstado('pending');
        }

        if (!empty($estado['sede_seleccionada'])) {
            return $this->presentacionEstado('pending');
        }

        if ($estado['pago_estado'] === 'revision') {
            return $this->presentacionEstado('pending');
        }

        return $this->presentacionEstado('in-progress');
    }

    private function presentacionEstado($estado)
    {
        return [
            'texto' => $this->etiquetaEstado($estado),
            'clase' => $estado,
        ];
    }

    private function etiquetaEstado($estado)
    {
        $etiquetas = [
            'completed' => 'Completado',
            'pending' => 'Pendiente',
            'in-progress' => 'En proceso',
            'rejected' => 'Rechazado',
        ];

        return isset($etiquetas[$estado]) ? $etiquetas[$estado] : $etiquetas['pending'];
    }

    private function normalizarEstado(array $estado)
    {
        $estado = array_merge($this->estadoBase(), $estado);
        $pagosPermitidos = ['sin_cargar', 'revision', 'rechazado', 'validado'];

        $estado['preregistro_completo'] = !empty($estado['preregistro_completo']);
        $estado['referencia_generada'] = !empty($estado['referencia_generada']);
        $estado['sede_seleccionada'] = !empty($estado['sede_seleccionada']);
        $estado['resultado_publicado'] = !empty($estado['resultado_publicado']);
        $estado['certificado_disponible'] = !empty($estado['certificado_disponible']);

        if (!in_array($estado['pago_estado'], $pagosPermitidos, true)) {
            $estado['pago_estado'] = 'sin_cargar';
        }

        if (!$estado['preregistro_completo']) {
            $estado['referencia_generada'] = false;
        }

        if (!$estado['referencia_generada']) {
            $estado['pago_estado'] = 'sin_cargar';
        }

        if ($estado['pago_estado'] !== 'validado') {
            $estado['sede_seleccionada'] = false;
        }

        if (!$estado['sede_seleccionada']) {
            $estado['resultado_publicado'] = false;
        }

        if (!$estado['resultado_publicado']) {
            $estado['certificado_disponible'] = false;
        }

        return $estado;
    }

    private function estadoBase()
    {
        return [
            'preregistro_completo' => false,
            'referencia_generada' => false,
            'pago_estado' => 'sin_cargar',
            'sede_seleccionada' => false,
            'resultado_publicado' => false,
            'certificado_disponible' => false,
        ];
    }

    private function escenariosDemo()
    {
        return [
            'inicio' => [],
            'preregistro-completo' => [
                'preregistro_completo' => true,
            ],
            'referencia-generada' => [
                'preregistro_completo' => true,
                'referencia_generada' => true,
            ],
            'validando-pago' => [
                'preregistro_completo' => true,
                'referencia_generada' => true,
                'pago_estado' => 'revision',
            ],
            'pago-validado' => [
                'preregistro_completo' => true,
                'referencia_generada' => true,
                'pago_estado' => 'validado',
            ],
            'sede-seleccionada' => [
                'preregistro_completo' => true,
                'referencia_generada' => true,
                'pago_estado' => 'validado',
                'sede_seleccionada' => true,
            ],
            'resultado-publicado' => [
                'preregistro_completo' => true,
                'referencia_generada' => true,
                'pago_estado' => 'validado',
                'sede_seleccionada' => true,
                'resultado_publicado' => true,
            ],
            'certificado-disponible' => [
                'preregistro_completo' => true,
                'referencia_generada' => true,
                'pago_estado' => 'validado',
                'sede_seleccionada' => true,
                'resultado_publicado' => true,
                'certificado_disponible' => true,
            ],
            'pago-rechazado' => [
                'preregistro_completo' => true,
                'referencia_generada' => true,
                'pago_estado' => 'rechazado',
            ],
        ];
    }
}
