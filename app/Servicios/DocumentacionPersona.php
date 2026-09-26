<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * DocumentacionPersona
 *
 * Responsabilidad: las escrituras que la persona hace sobre su propio
 * expediente documental y la regla que las acota: sólo mientras la solicitud
 * siga abierta.
 *
 * Una solicitud resuelta (aprobada, interrumpida o cancelada) sólo la reabre
 * la UIF con RevisionDocumentos::reanudar(), que exige el privilegio
 * reanudar-tramite. La pantalla ya escondía los botones; esta clase hace valer
 * la regla en el servidor.
 *
 * Primer tramo de la extracción del pre-registro (fase 3 de la auditoría MVC):
 * la carga de archivos se mudará aquí en esa fase.
 */
class DocumentacionPersona
{
    /**
     * Lista blanca: un estado vigente desconocido o ausente cuenta como
     * cerrado.
     */
    public const ESTADOS_ABIERTOS = ['Pre-registro', 'Documentación', 'En revisión'];

    public function __construct(private readonly BitacoraSolicitud $bitacora)
    {
    }

    /**
     * Bloquea la solicitud y falla si ya no admite cambios de la persona.
     * Va dentro de la transacción que escribe, como primer paso.
     */
    public function exigirAbierta(int $id_solicitud): void
    {
        $estado = $this->bitacora->estadoVigenteBloqueado($id_solicitud);

        if (!in_array($estado, self::ESTADOS_ABIERTOS, true)) {
            throw new DomainException('Tu trámite ya no admite cambios en la documentación.');
        }
    }

    /**
     * Manda a revisión los documentos indicados y la solicitud completa.
     *
     * @param array<int, int> $ids_documentos Los que no están aprobados: al
     *        subsanar, los aprobados conservan su estado.
     */
    public function enviarARevision(int $id_solicitud, array $ids_documentos): void
    {
        DB::transaction(function () use ($id_solicitud, $ids_documentos): void {
            $this->exigirAbierta($id_solicitud);

            $id_estado = DB::table('c_estado_documento')
                ->where('esdo_estado_documento', 'En revisión')
                ->value('esdo_id_c_estado_documento');

            if (!$id_estado) {
                throw new DomainException('El catálogo de estados documentales está incompleto.');
            }

            $ahora = now();

            DB::table('estado_documento')->insert(array_map(
                fn (int $id_documento): array => [
                    'esdo_id_c_estado_documento' => $id_estado,
                    'esdo_id_documento' => $id_documento,
                    'esdo_comentarios' => null,
                    'esdo_fecha' => $ahora->toDateString(),
                    'esdo_hora' => $ahora->toTimeString(),
                ],
                $ids_documentos
            ));

            $this->bitacora->registrar($id_solicitud, 'En revisión');
        });
    }
}
