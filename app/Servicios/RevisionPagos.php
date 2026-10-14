<?php

namespace App\Servicios;

use App\Consultas\ConsultaPagos;
use DomainException;
use Illuminate\Support\Facades\DB;

class RevisionPagos
{
    public function __construct(private readonly BitacoraPago $bitacora)
    {
    }

    public function aprobar(int $id_pago): void
    {
        $this->resolver($id_pago, ConsultaPagos::COMPLETADO);
    }

    public function rechazar(int $id_pago, string $motivo_rechazo): void
    {
        $motivo_rechazo = trim($motivo_rechazo);

        if ($motivo_rechazo === '') {
            throw new DomainException('Escribe el motivo del rechazo.');
        }

        $this->resolver($id_pago, ConsultaPagos::DECLINADO, $motivo_rechazo);
    }

    /**
     * Devuelve un pago ya resuelto a revisión.
     *
     * ESTADO_PAGO es una bitácora: reanudar no borra la resolución anterior,
     * agrega un renglón nuevo que pasa a ser el estado vigente.
     */
    public function reanudar(int $id_pago): void
    {
        DB::transaction(function () use ($id_pago): void {
            $this->bloquearPagoRevisable($id_pago);

            if (!in_array(
                $this->bitacora->ultimoEstado($id_pago),
                [ConsultaPagos::COMPLETADO, ConsultaPagos::DECLINADO],
                true
            )) {
                throw new DomainException('El pago aún no ha sido resuelto: no hay nada que reanudar.');
            }

            $this->bitacora->registrar($id_pago, ConsultaPagos::PENDIENTE);
        });
    }

    private function resolver(int $id_pago, string $estado_destino, ?string $comentario = null): void
    {
        DB::transaction(function () use ($id_pago, $estado_destino, $comentario): void {
            $this->bloquearPagoRevisable($id_pago);

            if ($this->bitacora->ultimoEstado($id_pago) !== ConsultaPagos::PENDIENTE) {
                throw new DomainException('El pago ya fue resuelto o no está disponible para revisión.');
            }

            $this->bitacora->registrar($id_pago, $estado_destino, $comentario);
        });
    }

    /**
     * Precondiciones comunes a toda decisión administrativa sobre un pago: el
     * renglón existe, cuelga de una solicitud aprobada y su comprobante sigue
     * en el disco. Bloquea pago y solicitud para serializar decisiones
     * concurrentes.
     */
    private function bloquearPagoRevisable(int $id_pago): object
    {
        $pago = DB::table('pago')
            ->where('pago_id_pago', $id_pago)
            ->lockForUpdate()
            ->first();

        if (!$pago) {
            throw new DomainException('El pago solicitado no existe.');
        }

        $solicitud = DB::table('solicitud')
            ->where('soli_id_pago', $id_pago)
            ->orderByDesc('soli_id_solicitud')
            ->lockForUpdate()
            ->first();

        if (!$solicitud) {
            throw new DomainException('El pago no está ligado a una solicitud.');
        }

        $this->bitacora->verificarSolicitudAprobada((int) $solicitud->soli_id_solicitud);

        if (!(new ConsultaPagos())->archivoDisponible($pago->pago_comprobante_path)) {
            throw new DomainException('El comprobante no está disponible para revisión.');
        }

        return $pago;
    }
}
