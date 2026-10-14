<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * BitacoraSolicitud
 *
 * Responsabilidad: leer y escribir ESTADO_SOLICITUD, la bitácora de la
 * solicitud.
 *
 * La comparten dos lados que no deben depender uno del otro: la revisión que
 * hace el administrador (RevisionDocumentos) y el expediente que arma la
 * persona (DocumentacionPersona). Las dos leen el estado con el mismo candado,
 * así que sus transiciones sobre una misma solicitud se serializan.
 */
class BitacoraSolicitud
{
    /**
     * Bloquea la solicitud y devuelve su estado vigente.
     *
     * lockForUpdate() serializa las transiciones concurrentes sobre el mismo
     * expediente: quien llega segundo ve el estado que dejó el primero. Sólo
     * sirve dentro de una transacción.
     */
    public function estadoVigenteBloqueado(int $id_solicitud): ?string
    {
        $solicitud = DB::table('solicitud')
            ->where('soli_id_solicitud', $id_solicitud)
            ->lockForUpdate()
            ->first();

        if (!$solicitud) {
            throw new DomainException('La solicitud no existe.');
        }

        return DB::table('estado_solicitud as es')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('es.esso_id_solicitud', $id_solicitud)
            ->orderByDesc('es.esso_id_estado_solicitud')
            ->value('ces.esso_estado_solicitud');
    }

    public function registrar(
        int $id_solicitud,
        string $estado,
        ?string $motivo_rechazo = null
    ): void
    {
        $id_estado = DB::table('c_estado_solicitud')
            ->where('esso_estado_solicitud', $estado)
            ->value('esso_id_c_estado_solicitud');

        if (!$id_estado) {
            throw new DomainException('El catálogo de estados de solicitud está incompleto.');
        }

        DB::table('estado_solicitud')->insert([
            'esso_id_c_estado_solicitud' => $id_estado,
            'esso_id_solicitud' => $id_solicitud,
            'esso_fecha' => now()->toDateString(),
            'esso_hora' => now()->toTimeString(),
            'esso_motivo_rechazo' => $motivo_rechazo,
        ]);
    }
}
