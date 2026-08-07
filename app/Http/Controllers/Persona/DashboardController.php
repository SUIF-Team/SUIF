<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use App\Servicios\AvancePersona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * DashboardController
 *
 * Responsabilidad: mostrar el avance del trámite de la persona.
 * El pre-registro y la documentación salen de la base de datos; el pago,
 * la sede, los resultados y el certificado todavía vienen de la sesión.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $usuario = Auth::user();
        $persona_modelo = $usuario ? $usuario->persona : null;
        $avance = new AvancePersona($usuario ? $usuario->usua_id_usuario : null);

        $persona = [
            'nombre' => $persona_modelo ? $persona_modelo->nombreCompleto() : 'Persona',
            'identificador' => $persona_modelo ? 'CURP '.$persona_modelo->pers_curp : '',
        ];

        $sesion = (array) $request->session()->get('suif.persona.estado', []);

        /* Nada del trámite avanza mientras el administrador no apruebe. */
        if (!$avance->solicitudAprobada()) {
            $sesion = $this->normalizarEstado([]);
        } else {
            $sesion['referencia_generada'] = $avance->tienePago();
            $sesion['pago_estado'] = $avance->estadoPagoVista();
            $sesion = $this->normalizarEstado($sesion);
        }

        /* La sede ya es persistente y no depende del estado de la sesión. */
        $sesion['sede_seleccionada'] = $avance->tieneSedeSeleccionada();

        $pasos = $this->construirPasos($avance, $sesion);
        $tramite = $this->estadoGeneral($avance, $sesion);

        return view('persona.dashboard', compact('persona', 'pasos', 'tramite'));
    }

    private function construirPasos(AvancePersona $avance, array $sesion)
    {
        $cuota = number_format((float) config('suif.cuota_recuperacion', 7000), 2, '.', ',');
        $moneda = config('suif.moneda', 'MXN');

        $capturado = $avance->tieneSolicitud();
        $documentacion = $avance->documentacionEstado();
        $aprobada = $avance->solicitudAprobada();
        $rechazada = $avance->estadoSolicitud() === 'Rechazada';

        $pasos = [];

        $pasos[] = $this->paso(1, 'Pre-registro',
            $capturado
                ? 'Tus datos personales quedaron registrados.'
                : 'Captura tus datos personales para iniciar tu trámite.',
            $capturado ? 'completed' : 'in-progress',
            'persona.preregistro.index');

        $pasos[] = $this->pasoDocumentacion($capturado, $documentacion);

        $pasos[] = $this->pasoReferencia($aprobada, $rechazada, !empty($sesion['referencia_generada']), $cuota, $moneda);

        $pasos[] = $this->pasoPago(!empty($sesion['referencia_generada']), $sesion['pago_estado']);

        $pasos[] = $this->pasoSede($sesion['pago_estado'], !empty($sesion['sede_seleccionada']));

        /* SUIF no aplica exámenes; solo consulta resultados publicados. */
        $pasos[] = $this->pasoResultados(!empty($sesion['sede_seleccionada']), !empty($sesion['resultado_publicado']));

        $pasos[] = $this->paso(7, 'Certificado',
            !empty($sesion['certificado_disponible'])
                ? 'Tu certificado está disponible para consulta y descarga.'
                : 'Disponible cuando el administrador emita tu certificado.',
            !empty($sesion['certificado_disponible']) ? 'completed' : 'pending',
            'persona.certificado');

        return $pasos;
    }

    private function pasoDocumentacion($capturado, $documentacion)
    {
        if (!$capturado) {
            return $this->paso(2, 'Documentación',
                'Disponible después de capturar tus datos personales.',
                'pending', 'persona.documentos.index');
        }

        $mensajes = [
            'pendiente' => ['Descarga los formatos, llénalos a mano y adjúntalos.', 'in-progress'],
            'en_proceso' => ['Te faltan documentos por adjuntar.', 'in-progress'],
            'revision' => ['Tus documentos están siendo revisados por el equipo administrativo.', 'review'],
            'rechazado' => ['Uno o más documentos tienen observaciones. Corrígelos y vuelve a enviarlos.', 'rejected'],
            'aprobado' => ['Todos tus documentos fueron aprobados.', 'completed'],
        ];

        $info = isset($mensajes[$documentacion]) ? $mensajes[$documentacion] : $mensajes['pendiente'];

        return $this->paso(2, 'Documentación', $info[0], $info[1], 'persona.documentos.index');
    }

    private function pasoReferencia($aprobada, $rechazada, $referenciaGenerada, $cuota, $moneda)
    {
        if ($rechazada) {
            return $this->paso(3, 'Obtener referencia',
                'Tu solicitud fue rechazada. Revisa el motivo en la etapa de documentación.',
                'rejected', 'persona.referencia.index');
        }

        if (!$aprobada) {
            return $this->paso(3, 'Obtener referencia',
                'Disponible cuando el equipo administrativo apruebe tu solicitud.',
                'pending', 'persona.referencia.index');
        }

        return $this->paso(3, 'Obtener referencia',
            $referenciaGenerada
                ? 'Referencia de pago generada ($'.$cuota.' '.$moneda.').'
                : 'Genera tu referencia de pago por la cantidad de $'.$cuota.' '.$moneda.'.',
            $referenciaGenerada ? 'completed' : 'in-progress',
            'persona.referencia.index');
    }

    private function pasoPago($referenciaGenerada, $pago)
    {
        if (!$referenciaGenerada) {
            return $this->paso(4, 'Pago', 'Disponible después de generar tu referencia.', 'pending', 'persona.pago.index');
        }

        if ($pago === 'validado') {
            return $this->paso(4, 'Pago', 'Tu comprobante fue validado por el equipo administrativo.', 'completed', 'persona.pago.index');
        }

        if ($pago === 'revision') {
            return $this->paso(4, 'Pago', 'Tu comprobante fue enviado y está siendo validado.', 'review', 'persona.pago.index');
        }

        if ($pago === 'rechazado') {
            return $this->paso(4, 'Pago', 'El comprobante tiene observaciones. Corrígelo y vuelve a cargarlo.', 'rejected', 'persona.pago.index');
        }

        return $this->paso(4, 'Pago', 'Carga tu comprobante para que el equipo administrativo valide el pago.', 'in-progress', 'persona.pago.index');
    }

    private function pasoSede($pago, $sede)
    {
        if ($pago === 'revision') {
            return $this->paso(5, 'Selección de sede y horario',
                'Se habilitará cuando termine la validación de tu pago.',
                'pending', 'persona.sede.index');
        }

        if ($pago !== 'validado') {
            return $this->paso(5, 'Selección de sede y horario',
                'Disponible cuando el equipo administrativo valide tu pago.',
                'pending', 'persona.sede.index');
        }

        return $this->paso(5, 'Selección de sede y horario',
            $sede ? 'Tu sede y horario quedaron registrados.' : 'Selecciona una sede y un horario disponible.',
            $sede ? 'completed' : 'in-progress',
            'persona.sede.index');
    }

    private function pasoResultados($sede, $resultado)
    {
        if (!$sede) {
            return $this->paso(6, 'Resultados',
                'Disponible después de realizar tu evaluación en la sede seleccionada.',
                'pending', 'persona.resultados');
        }

        if (!$resultado) {
            return $this->paso(6, 'Resultados',
                'Espera la publicación de tu resultado.',
                'pending', 'persona.resultados');
        }

        return $this->paso(6, 'Resultados',
            'Tu resultado ya fue publicado y está disponible para consulta.',
            'completed', 'persona.resultados');
    }

    private function paso($numero, $titulo, $descripcion, $estado, $ruta)
    {
        $etiqueta = $this->etiquetaEstado($estado);

        /* Las etapas cerradas o en revisión se consultan; las accionables se continúan. */
        $mostrarBoton = in_array($estado, ['completed', 'review', 'in-progress', 'rejected'], true);
        $textoBoton = in_array($estado, ['completed', 'review'], true) ? 'Ver' : 'Continuar';
        return compact('numero', 'titulo', 'descripcion', 'estado', 'etiqueta', 'ruta', 'mostrarBoton', 'textoBoton');
    }

    private function estadoGeneral(AvancePersona $avance, array $sesion)
    {
        if ($avance->estadoSolicitud() === 'Rechazada') {
            return $this->presentacionEstado('rejected');
        }

        if (!empty($sesion['certificado_disponible'])) {
            return $this->presentacionEstado('completed');
        }

        if ($avance->documentacionEstado() === 'rechazado' || $sesion['pago_estado'] === 'rechazado') {
            return $this->presentacionEstado('rejected');
        }

        if ($avance->documentacionEstado() === 'revision'
            || $sesion['pago_estado'] === 'revision') {
            return $this->presentacionEstado('review');
        }

        if (!empty($sesion['sede_seleccionada'])) {
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
            'review' => 'En revisión',
            'in-progress' => 'En proceso',
            'rejected' => 'Rechazado',
        ];

        return isset($etiquetas[$estado]) ? $etiquetas[$estado] : $etiquetas['pending'];
    }

    private function normalizarEstado(array $estado)
    {
        $estado = array_merge([
            'referencia_generada' => false,
            'pago_estado' => 'sin_cargar',
            'sede_seleccionada' => false,
            'resultado_publicado' => false,
            'certificado_disponible' => false,
        ], $estado);

        $pagosPermitidos = ['sin_cargar', 'revision', 'rechazado', 'validado'];

        $estado['referencia_generada'] = !empty($estado['referencia_generada']);
        $estado['sede_seleccionada'] = !empty($estado['sede_seleccionada']);
        $estado['resultado_publicado'] = !empty($estado['resultado_publicado']);
        $estado['certificado_disponible'] = !empty($estado['certificado_disponible']);

        if (!in_array($estado['pago_estado'], $pagosPermitidos, true)) {
            $estado['pago_estado'] = 'sin_cargar';
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
}
