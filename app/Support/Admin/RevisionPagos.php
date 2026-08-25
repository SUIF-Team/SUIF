<?php

namespace App\Support\Admin;

use DomainException;
use Illuminate\Support\Facades\DB;

class RevisionPagos
{
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
     * Registra una carga nueva únicamente para el pago ligado a la solicitud
     * más reciente de la persona autenticada.
     *
     * $datos_pago trae el monto, la fecha y la hora que capturó la persona, ya
     * validados por el controlador.
     *
     * @param array{monto_pagado: string|float, fecha_pago: string, hora_pago: string} $datos_pago
     */
    public function registrarComprobanteDePersona(int $id_usuario, string $ruta_archivo, array $datos_pago): void
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

            $this->verificarSolicitudAprobada((int) $solicitud->soli_id_solicitud);

            $pago = DB::table('pago')
                ->where('pago_id_pago', $solicitud->soli_id_pago)
                ->lockForUpdate()
                ->first();

            if (!$pago) {
                throw new DomainException('El pago ligado a tu solicitud no existe.');
            }

            $estado = $this->ultimoEstado((int) $pago->pago_id_pago);

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
            DB::table('pago')
                ->where('pago_id_pago', $pago->pago_id_pago)
                ->update([
                    'pago_comprobante_path' => $ruta_archivo,
                    'pago_monto_pagado' => $datos_pago['monto_pagado'],
                    'pago_fecha_pago' => $datos_pago['fecha_pago'],
                    'pago_hora_pago' => substr((string) $datos_pago['hora_pago'], 0, 5).':00',
                ]);

            $this->registrarEstado((int) $pago->pago_id_pago, ConsultaPagos::PENDIENTE);
        });
    }

    private function resolver(int $id_pago, string $estado_destino, ?string $comentario = null): void
    {
        DB::transaction(function () use ($id_pago, $estado_destino, $comentario): void {
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

            $this->verificarSolicitudAprobada((int) $solicitud->soli_id_solicitud);

            if (!(new ConsultaPagos())->archivoDisponible($pago->pago_comprobante_path)) {
                throw new DomainException('El comprobante no está disponible para revisión.');
            }

            if ($this->ultimoEstado($id_pago) !== ConsultaPagos::PENDIENTE) {
                throw new DomainException('El pago ya fue resuelto o no está disponible para revisión.');
            }

            $this->registrarEstado($id_pago, $estado_destino, $comentario);
        });
    }

    private function verificarSolicitudAprobada(int $id_solicitud): void
    {
        $estado = DB::table('estado_solicitud as es')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('es.esso_id_solicitud', $id_solicitud)
            ->orderByDesc('es.esso_id_estado_solicitud')
            ->value('ces.esso_estado_solicitud');

        if ($estado !== 'Aprobada') {
            throw new DomainException('La solicitud aún no está aprobada para revisar el pago.');
        }
    }

    private function ultimoEstado(int $id_pago): ?string
    {
        return DB::table('estado_pago as ep')
            ->join('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->where('ep.espa_id_pago', $id_pago)
            ->orderByDesc('ep.espa_id_estado_pago')
            ->lockForUpdate()
            ->value('cep.esta_estado_pago');
    }

    private function registrarEstado(int $id_pago, string $estado, ?string $comentario = null): void
    {
        $id_estado = DB::table('c_estado_pago')
            ->where('esta_estado_pago', $estado)
            ->value('espa_id_c_estado_pago');

        if (!$id_estado) {
            throw new DomainException('El catálogo de estados de pago está incompleto.');
        }

        $ahora = now();

        DB::table('estado_pago')->insert([
            'espa_id_pago' => $id_pago,
            'espa_id_c_estado_pago' => $id_estado,
            'espa_fecha' => $ahora->toDateString(),
            'espa_hora' => $ahora->toTimeString(),
            'espa_comentario' => $comentario,
        ]);
    }
}
