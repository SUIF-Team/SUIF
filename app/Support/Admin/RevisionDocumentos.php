<?php

namespace App\Support\Admin;

use DomainException;
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

            $documentos = DB::table('documento')
                ->where('soli_id_solicitud', $id_solicitud)
                ->orderBy('docu_id_documento')
                ->lockForUpdate()
                ->pluck('docu_id_documento')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            $recibidos = array_map('strval', array_keys($decisiones));
            sort($documentos);
            sort($recibidos);

            if (!$documentos || $documentos !== $recibidos) {
                throw new DomainException('Los documentos recibidos no corresponden al expediente completo.');
            }

            foreach ($decisiones as $decision) {
                if (!in_array($decision, [self::APROBADO, self::RECHAZADO], true)) {
                    throw new DomainException('Una o más decisiones documentales no son válidas.');
                }
            }

            $this->verificarDocumentosEnRevision($documentos);

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

    private function verificarDocumentosEnRevision(array $documentos): void
    {
        $ultimos_estados = DB::table('estado_documento as ed')
            ->join('c_estado_documento as ced', 'ced.esdo_id_c_estado_documento', '=', 'ed.esdo_id_c_estado_documento')
            ->whereIn('ed.esdo_id_documento', $documentos)
            ->whereRaw('ed.esdo_id_estado_documento = (
                SELECT MAX(ultimo.esdo_id_estado_documento)
                FROM estado_documento AS ultimo
                WHERE ultimo.esdo_id_documento = ed.esdo_id_documento
            )')
            ->pluck('ced.esdo_estado_documento', 'ed.esdo_id_documento');

        foreach ($documentos as $id_documento) {
            if ($ultimos_estados->get($id_documento) !== 'En revisión') {
                throw new DomainException('Uno o más documentos ya no están disponibles para revisión.');
            }
        }
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
