<?php

namespace App\Servicios;

use Illuminate\Support\Facades\DB;

/**
 * AvancePersona
 *
 * Responsabilidad: responder en qué punto del trámite va una persona.
 * Consulta la base una sola vez al construirse y es la única fuente de esa
 * verdad: la usan la barra lateral y el panel de la persona.
 */
class AvancePersona
{
    private $idSolicitud = null;
    private $idPago = null;
    private $idEvaluacion = null;
    private $estadoSolicitud = null;
    private $documentos = [];
    private $pago = null;

    public function __construct($idUsuario)
    {
        if (!$idUsuario) {
            return;
        }

        $solicitud = DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->where('p.pers_id_usuario', $idUsuario)
            ->orderByDesc('s.soli_id_solicitud')
            ->select('s.soli_id_solicitud', 's.soli_id_pago', 's.soli_id_evaluacion')
            ->first();

        $this->idSolicitud = $solicitud ? $solicitud->soli_id_solicitud : null;
        $this->idPago = $solicitud ? $solicitud->soli_id_pago : null;
        $this->idEvaluacion = $solicitud ? $solicitud->soli_id_evaluacion : null;

        if (!$this->idSolicitud) {
            return;
        }

        /* El estado vigente es el último renglón de la bitácora. */
        $this->estadoSolicitud = DB::table('estado_solicitud as e')
            ->join('c_estado_solicitud as c', 'c.esso_id_c_estado_solicitud', '=', 'e.esso_id_c_estado_solicitud')
            ->where('e.esso_id_solicitud', $this->idSolicitud)
            ->orderByDesc('e.esso_id_estado_solicitud')
            ->value('c.esso_estado_solicitud');

        $this->documentos = $this->cargarDocumentos();
        $this->pago = $this->cargarPago();
    }

    public function tieneSolicitud()
    {
        return $this->idSolicitud !== null;
    }

    public function idSolicitud()
    {
        return $this->idSolicitud;
    }

    public function estadoSolicitud()
    {
        return $this->estadoSolicitud;
    }

    public function tienePago()
    {
        return $this->pago !== null;
    }

    public function tieneSedeSeleccionada()
    {
        return $this->idEvaluacion !== null;
    }

    public function tieneComprobantePago()
    {
        return $this->tienePago() && !empty($this->pago->pago_comprobante_path);
    }

    public function estadoPagoVista()
    {
        if (!$this->tieneComprobantePago()) {
            return 'sin_cargar';
        }

        return match ($this->pago->esta_estado_pago) {
            'Completado' => 'validado',
            'Declinado' => 'rechazado',
            default => 'revision',
        };
    }

    public function motivoRechazoPago()
    {
        return $this->estadoPagoVista() === 'rechazado'
            ? trim((string) $this->pago->espa_comentario)
            : null;
    }

    /**
     * El pre-registro se considera completo cuando el administrador aprobó
     * la solicitud, no cuando la persona terminó de capturar.
     */
    public function solicitudAprobada()
    {
        return $this->estadoSolicitud === 'Aprobada';
    }

    /**
     * El trámite terminó sin certificación: da igual si el administrador lo
     * rechazó en su momento o si lo canceló después de haberlo aprobado.
     */
    public function solicitudCerrada()
    {
        return in_array($this->estadoSolicitud, ['Rechazada', 'Cancelada'], true);
    }

    /**
     * Estado global de la documentación:
     * pendiente · en_proceso · revision · rechazado · aprobado
     */
    public function documentacionEstado()
    {
        $requeridos = array_keys(config('suif.documentos'));
        $estados = [];

        foreach ($requeridos as $slug) {
            $estados[] = isset($this->documentos[$slug]) ? $this->documentos[$slug] : 'pendiente';
        }

        if (in_array('rechazado', $estados, true)) {
            return 'rechazado';
        }

        $unicos = array_unique($estados);

        if (count($unicos) === 1) {
            if ($estados[0] === 'aprobado') {
                return 'aprobado';
            }

            if ($estados[0] === 'revision') {
                return 'revision';
            }

            if ($estados[0] === 'pendiente') {
                return 'pendiente';
            }
        }

        /* Tras una subsanación conviven documentos aprobados y documentos que
           volvieron a revisión: la etapa sigue en manos del administrador. */
        if (!array_diff($unicos, ['aprobado', 'revision']) && in_array('revision', $estados, true)) {
            return 'revision';
        }

        return 'en_proceso';
    }

    /**
     * Estado de un documento en particular.
     */
    public function estadoDocumento($slug)
    {
        return isset($this->documentos[$slug]) ? $this->documentos[$slug] : 'pendiente';
    }

    private function cargarDocumentos()
    {
        $filas = DB::table('documento as d')
            ->join('tipo_documento as t', 't.tido_id_tipo_documento', '=', 'd.tido_id_tipo_documento')
            ->where('d.soli_id_solicitud', $this->idSolicitud)
            ->select('d.docu_id_documento', 't.tido_tipo_documento')
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $estados = DB::table('estado_documento as ed')
            ->join('c_estado_documento as ce', 'ce.esdo_id_c_estado_documento', '=', 'ed.esdo_id_c_estado_documento')
            ->whereIn('ed.esdo_id_documento', $filas->pluck('docu_id_documento'))
            ->orderBy('ed.esdo_id_estado_documento')
            ->select('ed.esdo_id_documento', 'ce.esdo_estado_documento')
            ->get()
            ->keyBy('esdo_id_documento');

        $equivalencias = [
            'Pendiente' => 'pendiente',
            'Cargado' => 'cargado',
            'En revisión' => 'revision',
            'Aprobado' => 'aprobado',
            'Rechazado' => 'rechazado',
        ];

        $porNombre = array_flip(config('suif.documentos'));
        $resultado = [];

        foreach ($filas as $fila) {
            if (!isset($porNombre[$fila->tido_tipo_documento])) {
                continue;
            }

            $estado = isset($estados[$fila->docu_id_documento])
                ? $estados[$fila->docu_id_documento]->esdo_estado_documento
                : 'Cargado';

            $resultado[$porNombre[$fila->tido_tipo_documento]] =
                isset($equivalencias[$estado]) ? $equivalencias[$estado] : 'cargado';
        }

        return $resultado;
    }

    private function cargarPago()
    {
        if (!$this->idPago) {
            return null;
        }

        return DB::table('pago as p')
            ->leftJoin('estado_pago as ep', function ($join): void {
                $join->on('ep.espa_id_pago', '=', 'p.pago_id_pago')
                    ->whereRaw('ep.espa_id_estado_pago = (
                        SELECT MAX(ultimo.espa_id_estado_pago)
                        FROM estado_pago AS ultimo
                        WHERE ultimo.espa_id_pago = p.pago_id_pago
                    )');
            })
            ->leftJoin('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->where('p.pago_id_pago', $this->idPago)
            ->select([
                'p.pago_id_pago',
                'p.pago_comprobante_path',
                'cep.esta_estado_pago',
                'ep.espa_comentario',
            ])
            ->first();
    }
}
