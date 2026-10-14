<?php

namespace App\Servicios;

use App\Consultas\ConsultaPagos;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * ComprobantePago
 *
 * Responsabilidad: registrar el comprobante de pago que sube la persona y
 * dejar el pago en revisión.
 *
 * Es el lado de la persona del pago; la decisión sobre ese comprobante la toma
 * el administrador en RevisionPagos. Los dos escriben la misma bitácora.
 */
class ComprobantePago
{
    public function __construct(private readonly BitacoraPago $bitacora)
    {
    }

    /**
     * Registra una carga nueva únicamente para el pago ligado a la solicitud
     * más reciente de la persona autenticada.
     *
     * $datos_pago trae lo que capturó la persona, ya validado por el
     * controlador: monto, fecha y hora, la forma de pago con su banco y el
     * comprobante que pide. uso_cfdi llega en null cuando ya había elección.
     *
     * @param array{monto_pagado: string|float, fecha_pago: string, hora_pago: string,
     *              metodo_pago: int, banco: ?int, uso_cfdi: ?bool} $datos_pago
     */
    public function registrar(int $id_usuario, string $ruta_archivo, array $datos_pago): void
    {
        DB::transaction(function () use ($id_usuario, $ruta_archivo, $datos_pago): void {
            $solicitud = DB::table('solicitud as s')
                ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
                ->where('p.pers_id_usuario', $id_usuario)
                ->orderByDesc('s.soli_id_solicitud')
                ->lockForUpdate()
                ->select('s.soli_id_solicitud', 's.soli_id_pago')
                ->first();

            if (!$solicitud || !$solicitud->soli_id_pago) {
                throw new DomainException('Aún no existe un pago ligado a tu solicitud.');
            }

            $this->bitacora->verificarSolicitudAprobada((int) $solicitud->soli_id_solicitud);

            $pago = DB::table('pago')
                ->where('pago_id_pago', $solicitud->soli_id_pago)
                ->lockForUpdate()
                ->first();

            if (!$pago) {
                throw new DomainException('El pago ligado a tu solicitud no existe.');
            }

            $estado = $this->bitacora->ultimoEstado((int) $pago->pago_id_pago);

            if (in_array($estado, [ConsultaPagos::PENDIENTE, ConsultaPagos::COMPLETADO], true)) {
                throw new DomainException('Tu comprobante ya está en revisión o fue aprobado y no puede reemplazarse.');
            }

            /* El pago se fecha con lo que declaró la persona, no con el momento
               de la carga: quien revisa el comprobante compara contra eso.

               PAGO_MONTO_PAGADO nació con el monto de la referencia porque el
               renglón se crea al asignarla; aquí pasa a guardar lo pagado. El
               monto que se cobró sigue en REFERENCIA_BANCARIA.REBA_MONTO.

               Los segundos se completan a mano: PostgreSQL los rellena solo al
               guardar en TIME, pero SQLite —el motor de las pruebas— almacena
               la cadena tal cual. */
            $cambios = [
                'pago_comprobante_path' => $ruta_archivo,
                'pago_monto_pagado' => $datos_pago['monto_pagado'],
                'pago_fecha_pago' => $datos_pago['fecha_pago'],
                'pago_hora_pago' => substr((string) $datos_pago['hora_pago'], 0, 5).':00',
                /* La forma de pago se reemplaza en cada carga: al subsanar
                   también puede corregirse. */
                'pago_id_metodo_pago' => $datos_pago['metodo_pago'],
                'pago_id_banco' => $datos_pago['banco'],
            ];

            /* La elección del comprobante es definitiva: sólo se escribe si el
               pago todavía no tenía una. */
            if ($pago->pago_uso_cfdi === null && $datos_pago['uso_cfdi'] !== null) {
                $cambios['pago_uso_cfdi'] = $datos_pago['uso_cfdi'];
            }

            DB::table('pago')
                ->where('pago_id_pago', $pago->pago_id_pago)
                ->update($cambios);

            $this->bitacora->registrar((int) $pago->pago_id_pago, ConsultaPagos::PENDIENTE);
        });
    }
}
