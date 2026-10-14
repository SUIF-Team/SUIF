<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * BitacoraPago
 *
 * Responsabilidad: leer y escribir ESTADO_PAGO, la bitácora del pago, y
 * comprobar que la solicitud del pago esté aprobada.
 *
 * La comparten dos lados que no deben depender uno del otro: la revisión que
 * hace el administrador (RevisionPagos) y la carga del comprobante que hace la
 * persona (ComprobantePago). Las dos escriben el mismo historial con las mismas
 * reglas.
 */
class BitacoraPago
{
    public function verificarSolicitudAprobada(int $id_solicitud): void
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

    public function ultimoEstado(int $id_pago): ?string
    {
        return DB::table('estado_pago as ep')
            ->join('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->where('ep.espa_id_pago', $id_pago)
            ->orderByDesc('ep.espa_id_estado_pago')
            ->lockForUpdate()
            ->value('cep.esta_estado_pago');
    }

    public function registrar(int $id_pago, string $estado, ?string $comentario = null): void
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
