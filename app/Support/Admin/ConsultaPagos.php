<?php

namespace App\Support\Admin;

use App\Servicios\ComprobanteFiscal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConsultaPagos
{
    public const PENDIENTE = 'Pendiente';

    public const COMPLETADO = 'Completado';

    public const DECLINADO = 'Declinado';

    private const ROLES_PERSONA = ['Persona', 'Candidato'];

    /**
     * Devuelve todos los comprobantes registrados para personas solicitantes.
     */
    public function bandeja(): array
    {
        return $this->consultaBase()
            ->get()
            ->map(fn (object $pago): array => $this->normalizar($pago))
            ->sortByDesc('fecha_envio_comprobante')
            ->values()
            ->all();
    }

    /**
     * Busca un pago dentro de la misma población visible en la bandeja.
     */
    public function pago(int $id_pago): ?array
    {
        /* Los datos fiscales sólo se traen aquí: la bandeja serializa cada
           renglón en el HTML y no tiene por qué llevar el RFC y el correo de
           todas las personas. Los dos joins son contra llaves primarias, así
           que no multiplican renglones. */
        $pago = $this->consultaBase()
            ->leftJoin('dato_fiscal as df', 'df.dafi_id_dato_fiscal', '=', 'pg.pago_id_dato_fiscal')
            ->leftJoin('regimen_fiscal as rf', 'rf.refi_id_regimen_fiscal', '=', 'df.dafi_id_regimen_fiscal')
            ->leftJoinSub($this->correosDeFacturacion(), 'cf', function ($join): void {
                $join->on('cf.comu_id_persona', '=', 'p.pers_id_persona');
            })
            ->addSelect([
                'df.dafi_razon_social',
                'df.dafi_rfc',
                'df.dafi_persona_moral',
                'df.dafi_id_codigo_postal',
                'rf.refi_regimen_fiscal',
                'cf.comu_descripcion as correo_facturacion',
            ])
            ->where('pg.pago_id_pago', $id_pago)
            ->first();

        return $pago ? $this->normalizar($pago, true) : null;
    }

    /**
     * Reporte de quienes ya pagaron su referencia bancaria.
     *
     * Pagado significa que el último renglón de la bitácora es «Completado»,
     * es decir, que la DEC revisó el comprobante y lo aprobó. Un comprobante
     * enviado y todavía en revisión no es dinero confirmado y no entra.
     *
     * Vive aquí y no en un servicio de reportes aparte porque consultaBase()
     * ya une pago, persona, rol, entidad, referencia y las tres bitácoras que
     * hacen falta; repetir esos joins en otra clase sería copiarlos.
     *
     * No reutiliza normalizar(): ese método pregunta al disco por el archivo
     * de cada comprobante, y en un reporte de cientos de renglones eso son
     * cientos de accesos a disco para un dato que aquí no se usa.
     *
     * @return array<int, array<string, string|float|null>>
     */
    public function pagadas(?int $id_convocatoria = null): array
    {
        $consulta = $this->consultaBase()
            ->where('cep.esta_estado_pago', self::COMPLETADO);

        return $this->conConvocatoriaYSede($consulta, $id_convocatoria)
            ->addSelect([
                'ep.espa_fecha as fecha_validacion',
                'ep.espa_hora as hora_validacion',
            ])
            ->get()
            ->map(fn (object $fila): array => $this->normalizarPersonaDeReporte($fila) + [
                'referencia_bancaria' => (string) $fila->pago_referencia_bancaria,
                /* Dos cifras distintas a propósito: lo que se cobró contra lo
                   que la persona declaró haber pagado. Su diferencia es justo
                   lo que se revisa al cortar caja, así que van las dos y como
                   número, para que se puedan sumar en la hoja. */
                'monto_cobrado' => $fila->reba_monto === null ? null : (float) $fila->reba_monto,
                'monto_declarado' => (float) $fila->pago_monto_pagado,
                'fecha_pago' => (string) $fila->pago_fecha_pago,
                'fecha_validacion' => trim(
                    (string) $fila->fecha_validacion.' '.(string) $fila->hora_validacion
                ),
            ])
            ->all();
    }

    /**
     * Reporte de quienes pidieron CFDI, con los datos con que se les factura.
     *
     * Elegir CFDI y capturar los datos fiscales son dos pasos separados, así
     * que quien eligió y todavía no captura aparece igual, con las columnas
     * fiscales vacías: el reporte sirve para saber a quién le falta completar
     * antes de poder facturarle. De ahí que los joins fiscales sean todos
     * leftJoin y no join.
     *
     * @return array<int, array<string, string|float|null>>
     */
    public function solicitudesCfdi(?int $id_convocatoria = null): array
    {
        $consulta = $this->consultaBase()
            ->where('pg.pago_uso_cfdi', true)
            ->leftJoin('dato_fiscal as df', 'df.dafi_id_dato_fiscal', '=', 'pg.pago_id_dato_fiscal')
            ->leftJoin('regimen_fiscal as rf', 'rf.refi_id_regimen_fiscal', '=', 'df.dafi_id_regimen_fiscal')
            ->leftJoinSub($this->correosDeFacturacion(), 'cf', function ($join): void {
                $join->on('cf.comu_id_persona', '=', 'p.pers_id_persona');
            })
            ->addSelect([
                'df.dafi_razon_social',
                'df.dafi_rfc',
                'df.dafi_persona_moral',
                'df.dafi_id_codigo_postal',
                'rf.refi_regimen_fiscal',
                'cf.comu_descripcion as correo_facturacion',
            ]);

        return $this->conConvocatoriaYSede($consulta, $id_convocatoria)
            ->get()
            ->map(function (object $fila): array {
                $fiscales = $this->datosFiscales($fila);

                return $this->normalizarPersonaDeReporte($fila) + [
                    'referencia_bancaria' => (string) $fila->pago_referencia_bancaria,
                    'monto_declarado' => (float) $fila->pago_monto_pagado,
                    'fecha_pago' => (string) $fila->pago_fecha_pago,
                    'razon_social' => $fiscales['razon_social'] ?? '',
                    'rfc_fiscal' => $fiscales['rfc'] ?? '',
                    'tipo_persona' => $fiscales['tipo_persona'] ?? '',
                    'regimen_fiscal' => $fiscales['regimen_fiscal'] ?? '',
                    'codigo_postal' => $fiscales['codigo_postal'] ?? '',
                    'correo_facturacion' => $fiscales['correo'] ?? '',
                    'captura' => $fiscales === null ? 'Pendiente de capturar' : 'Completa',
                ];
            })
            ->all();
    }

    /**
     * Convocatoria, sede y horario: lo que los dos reportes necesitan y la
     * bandeja no, más el filtro opcional por convocatoria.
     *
     * La sede va con leftJoin porque se elige después de pagar: quien acaba de
     * pagar todavía no tiene grupo y debe aparecer en el reporte igual.
     */
    private function conConvocatoriaYSede(Builder $consulta, ?int $id_convocatoria): Builder
    {
        return $consulta
            ->join('convocatoria as cv', 'cv.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->leftJoin('evaluacion as ev', 'ev.eval_id_evaluacion', '=', 's.soli_id_evaluacion')
            ->leftJoin('grupo as gr', 'gr.grup_id_grupo', '=', 'ev.grup_id_grupo')
            ->leftJoin('sede as sd', 'sd.sede_id_sede', '=', 'gr.sede_id_sede')
            ->when(
                $id_convocatoria,
                fn (Builder $filtrada): Builder => $filtrada
                    ->where('s.soli_id_convocatoria', $id_convocatoria)
            )
            ->addSelect([
                'cv.conv_nombre',
                'sd.sede_nombre',
                'gr.grup_fecha_inicio',
                'gr.grup_hora_inicio',
                'gr.grup_hora_fin',
            ])
            ->orderBy('p.pers_apellido_paterno')
            ->orderBy('p.pers_apellido_materno')
            ->orderBy('p.pers_nombre');
    }

    /**
     * Las columnas que comparten los dos reportes de pagos.
     *
     * @return array<string, string>
     */
    private function normalizarPersonaDeReporte(object $fila): array
    {
        $horario = $fila->grup_hora_inicio
            ? trim((string) $fila->grup_hora_inicio).' a '.trim((string) $fila->grup_hora_fin)
            : '';

        return [
            'curp' => (string) $fila->pers_curp,
            'nombre_completo' => trim(implode(' ', array_filter([
                $fila->pers_nombre,
                $fila->pers_apellido_paterno,
                $fila->pers_apellido_materno,
            ]))),
            'entidad_federativa' => (string) ($fila->enfe_entidad_federativa ?? ''),
            'convocatoria' => (string) ($fila->conv_nombre ?? ''),
            'sede' => (string) ($fila->sede_nombre ?? ''),
            'fecha_grupo' => (string) ($fila->grup_fecha_inicio ?? ''),
            'horario' => $horario,
        ];
    }

    /**
     * Comprobantes enviados que siguen esperando decisión. Se cuenta sobre la
     * misma consulta de la bandeja para que el indicador del dashboard y lo
     * que ahí se ve como «Por revisar» no se puedan desincronizar; se resuelve
     * en SQL porque normalizar cada fila toca el disco.
     */
    public function totalPorValidar(): int
    {
        return $this->consultaBase()
            ->where(function (Builder $consulta): void {
                $consulta->where('cep.esta_estado_pago', self::PENDIENTE)
                    ->orWhereNull('cep.esta_estado_pago');
            })
            ->count();
    }

    public function archivoDisponible(?string $ruta): bool
    {
        return $this->rutaArchivoValida($ruta)
            && Storage::disk('comprobantes')->exists($ruta);
    }

    public function rutaArchivoValida(?string $ruta): bool
    {
        if (!is_string($ruta) || $ruta === '' || str_starts_with($ruta, '/')) {
            return false;
        }

        return !str_contains($ruta, '..') && str_ends_with(mb_strtolower($ruta), '.pdf');
    }

    private function consultaBase(): Builder
    {
        return DB::table('pago as pg')
            ->join('solicitud as s', 's.soli_id_pago', '=', 'pg.pago_id_pago')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->join('usuario as u', 'u.usua_id_usuario', '=', 'p.pers_id_usuario')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->leftJoin('entidad_federativa as ef', 'ef.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            /* El renglón del catálogo trae el monto que se cobró; PAGO guarda
               el que la persona declaró haber pagado. */
            ->leftJoin('referencia_bancaria as rb', 'rb.reba_id_pago', '=', 'pg.pago_id_pago')
            ->leftJoinSub($this->ultimosEstadosPago(), 'estado_actual', function ($join): void {
                $join->on('estado_actual.espa_id_pago', '=', 'pg.pago_id_pago');
            })
            ->leftJoin('estado_pago as ep', 'ep.espa_id_estado_pago', '=', 'estado_actual.id_estado')
            ->leftJoin('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'ep.espa_id_c_estado_pago')
            ->leftJoinSub($this->ultimosEstadosPendiente(), 'ultimo_envio', function ($join): void {
                $join->on('ultimo_envio.espa_id_pago', '=', 'pg.pago_id_pago');
            })
            ->leftJoin('estado_pago as envio', 'envio.espa_id_estado_pago', '=', 'ultimo_envio.id_estado')
            ->leftJoinSub($this->primerosEstadosPago(), 'primer_estado', function ($join): void {
                $join->on('primer_estado.espa_id_pago', '=', 'pg.pago_id_pago');
            })
            ->leftJoin('estado_pago as primer', 'primer.espa_id_estado_pago', '=', 'primer_estado.id_estado')
            ->leftJoinSub($this->ultimosEstadosSolicitud(), 'estado_solicitud_actual', function ($join): void {
                $join->on('estado_solicitud_actual.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->leftJoin('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'estado_solicitud_actual.id_estado')
            ->leftJoin('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->whereIn('r.rol_tipo_rol', self::ROLES_PERSONA)
            ->whereNotNull('pg.pago_comprobante_path')
            ->where('pg.pago_comprobante_path', '<>', '')
            ->select([
                'pg.pago_id_pago',
                'pg.pago_comprobante_path',
                'pg.pago_monto_pagado',
                'rb.reba_monto',
                'pg.pago_referencia_bancaria',
                'pg.pago_fecha_pago',
                'pg.pago_hora_pago',
                'pg.pago_uso_cfdi',
                'pg.pago_id_dato_fiscal',
                's.soli_id_solicitud',
                'p.pers_id_persona',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
                'ef.enfe_entidad_federativa',
                'cep.esta_estado_pago',
                'ep.espa_comentario',
                'envio.espa_fecha as fecha_envio',
                'envio.espa_hora as hora_envio',
                'primer.espa_fecha as fecha_primer_estado',
                'primer.espa_hora as hora_primer_estado',
                'ces.esso_estado_solicitud as estado_solicitud',
            ]);
    }

    private function ultimosEstadosPago(): Builder
    {
        return DB::table('estado_pago')
            ->selectRaw('espa_id_pago, MAX(espa_id_estado_pago) as id_estado')
            ->groupBy('espa_id_pago');
    }

    private function primerosEstadosPago(): Builder
    {
        return DB::table('estado_pago')
            ->selectRaw('espa_id_pago, MIN(espa_id_estado_pago) as id_estado')
            ->groupBy('espa_id_pago');
    }

    private function ultimosEstadosPendiente(): Builder
    {
        return DB::table('estado_pago as esp')
            ->join('c_estado_pago as cep', 'cep.espa_id_c_estado_pago', '=', 'esp.espa_id_c_estado_pago')
            ->where('cep.esta_estado_pago', self::PENDIENTE)
            ->selectRaw('esp.espa_id_pago, MAX(esp.espa_id_estado_pago) as id_estado')
            ->groupBy('esp.espa_id_pago');
    }

    /**
     * El correo al que va el CFDI. Vive en COMUNICACION con su propio tipo,
     * porque puede no ser el correo con el que la persona entra al sistema.
     */
    private function correosDeFacturacion(): Builder
    {
        return DB::table('comunicacion as co')
            ->join('tipo_comunicacion as tc', 'tc.tico_id_tipo_comunicacion', '=', 'co.comu_id_tipo_comunicacion')
            ->where('tc.tico_tipo_comunicacion', 'Correo facturación')
            ->select('co.comu_id_persona', 'co.comu_descripcion');
    }

    private function ultimosEstadosSolicitud(): Builder
    {
        return DB::table('estado_solicitud')
            ->selectRaw('esso_id_solicitud, MAX(esso_id_estado_solicitud) as id_estado')
            ->groupBy('esso_id_solicitud');
    }

    private function normalizar(object $pago, bool $con_datos_fiscales = false): array
    {
        $estado_persistido = (string) ($pago->esta_estado_pago ?: self::PENDIENTE);
        $estatus = $this->etiquetaEstado($estado_persistido);
        $archivo_disponible = $this->archivoDisponible($pago->pago_comprobante_path);
        $estado_solicitud = (string) ($pago->estado_solicitud ?: 'Pendiente');
        $puede_revisarse = $estado_persistido === self::PENDIENTE
            && $archivo_disponible
            && $estado_solicitud === 'Aprobada';

        $fecha_envio = $this->fechaHora(
            $pago->fecha_envio,
            $pago->hora_envio,
            $pago->fecha_primer_estado,
            $pago->hora_primer_estado,
            $pago->pago_fecha_pago,
            $pago->pago_hora_pago
        );

        $nombre = trim(implode(' ', array_filter([
            $pago->pers_nombre,
            $pago->pers_apellido_paterno,
            $pago->pers_apellido_materno,
        ])));

        return [
            'id' => (string) $pago->pago_id_pago,
            'id_solicitud' => (string) $pago->soli_id_solicitud,
            'nombre' => (string) $pago->pers_nombre,
            'primer_apellido' => (string) ($pago->pers_apellido_paterno ?? ''),
            'segundo_apellido' => (string) ($pago->pers_apellido_materno ?? ''),
            'nombre_completo' => $nombre,
            'iniciales' => $this->iniciales($pago->pers_nombre, $pago->pers_apellido_paterno),
            'curp' => (string) $pago->pers_curp,
            'entidad_federativa' => (string) ($pago->enfe_entidad_federativa ?? 'Sin información'),
            'estatus' => $estatus,
            'estado_persistido' => $estado_persistido,
            'clase_estado' => $this->claseEstado($estado_persistido),
            'clase_estado_detalle' => $this->claseEstadoDetalle($estado_persistido),
            'fecha_envio_comprobante' => $fecha_envio,
            'estado_preregistro' => $this->estadoPreRegistro($estado_solicitud),
            'estado_documentacion' => $this->estadoDocumentacion($estado_solicitud),
            'clase_paso_preregistro' => $this->clasePaso($this->estadoPreRegistro($estado_solicitud)),
            'clase_paso_documentacion' => $this->clasePaso($this->estadoDocumentacion($estado_solicitud)),
            'clase_paso_pago' => $this->clasePaso($estatus),
            'comprobante' => [
                'nombre' => basename((string) $pago->pago_comprobante_path),
            ],
            'comprobante_disponible' => $archivo_disponible,
            /* Lo que la persona declaró contra lo que se le cobró: sin las dos
               cifras no hay forma de revisar el comprobante. */
            'monto' => '$'.number_format((float) $pago->pago_monto_pagado, 2).' '.config('suif.moneda', 'MXN'),
            'monto_referencia' => $pago->reba_monto === null
                ? null
                : '$'.number_format((float) $pago->reba_monto, 2).' '.config('suif.moneda', 'MXN'),
            'referencia_bancaria' => (string) $pago->pago_referencia_bancaria,
            'fecha_pago' => (string) $pago->pago_fecha_pago,
            'hora_pago' => (string) $pago->pago_hora_pago,
            'motivo_rechazo' => $estado_persistido === self::DECLINADO
                ? trim((string) $pago->espa_comentario)
                : null,
            /* Lo que la persona pidió: es opcional, así que no haberlo
               elegido es un resultado válido y no una omisión. */
            'comprobante_solicitado' => $this->etiquetaComprobante($pago->pago_uso_cfdi),
            'datos_fiscales' => $con_datos_fiscales ? $this->datosFiscales($pago) : null,
            'puede_revisarse' => $puede_revisarse,
            'mensaje_revision_no_disponible' => $this->mensajeNoDisponible(
                $estado_persistido,
                $archivo_disponible,
                $estado_solicitud
            ),
        ];
    }

    private function fechaHora(
        mixed $fecha_envio,
        mixed $hora_envio,
        mixed $fecha_primer_estado,
        mixed $hora_primer_estado,
        mixed $fecha_pago,
        mixed $hora_pago
    ): string {
        $fecha = $fecha_envio ?: ($fecha_primer_estado ?: $fecha_pago);
        $hora = $hora_envio ?: ($hora_primer_estado ?: $hora_pago);

        return trim((string) $fecha.' '.(string) $hora);
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            self::COMPLETADO => 'Aprobado',
            self::DECLINADO => 'Rechazado',
            default => 'Por revisar',
        };
    }

    private function claseEstado(string $estado): string
    {
        return match ($estado) {
            self::COMPLETADO => 'admin-bandeja-preregistros-estado-aceptado',
            self::DECLINADO => 'admin-bandeja-preregistros-estado-rechazado',
            default => 'admin-bandeja-preregistros-estado-revision',
        };
    }

    private function claseEstadoDetalle(string $estado): string
    {
        return match ($estado) {
            self::COMPLETADO => 'admin-preregistro-estado--completado',
            self::DECLINADO => 'admin-preregistro-estado--rechazado',
            default => 'admin-preregistro-estado--revision',
        };
    }

    private function estadoPreRegistro(string $estado_solicitud): string
    {
        return in_array($estado_solicitud, ['Rechazada', 'Cancelada'], true)
            ? 'Rechazado'
            : 'Completado';
    }

    private function estadoDocumentacion(string $estado_solicitud): string
    {
        return $estado_solicitud === 'Aprobada' ? 'Completado' : 'En revisión';
    }

    private function clasePaso(string $estado): string
    {
        return match ($estado) {
            'Completado', 'Aprobado' => 'admin-preregistro-paso--completado',
            'Rechazado' => 'admin-preregistro-paso--rechazado',
            'Por revisar', 'En revisión' => 'admin-preregistro-paso--actual',
            default => 'admin-preregistro-paso--pendiente',
        };
    }

    private function mensajeNoDisponible(
        string $estado_persistido,
        bool $archivo_disponible,
        string $estado_solicitud
    ): ?string {
        if ($estado_persistido !== self::PENDIENTE) {
            return 'El pago ya fue resuelto y sólo puede consultarse.';
        }

        if (!$archivo_disponible) {
            return 'El archivo del comprobante no está disponible para revisión.';
        }

        if ($estado_solicitud !== 'Aprobada') {
            return 'La solicitud aún no cumple los requisitos previos para revisar el pago.';
        }

        return null;
    }

    private function etiquetaComprobante(mixed $uso_cfdi): string
    {
        return match (ComprobanteFiscal::normalizarUsoCfdi($uso_cfdi)) {
            true => 'CFDI',
            false => 'Ticket',
            default => 'Sin solicitar',
        };
    }

    /**
     * Los datos con los que se emitirá el CFDI. Null mientras la persona no
     * los capture, aunque ya haya elegido la opción.
     */
    private function datosFiscales(object $pago): ?array
    {
        if ($pago->pago_id_dato_fiscal === null) {
            return null;
        }

        return [
            'razon_social' => (string) $pago->dafi_razon_social,
            /* DAFI_PERSONA_MORAL es BOOL y cada motor lo devuelve a su
               manera; se lee con el mismo normalizador que el uso de CFDI. */
            'tipo_persona' => ComprobanteFiscal::normalizarUsoCfdi($pago->dafi_persona_moral)
                ? 'Moral'
                : 'Física',
            'regimen_fiscal' => (string) ($pago->refi_regimen_fiscal ?? 'Sin información'),
            /* La columna es CHAR(5) y PostgreSQL la devuelve con relleno. */
            'codigo_postal' => trim((string) $pago->dafi_id_codigo_postal),
            'rfc' => (string) $pago->dafi_rfc,
            'correo' => trim((string) $pago->correo_facturacion) ?: 'Sin registro',
        ];
    }

    private function iniciales(?string $nombre, ?string $apellido): string
    {
        return mb_strtoupper(
            mb_substr((string) $nombre, 0, 1)
            .mb_substr((string) $apellido, 0, 1)
        );
    }
}
