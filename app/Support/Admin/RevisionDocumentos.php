<?php

namespace App\Support\Admin;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevisionDocumentos
{
    public const APROBADO = 'aprobado';

    public const REVISION = 'revision';

    public const RECHAZADO = 'rechazado';

    /**
     * Registra una resolución histórica para cada documento de la solicitud.
     *
     * Cada documento rechazado exige su propio comentario; la fecha límite es
     * única para el expediente y se anexa al comentario de cada rechazo.
     */
    public function resolver(
        int $id_solicitud,
        array $decisiones,
        array $comentarios,
        ?string $fecha_limite = null
    ): string
    {
        return DB::transaction(function () use ($id_solicitud, $decisiones, $comentarios, $fecha_limite): string {
            $this->bloquearSolicitudEnRevision($id_solicitud);

            DB::table('documento')
                ->where('soli_id_solicitud', $id_solicitud)
                ->lockForUpdate()
                ->get();

            /* Sólo se resuelven los documentos que esperan revisión. Los que ya
               fueron aprobados conservan su estado y no vuelven a revisarse. */
            $pendientes = $this->documentosEnRevision($id_solicitud);
            $recibidos = array_map('strval', array_keys($decisiones));
            sort($pendientes);
            sort($recibidos);

            if (!$pendientes) {
                throw new DomainException('El expediente no tiene documentos pendientes de revisión.');
            }

            if ($pendientes !== $recibidos) {
                throw new DomainException('Los documentos recibidos no corresponden a los que esperan revisión.');
            }

            foreach ($decisiones as $decision) {
                if (!in_array($decision, [self::APROBADO, self::RECHAZADO], true)) {
                    throw new DomainException('Una o más decisiones documentales no son válidas.');
                }
            }

            $hay_rechazos = in_array(self::RECHAZADO, $decisiones, true);

            foreach ($decisiones as $id_documento => $decision) {
                if ($decision === self::RECHAZADO
                    && trim((string) ($comentarios[$id_documento] ?? '')) === '') {
                    throw new DomainException('Escribe el motivo del rechazo de cada documento rechazado.');
                }
            }

            if ($hay_rechazos && !$this->fechaLimiteValida($fecha_limite)) {
                throw new DomainException('Indica una fecha límite válida.');
            }

            $catalogo = DB::table('c_estado_documento')
                ->whereIn('esdo_estado_documento', ['Aprobado', 'Rechazado'])
                ->pluck('esdo_id_c_estado_documento', 'esdo_estado_documento');

            if (!$catalogo->has('Aprobado') || !$catalogo->has('Rechazado')) {
                throw new DomainException('El catálogo de estados documentales está incompleto.');
            }

            $ahora = now();
            $historial = [];

            foreach ($decisiones as $id_documento => $decision) {
                $es_rechazado = $decision === self::RECHAZADO;
                $estado = $es_rechazado ? 'Rechazado' : 'Aprobado';

                $historial[] = [
                    'esdo_id_c_estado_documento' => $catalogo[$estado],
                    'esdo_id_documento' => (int) $id_documento,
                    'esdo_comentarios' => $es_rechazado
                        ? sprintf(
                            "%s\nFecha límite: %s",
                            trim((string) $comentarios[$id_documento]),
                            $fecha_limite
                        )
                        : null,
                    'esdo_fecha' => $ahora->toDateString(),
                    'esdo_hora' => $ahora->toTimeString(),
                ];
            }

            DB::table('estado_documento')->insert($historial);

            if ($hay_rechazos) {
                return self::REVISION;
            }

            /* La solicitud sólo se aprueba cuando el expediente completo quedó
               aprobado, contando los documentos resueltos en revisiones previas. */
            if ($this->documentosSinAprobar($id_solicitud) > 0) {
                return self::REVISION;
            }

            $this->registrarEstadoSolicitud($id_solicitud, 'Aprobada');

            return self::APROBADO;
        });
    }

    /**
     * Cierra la solicitud sin alterar el historial documental ya existente.
     */
    public function interrumpir(int $id_solicitud, ?string $motivo_rechazo = null): string
    {
        return DB::transaction(function () use ($id_solicitud, $motivo_rechazo): string {
            $this->bloquearSolicitudEnRevision($id_solicitud);

            $motivo_rechazo = trim((string) $motivo_rechazo) ?: null;

            $this->registrarEstadoSolicitud($id_solicitud, 'Rechazada', $motivo_rechazo);

            return self::RECHAZADO;
        });
    }

    private function fechaLimiteValida(?string $fecha_limite): bool
    {
        if (!is_string($fecha_limite)) {
            return false;
        }

        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha_limite);
        $errores = \DateTimeImmutable::getLastErrors();

        return $fecha
            && $fecha->format('Y-m-d') === $fecha_limite
            && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0));
    }

    private function bloquearSolicitudEnRevision(int $id_solicitud): void
    {
        $solicitud = DB::table('solicitud')
            ->where('soli_id_solicitud', $id_solicitud)
            ->lockForUpdate()
            ->first();

        if (!$solicitud) {
            throw new DomainException('La solicitud no existe.');
        }

        $estado = DB::table('estado_solicitud as es')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->where('es.esso_id_solicitud', $id_solicitud)
            ->orderByDesc('es.esso_id_estado_solicitud')
            ->value('ces.esso_estado_solicitud');

        if ($estado !== 'En revisión') {
            throw new DomainException('La solicitud ya fue resuelta o no está disponible para revisión.');
        }
    }

    /**
     * Documentos de la solicitud cuyo último estado es "En revisión".
     *
     * @return array<int, string>
     */
    private function documentosEnRevision(int $id_solicitud): array
    {
        return $this->ultimosEstados($id_solicitud)
            ->filter(fn (string $estado): bool => $estado === 'En revisión')
            ->keys()
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * Cuántos documentos de la solicitud aún no están aprobados.
     */
    private function documentosSinAprobar(int $id_solicitud): int
    {
        return $this->ultimosEstados($id_solicitud)
            ->filter(fn (string $estado): bool => $estado !== 'Aprobado')
            ->count();
    }

    /**
     * Último estado de cada documento de la solicitud, indexado por documento.
     *
     * ESTADO_DOCUMENTO es una bitácora: el estado vigente es el registro con
     * el identificador más alto de cada documento.
     */
    private function ultimosEstados(int $id_solicitud): Collection
    {
        return DB::table('documento as d')
            ->join('estado_documento as ed', 'ed.esdo_id_documento', '=', 'd.docu_id_documento')
            ->join('c_estado_documento as ced', 'ced.esdo_id_c_estado_documento', '=', 'ed.esdo_id_c_estado_documento')
            ->where('d.soli_id_solicitud', $id_solicitud)
            ->whereRaw('ed.esdo_id_estado_documento = (
                SELECT MAX(ultimo.esdo_id_estado_documento)
                FROM estado_documento AS ultimo
                WHERE ultimo.esdo_id_documento = ed.esdo_id_documento
            )')
            ->orderBy('d.docu_id_documento')
            ->pluck('ced.esdo_estado_documento', 'd.docu_id_documento');
    }

    private function registrarEstadoSolicitud(
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
