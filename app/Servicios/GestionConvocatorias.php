<?php

namespace App\Servicios;

use App\Models\Convocatoria;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GestionConvocatorias
 *
 * Responsabilidad: el alta, la edición y el ciclo de vida de las convocatorias,
 * y la única definición de qué convocatoria está abierta a registro.
 *
 * El estado no es una columna de CONVOCATORIA sino una bitácora aparte:
 * ESTADO_CONVOCATORIA guarda un renglón por cambio, con su fecha y su hora, y
 * el vigente es el de identificador más alto. Cerrar una convocatoria agrega un
 * movimiento; no corrige el anterior. Es el mismo trato que reciben las
 * solicitudes, los documentos y los pagos.
 *
 * Sólo una convocatoria puede estar vigente a la vez. La regla no vive en la
 * base —el esquema no la modela— sino aquí, porque es la que le da sentido a la
 * pregunta que hace el pre-registro: a qué convocatoria pertenece quien se
 * registra hoy.
 */
class GestionConvocatorias
{
    public const VIGENTE = 'Vigente';

    public const CERRADA = 'Cerrada';

    public const INTERRUMPIDA = 'Interrumpida';

    /**
     * El catálogo completo, en el orden en que se ofrece en pantalla.
     *
     * @return array<int, string>
     */
    public function estados(): array
    {
        return [self::VIGENTE, self::CERRADA, self::INTERRUMPIDA];
    }

    /**
     * La bandeja administrativa. A diferencia de lo que consulta el
     * pre-registro, aquí se ven todas: las cerradas y las interrumpidas son el
     * historial de la certificación y no se ocultan.
     */
    public function bandeja(array $filtros = []): array
    {
        $todas = $this->filas();

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $estado = trim((string) ($filtros['estado'] ?? ''));

        $filtradas = $todas->filter(function (array $convocatoria) use ($buscar, $estado): bool {
            if ($buscar !== '' && mb_stripos($convocatoria['nombre'], $buscar) === false) {
                return false;
            }

            return $estado === '' || $convocatoria['estado'] === $estado;
        })->values();

        $vigente = $todas->firstWhere('estado', self::VIGENTE);

        return [
            'convocatorias' => $filtradas,
            'resumen' => [
                'registradas' => $todas->count(),
                'vigente' => $vigente['nombre'] ?? null,
                'registro_abierto' => (bool) ($vigente['registro_abierto'] ?? false),
                'solicitudes_vigente' => (int) ($vigente['solicitudes'] ?? 0),
            ],
        ];
    }

    /**
     * Un renglón de la bandeja ya resuelto. Sirve para la tabla y para el
     * formulario, de modo que las dos pantallas no tengan cada una su propia
     * idea de en qué estado está una convocatoria.
     */
    public function convocatoria(int $id): array
    {
        $convocatoria = $this->filas()->firstWhere('id', $id);

        if (!$convocatoria) {
            throw new DomainException('La convocatoria indicada no existe.');
        }

        return $convocatoria;
    }

    /**
     * La convocatoria nace vigente: darla de alta es abrirla.
     *
     * Por eso el alta se rechaza si ya hay otra vigente. Cerrar la anterior en
     * silencio dejaría sin registro a quien estuviera a media captura, y esa no
     * es una decisión que deba tomar el sistema por su cuenta.
     */
    public function crear(array $datos): Convocatoria
    {
        return DB::transaction(function () use ($datos): Convocatoria {
            $this->exigirQueNoHayaOtraVigente(null);

            $convocatoria = Convocatoria::query()->create($this->columnas($datos));

            $this->registrarEstado((int) $convocatoria->conv_id_convocatoria, self::VIGENTE);

            return $convocatoria;
        }, 3);
    }

    /**
     * La edición corrige los datos de la convocatoria y nunca su estado: para
     * eso está cambiarEstado(), que sí deja rastro en la bitácora.
     */
    public function actualizar(int $id, array $datos): Convocatoria
    {
        return DB::transaction(function () use ($id, $datos): Convocatoria {
            $convocatoria = Convocatoria::query()->lockForUpdate()->findOrFail($id);

            $convocatoria->fill($this->columnas($datos))->save();

            return $convocatoria;
        });
    }

    /**
     * Eliminar una convocatoria con solicitudes borraría el expediente de
     * quienes se registraron en ella: SOLICITUD la apunta con una columna
     * obligatoria y la llave foránea es restrictiva, así que la base tampoco lo
     * permitiría. Se avisa antes, con la salida que sí existe.
     */
    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $convocatoria = Convocatoria::query()->lockForUpdate()->findOrFail($id);

            if ($this->solicitudes($id) > 0) {
                throw new DomainException(
                    'No es posible eliminar una convocatoria que ya tiene solicitudes. Ciérrala o interrúmpela.'
                );
            }

            /* La bitácora se va con ella: su llave foránea también es
               restrictiva y no puede quedar huérfana. */
            DB::table('estado_convocatoria')->where('esco_id_convocatoria', $id)->delete();

            $convocatoria->delete();
        });
    }

    /**
     * Agrega un movimiento a la bitácora. No hay transición prohibida: una
     * convocatoria interrumpida puede volver a abrirse, igual que un trámite
     * resuelto puede reanudarse. Lo único que se impide es dejar dos vigentes.
     */
    public function cambiarEstado(int $id, string $estado): void
    {
        if (!in_array($estado, $this->estados(), true)) {
            throw new DomainException('El estado indicado no existe.');
        }

        DB::transaction(function () use ($id, $estado): void {
            /* El candado va sobre la convocatoria y no sobre la bitácora: es la
               fila que serializa las decisiones concurrentes sobre ella. */
            Convocatoria::query()->lockForUpdate()->findOrFail($id);

            if ($this->estadoVigente($id, true) === $estado) {
                throw new DomainException("La convocatoria ya está en estado «{$estado}».");
            }

            if ($estado === self::VIGENTE) {
                $this->exigirQueNoHayaOtraVigente($id);
            }

            $this->registrarEstado($id, $estado);
        }, 3);
    }

    /**
     * La convocatoria a la que pertenece quien se registra hoy, o null si no
     * hay ninguna abierta.
     *
     * Son dos condiciones y hacen falta las dos: la ventana de registro dice
     * cuándo se puede entrar y el estado dice si la convocatoria sigue en pie.
     * Una interrumpida a media ventana deja de admitir gente aunque le sobren
     * fechas.
     *
     * Si hubiera empate gana la de identificador más alto, que es la más
     * reciente.
     */
    public function idConvocatoriaAbierta(): ?int
    {
        $hoy = Carbon::now(config('app.timezone'))->toDateString();

        $id = DB::table('convocatoria as c')
            ->joinSub($this->ultimosEstados(), 'ue', 'ue.esco_id_convocatoria', '=', 'c.conv_id_convocatoria')
            ->join('estado_convocatoria as ec', 'ec.esco_id_estado_convocatoria', '=', 'ue.id_estado')
            ->join('c_estado_convocatoria as cec', 'cec.esco_id_c_estado_convocatoria', '=', 'ec.esco_id_c_estado_convocatoria')
            ->where('cec.esco_estado_convocatoria', self::VIGENTE)
            ->whereDate('c.conv_fecha_inicio_registro', '<=', $hoy)
            ->whereDate('c.conv_fecha_fin_registro', '>=', $hoy)
            ->orderByDesc('c.conv_id_convocatoria')
            ->value('c.conv_id_convocatoria');

        return $id === null ? null : (int) $id;
    }

    /**
     * El estado vigente de una convocatoria: el renglón de identificador más
     * alto de su bitácora. Devuelve null si todavía no tiene ninguno.
     *
     * El bloqueo es opcional porque la mayoría de las lecturas sólo pintan
     * pantalla; se pide cuando de esa respuesta depende una escritura.
     */
    public function estadoVigente(int $id, bool $bloquear = false): ?string
    {
        $consulta = DB::table('estado_convocatoria as ec')
            ->join('c_estado_convocatoria as cec', 'cec.esco_id_c_estado_convocatoria', '=', 'ec.esco_id_c_estado_convocatoria')
            ->where('ec.esco_id_convocatoria', $id)
            ->orderByDesc('ec.esco_id_estado_convocatoria');

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $estado = $consulta->value('cec.esco_estado_convocatoria');

        return $estado === null ? null : (string) $estado;
    }

    /* ── Interno ────────────────────────────────────────────────────────── */

    /**
     * Todos los renglones de la bandeja, con su estado vigente y su ocupación.
     *
     * El estado se resuelve con la subconsulta del identificador más alto por
     * convocatoria, igual que las bandejas de pre-registros y de pagos. La
     * unión es por la izquierda porque una convocatoria dada de alta antes del
     * módulo puede no tener bitácora todavía.
     */
    private function filas(): Collection
    {
        $solicitudes = DB::table('solicitud')
            ->selectRaw('soli_id_convocatoria, COUNT(*) AS solicitudes')
            ->whereNotNull('soli_id_convocatoria')
            ->groupBy('soli_id_convocatoria');

        return DB::table('convocatoria as c')
            ->leftJoinSub($this->ultimosEstados(), 'ue', 'ue.esco_id_convocatoria', '=', 'c.conv_id_convocatoria')
            ->leftJoin('estado_convocatoria as ec', 'ec.esco_id_estado_convocatoria', '=', 'ue.id_estado')
            ->leftJoin('c_estado_convocatoria as cec', 'cec.esco_id_c_estado_convocatoria', '=', 'ec.esco_id_c_estado_convocatoria')
            ->leftJoinSub($solicitudes, 's', 's.soli_id_convocatoria', '=', 'c.conv_id_convocatoria')
            ->orderByDesc('c.conv_id_convocatoria')
            ->select([
                'c.conv_id_convocatoria',
                'c.conv_nombre',
                'c.conv_monto_recuperacion',
                'c.conv_fecha_inicio_registro',
                'c.conv_fecha_fin_registro',
                'c.conv_fin_fecha_entrega_docs',
                'c.conv_fecha_inicio',
                'c.conv_fecha_fin',
                'cec.esco_estado_convocatoria',
                'ec.esco_fecha',
                'ec.esco_hora',
                DB::raw('COALESCE(s.solicitudes, 0) AS solicitudes'),
            ])
            ->get()
            ->map(function (object $fila): array {
                $estado = $fila->esco_estado_convocatoria === null
                    ? null
                    : (string) $fila->esco_estado_convocatoria;
                $monto = Convocatoria::montoDecimal($fila->conv_monto_recuperacion);

                return [
                    'id' => (int) $fila->conv_id_convocatoria,
                    'nombre' => (string) $fila->conv_nombre,
                    'monto' => $monto,
                    'monto_formateado' => number_format($monto, 2, '.', ','),
                    'fecha_inicio_registro' => $this->dia($fila->conv_fecha_inicio_registro),
                    'fecha_fin_registro' => $this->dia($fila->conv_fecha_fin_registro),
                    'fin_fecha_entrega_docs' => $this->dia($fila->conv_fin_fecha_entrega_docs),
                    'fecha_inicio' => $this->dia($fila->conv_fecha_inicio),
                    'fecha_fin' => $this->dia($fila->conv_fecha_fin),
                    'estado' => $estado ?? 'Sin estado',
                    'estado_clave' => $this->claveEstado($estado),
                    'estado_fecha' => $this->dia($fila->esco_fecha),
                    'estado_hora' => substr((string) $fila->esco_hora, 0, 5),
                    'solicitudes' => (int) $fila->solicitudes,
                    'registro_abierto' => $estado === self::VIGENTE && $this->ventanaAbierta(
                        $fila->conv_fecha_inicio_registro,
                        $fila->conv_fecha_fin_registro
                    ),
                ];
            })
            ->values();
    }

    /**
     * Un renglón por convocatoria con el identificador de su último estado.
     */
    private function ultimosEstados(): Builder
    {
        return DB::table('estado_convocatoria')
            ->selectRaw('esco_id_convocatoria, MAX(esco_id_estado_convocatoria) as id_estado')
            ->groupBy('esco_id_convocatoria');
    }

    /**
     * El movimiento nuevo de la bitácora. La fecha y la hora salen del mismo
     * now() y no de dos llamadas distintas: dos consultas al reloj pueden caer
     * a los lados de la medianoche y dejar el renglón fechado el día anterior.
     */
    private function registrarEstado(int $id_convocatoria, string $estado): void
    {
        $id_estado = DB::table('c_estado_convocatoria')
            ->where('esco_estado_convocatoria', $estado)
            ->value('esco_id_c_estado_convocatoria');

        if (!$id_estado) {
            throw new DomainException('El catálogo de estados de convocatoria está incompleto.');
        }

        $ahora = now();

        DB::table('estado_convocatoria')->insert([
            'esco_id_c_estado_convocatoria' => $id_estado,
            'esco_id_convocatoria' => $id_convocatoria,
            'esco_fecha' => $ahora->toDateString(),
            'esco_hora' => $ahora->toTimeString(),
        ]);
    }

    /**
     * Corta el paso si ya hay otra convocatoria vigente, y dice cuál es: sin el
     * nombre, quien lo lea no sabe qué tiene que cerrar.
     */
    private function exigirQueNoHayaOtraVigente(?int $excluir): void
    {
        $vigente = DB::table('convocatoria as c')
            ->joinSub($this->ultimosEstados(), 'ue', 'ue.esco_id_convocatoria', '=', 'c.conv_id_convocatoria')
            ->join('estado_convocatoria as ec', 'ec.esco_id_estado_convocatoria', '=', 'ue.id_estado')
            ->join('c_estado_convocatoria as cec', 'cec.esco_id_c_estado_convocatoria', '=', 'ec.esco_id_c_estado_convocatoria')
            ->where('cec.esco_estado_convocatoria', self::VIGENTE)
            ->when($excluir, fn ($consulta) => $consulta->where('c.conv_id_convocatoria', '!=', $excluir))
            ->orderByDesc('c.conv_id_convocatoria')
            ->value('c.conv_nombre');

        if ($vigente !== null) {
            throw new DomainException(
                "«{$vigente}» sigue vigente. Ciérrala o interrúmpela antes de dejar otra convocatoria vigente."
            );
        }
    }

    private function solicitudes(int $id): int
    {
        return DB::table('solicitud')->where('soli_id_convocatoria', $id)->count();
    }

    /**
     * Las columnas tal como las espera la tabla.
     *
     * CONV_MONTO_RECUPERACION es MONEY: se le entrega una cadena con punto
     * decimal y sin separador de miles, que PostgreSQL convierte sin depender
     * de cómo esté configurado el formato de moneda del servidor.
     */
    private function columnas(array $datos): array
    {
        return [
            'conv_nombre' => $datos['nombre'],
            'conv_monto_recuperacion' => number_format((float) $datos['monto'], 2, '.', ''),
            'conv_fecha_inicio_registro' => $datos['fecha_inicio_registro'],
            'conv_fecha_fin_registro' => $datos['fecha_fin_registro'],
            'conv_fin_fecha_entrega_docs' => $datos['fin_fecha_entrega_docs'],
            'conv_fecha_inicio' => $datos['fecha_inicio'],
            'conv_fecha_fin' => $datos['fecha_fin'],
        ];
    }

    private function claveEstado(?string $estado): string
    {
        return match ($estado) {
            self::VIGENTE => 'vigente',
            self::CERRADA => 'cerrada',
            self::INTERRUMPIDA => 'interrumpida',
            default => 'pendiente',
        };
    }

    private function ventanaAbierta(mixed $inicio, mixed $fin): bool
    {
        $hoy = Carbon::now(config('app.timezone'))->toDateString();

        return $this->dia($inicio) <= $hoy && $hoy <= $this->dia($fin);
    }

    /**
     * PostgreSQL devuelve las fechas como 'Y-m-d' y SQLite puede añadirles la
     * hora; en los dos casos el día son los primeros diez caracteres.
     */
    private function dia(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return substr((string) $fecha, 0, 10);
    }
}
