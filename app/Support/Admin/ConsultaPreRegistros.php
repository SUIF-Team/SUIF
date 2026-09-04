<?php

namespace App\Support\Admin;

use App\Support\NombrePersona;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConsultaPreRegistros
{
    private const ROLES_PERSONA = ['Persona', 'Candidato'];

    /** En minúsculas: la comparación con el catálogo ignora mayúsculas. */
    private const ESTADOS_FILTRO = ['en revisión', 'aprobada', 'rechazada'];

    /**
     * Los desenlaces de un trámite: los únicos que trae el reporte de registros.
     *
     * Se escriben como en el catálogo porque estos tres no tienen variantes de
     * mayúsculas entre los scripts de la base; la que sí las tiene, «En
     * revisión», no está en la lista.
     */
    private const ESTADOS_RESUELTOS = ['Aprobada', 'Rechazada', 'Cancelada'];

    /**
     * Obtiene una fila por persona pre-registrada. La clave de acceso confirma
     * que concluyó la captura inicial, aunque la convocatoria haya terminado o
     * todavía no haya enviado su documentación a revisión.
     */
    public function bandeja(): array
    {
        return $this->consultaBase()
            ->orderByDesc('p.pers_fecha_registro')
            ->orderByDesc('s.soli_id_solicitud')
            ->get()
            ->map(fn (object $solicitud): array => $this->normalizarBandeja($solicitud))
            ->all();
    }

    /**
     * Reporte de los registros al sistema: un renglón por solicitud resuelta.
     *
     * La unidad es la solicitud y no la persona porque quien participa en dos
     * convocatorias se registró dos veces, y el reporte cuenta registros. Por
     * eso no pasa por ultimasSolicitudes(), que es justo lo contrario: deja
     * una sola solicitud por persona para la bandeja de revisión.
     *
     * Sólo trae los trámites que llegaron a un desenlace —Aprobada, Rechazada
     * o Cancelada—; quien sigue en Pre-registro o En revisión no aparece. El
     * reporte se lee en orden ascendente de folio, que es como se revisa un
     * expediente: el primer registro arriba. No se limita a convocatorias
     * vigentes: es histórico.
     *
     * @return array<int, array<string, string>>
     */
    public function todasLasSolicitudes(?int $id_convocatoria = null): array
    {
        return DB::table('solicitud as s')
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->join('usuario as u', 'u.usua_id_usuario', '=', 'p.pers_id_usuario')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->join('convocatoria as cv', 'cv.conv_id_convocatoria', '=', 's.soli_id_convocatoria')
            ->leftJoin('entidad_federativa as ef', 'ef.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            /* La sede se elige al final del trámite: la mayoría de los
               renglones no la tendrá, y eso es un dato del reporte. */
            ->leftJoin('evaluacion as ev', 'ev.eval_id_evaluacion', '=', 's.soli_id_evaluacion')
            ->leftJoin('grupo as gr', 'gr.grup_id_grupo', '=', 'ev.grup_id_grupo')
            ->leftJoin('sede as sd', 'sd.sede_id_sede', '=', 'gr.sede_id_sede')
            /* El estado vigente es el último renglón de la bitácora. La misma
               subconsulta que usa la bandeja: aquí sirve para publicarlo en la
               última columna y para dejar fuera los trámites sin resolver. */
            ->joinSub($this->ultimosEstados(), 'ultimo_estado', function ($join): void {
                $join->on('ultimo_estado.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'ultimo_estado.id_estado')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->whereIn('r.rol_tipo_rol', self::ROLES_PERSONA)
            ->whereNotNull('u.usua_clave_acceso')
            ->whereIn('ces.esso_estado_solicitud', self::ESTADOS_RESUELTOS)
            ->when(
                $id_convocatoria,
                fn (Builder $consulta): Builder => $consulta
                    ->where('s.soli_id_convocatoria', $id_convocatoria)
            )
            ->orderBy('s.soli_id_solicitud')
            ->select([
                's.soli_id_solicitud',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
                'p.pers_rfc',
                'p.pers_fecha_registro',
                'ef.enfe_entidad_federativa',
                'cv.conv_nombre',
                'sd.sede_nombre',
                'gr.grup_fecha_inicio',
                'gr.grup_hora_inicio',
                'gr.grup_hora_fin',
                'ces.esso_estado_solicitud as estado',
            ])
            ->get()
            ->map(fn (object $fila): array => [
                'folio' => (string) $fila->soli_id_solicitud,
                'curp' => (string) $fila->pers_curp,
                'nombre_completo' => $this->nombreCompleto($fila),
                'rfc' => trim((string) ($fila->pers_rfc ?? '')),
                'entidad_federativa' => (string) ($fila->enfe_entidad_federativa ?? ''),
                'fecha_registro' => (string) $fila->pers_fecha_registro,
                'convocatoria' => (string) ($fila->conv_nombre ?? ''),
                'sede' => (string) ($fila->sede_nombre ?? ''),
                'fecha_grupo' => (string) ($fila->grup_fecha_inicio ?? ''),
                'horario' => $fila->grup_hora_inicio
                    ? trim((string) $fila->grup_hora_inicio).' a '.trim((string) $fila->grup_hora_fin)
                    : '',
                'estado' => (string) $fila->estado,
            ])
            ->all();
    }

    /**
     * Estados con los que se puede filtrar la bandeja. Son los tres que le
     * importan a quien revisa; se toman del catálogo para conservar su
     * escritura exacta y se comparan sin distinguir mayúsculas, porque los
     * scripts de la base no coinciden entre sí («En revisión» / «En Revisión»).
     *
     * «Cancelada» quedó fuera: ya no se pueden cancelar trámites, así que
     * filtrar por ese estado sólo devolvería expedientes históricos.
     */
    public function estados(): array
    {
        return DB::table('c_estado_solicitud')
            ->orderBy('esso_id_c_estado_solicitud')
            ->pluck('esso_estado_solicitud')
            ->map(fn (mixed $estado): string => (string) $estado)
            ->filter(fn (string $estado): bool => in_array(
                mb_strtolower($estado),
                self::ESTADOS_FILTRO,
                true
            ))
            ->values()
            ->all();
    }

    /**
     * Resuelve un expediente de la misma población visible en la bandeja.
     */
    public function solicitud(int $id_solicitud): ?array
    {
        $solicitud = $this->consultaBase()
            ->where('s.soli_id_solicitud', $id_solicitud)
            ->first();

        if (!$solicitud) {
            return null;
        }

        $contactos = $this->contactos((int) $solicitud->pers_id_persona);
        $trabajo = $this->trabajo((int) $solicitud->pers_id_persona);

        return [
            'persona' => [
                'id' => (string) $solicitud->soli_id_solicitud,
                'nombre' => (string) $solicitud->pers_nombre,
                'primer_apellido' => (string) ($solicitud->pers_apellido_paterno ?? ''),
                'segundo_apellido' => (string) ($solicitud->pers_apellido_materno ?? ''),
                'nombre_completo' => $this->nombreCompleto($solicitud),
                'curp' => (string) $solicitud->pers_curp,
                'rfc' => (string) $solicitud->pers_rfc,
                'correo_principal' => $contactos['Correo principal'] ?? 'No registrado',
                'correo_alterno' => $contactos['Correo alterno'] ?? 'No registrado',
                'telefono' => $contactos['Teléfono celular'] ?? 'No registrado',
                'entidad_federativa' => $solicitud->enfe_entidad_federativa ?? 'No registrada',
                'ultimo_grado_estudios' => $this->ultimoGrado((int) $solicitud->pers_id_persona)
                    ?? 'No registrado',
                'actividad_vulnerable' => $trabajo
                    ? ($this->esVerdadero($trabajo->trab_actividad_vulnerable) ? 'Sí' : 'No')
                    : 'No registrado',
                'responsable_cumplimiento' => $trabajo
                    ? ($this->esVerdadero($trabajo->trab_responsable) ? 'Sí' : 'No')
                    : 'No registrado',
                'documentos' => $this->documentos((int) $solicitud->soli_id_solicitud),
            ],
            'estados' => $this->normalizarEstados((string) $solicitud->estado_solicitud),
        ];
    }

    /**
     * Localiza un documento de un expediente visible en la bandeja.
     * La ruta privada sólo se devuelve a quien servirá el archivo, nunca a la vista.
     */
    public function documento(int $id_solicitud, int $id_documento): ?array
    {
        $documento = DB::table('documento as d')
            ->joinSub($this->consultaBase(), 'solicitud_visible', function ($join): void {
                $join->on('solicitud_visible.soli_id_solicitud', '=', 'd.soli_id_solicitud');
            })
            ->where('d.soli_id_solicitud', $id_solicitud)
            ->where('d.docu_id_documento', $id_documento)
            ->select('d.docu_nombre', 'd.docu_path')
            ->first();

        if (!$documento) {
            return null;
        }

        return [
            'nombre' => (string) $documento->docu_nombre,
            'ruta_archivo' => (string) $documento->docu_path,
        ];
    }

    private function consultaBase(): Builder
    {
        return DB::table('solicitud as s')
            ->joinSub($this->ultimasSolicitudes(), 'ultima_solicitud', function ($join): void {
                $join->on('ultima_solicitud.id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('persona as p', 'p.pers_id_persona', '=', 's.soli_id_persona')
            ->join('usuario as u', 'u.usua_id_usuario', '=', 'p.pers_id_usuario')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->leftJoin('entidad_federativa as ef', 'ef.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            ->joinSub($this->ultimosEstados(), 'ultimo_estado', function ($join): void {
                $join->on('ultimo_estado.esso_id_solicitud', '=', 's.soli_id_solicitud');
            })
            ->join('estado_solicitud as es', 'es.esso_id_estado_solicitud', '=', 'ultimo_estado.id_estado')
            ->join('c_estado_solicitud as ces', 'ces.esso_id_c_estado_solicitud', '=', 'es.esso_id_c_estado_solicitud')
            ->whereIn('r.rol_tipo_rol', self::ROLES_PERSONA)
            ->whereNotNull('u.usua_clave_acceso')
            ->select([
                's.soli_id_solicitud',
                'p.pers_id_persona',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_curp',
                'p.pers_rfc',
                'p.pers_fecha_registro',
                'ef.enfe_entidad_federativa',
                'ces.esso_estado_solicitud as estado_solicitud',
            ]);
    }

    private function ultimasSolicitudes(): Builder
    {
        return DB::table('solicitud')
            ->whereNotNull('soli_id_persona')
            ->selectRaw('soli_id_persona, MAX(soli_id_solicitud) as id_solicitud')
            ->groupBy('soli_id_persona');
    }

    private function ultimosEstados(): Builder
    {
        return DB::table('estado_solicitud')
            ->selectRaw('esso_id_solicitud, MAX(esso_id_estado_solicitud) as id_estado')
            ->groupBy('esso_id_solicitud');
    }

    private function documentos(int $id_solicitud): array
    {
        return DB::table('documento as d')
            ->join('tipo_documento as td', 'td.tido_id_tipo_documento', '=', 'd.tido_id_tipo_documento')
            ->leftJoinSub($this->ultimosEstadosDocumentos(), 'ultimo_estado_documento', function ($join): void {
                $join->on('ultimo_estado_documento.esdo_id_documento', '=', 'd.docu_id_documento');
            })
            ->leftJoin('estado_documento as ed', 'ed.esdo_id_estado_documento', '=', 'ultimo_estado_documento.id_estado')
            ->leftJoin('c_estado_documento as ced', 'ced.esdo_id_c_estado_documento', '=', 'ed.esdo_id_c_estado_documento')
            ->where('d.soli_id_solicitud', $id_solicitud)
            ->orderBy('td.tido_id_tipo_documento')
            ->orderBy('d.docu_id_documento')
            ->select([
                'd.docu_id_documento',
                'd.docu_nombre',
                'd.docu_fecha_carga',
                'd.docu_hora_carga',
                'td.tido_tipo_documento',
                'ced.esdo_estado_documento',
                'ed.esdo_comentarios',
            ])
            ->get()
            ->map(fn (object $documento): array => [
                'id' => (string) $documento->docu_id_documento,
                'titulo' => (string) $documento->tido_tipo_documento,
                'nombre' => (string) $documento->docu_nombre,
                'fecha_carga' => trim(implode(' ', array_filter([
                    $documento->docu_fecha_carga,
                    $documento->docu_hora_carga,
                ]))),
                'estado' => $documento->esdo_estado_documento ?: 'Cargado',
                'comentario' => $documento->esdo_comentarios
                    ? (string) $documento->esdo_comentarios
                    : null,
            ])
            ->all();
    }

    private function ultimosEstadosDocumentos(): Builder
    {
        return DB::table('estado_documento')
            ->selectRaw('esdo_id_documento, MAX(esdo_id_estado_documento) as id_estado')
            ->groupBy('esdo_id_documento');
    }

    /**
     * Nombre para mostrar. Tanto la bandeja como el expediente lo exponen con
     * la misma llave: las pantallas de resultado leen `nombre_completo`.
     */
    private function nombreCompleto(object $solicitud): string
    {
        return NombrePersona::administrativo(
            $solicitud->pers_apellido_paterno,
            $solicitud->pers_apellido_materno,
            $solicitud->pers_nombre
        );
    }

    private function normalizarBandeja(object $solicitud): array
    {
        return [
            'id' => (string) $solicitud->soli_id_solicitud,
            'nombre' => (string) $solicitud->pers_nombre,
            'primer_apellido' => (string) ($solicitud->pers_apellido_paterno ?? ''),
            'segundo_apellido' => (string) ($solicitud->pers_apellido_materno ?? ''),
            'nombre_completo' => $this->nombreCompleto($solicitud),
            'curp' => (string) $solicitud->pers_curp,
            'rfc' => (string) $solicitud->pers_rfc,
            'fecha_registro' => $solicitud->pers_fecha_registro.' 00:00:00',
            'estado_bandeja' => (string) $solicitud->estado_solicitud,
            'clase_estado' => $this->claseEstado((string) $solicitud->estado_solicitud),
        ];
    }

    private function contactos(int $id_persona): array
    {
        $contactos = [];

        DB::table('comunicacion as co')
            ->join('tipo_comunicacion as tc', 'tc.tico_id_tipo_comunicacion', '=', 'co.comu_id_tipo_comunicacion')
            ->where('co.comu_id_persona', $id_persona)
            ->orderByDesc('co.comu_id_comunicacion')
            ->select('tc.tico_tipo_comunicacion', 'co.comu_descripcion')
            ->get()
            ->each(function (object $contacto) use (&$contactos): void {
                if (!isset($contactos[$contacto->tico_tipo_comunicacion])) {
                    $contactos[$contacto->tico_tipo_comunicacion] = $contacto->comu_descripcion;
                }
            });

        return $contactos;
    }

    private function ultimoGrado(int $id_persona): ?string
    {
        return DB::table('grado_persona as gp')
            ->join('nivel_profesional as np', 'np.nipr_id_nivel_profesional', '=', 'gp.grpe_id_nivel_profesional')
            ->where('gp.grpe_id_persona', $id_persona)
            ->orderByDesc('gp.grpe_id_grado_persona')
            ->value('np.nipr_nivel_profesional');
    }

    private function trabajo(int $id_persona): ?object
    {
        return DB::table('trabajo_persona as tp')
            ->join('trabajo as t', 't.trab_id_trabajo', '=', 'tp.trpe_id_trabajo')
            ->where('tp.trpe_id_persona', $id_persona)
            ->orderByDesc('tp.trpe_id_trabajo_persona')
            ->select('t.trab_actividad_vulnerable', 't.trab_responsable')
            ->first();
    }

    private function normalizarEstados(string $estado_solicitud): array
    {
        return match ($estado_solicitud) {
            'Aprobada' => [
                'general' => 'Aprobada',
                'preregistro' => 'Completado',
                'documentacion' => 'Completado',
            ],
            'Rechazada' => [
                'general' => 'Rechazada',
                'preregistro' => 'Rechazado',
                'documentacion' => 'Pendiente',
            ],
            /* Ya no se cancelan trámites, pero el historial conserva los que se
               cancelaron: el pre-registro sigue completado y lo que quedó
               detenido es la documentación. */
            'Cancelada' => [
                'general' => 'Cancelada',
                'preregistro' => 'Completado',
                'documentacion' => 'Cancelado',
            ],
            'En revisión', 'En Revisión' => [
                'general' => 'En revisión',
                'preregistro' => 'Completado',
                'documentacion' => 'En revisión',
            ],
            default => [
                'general' => $estado_solicitud,
                'preregistro' => 'Completado',
                'documentacion' => 'Pendiente',
            ],
        };
    }

    private function claseEstado(string $estado_solicitud): string
    {
        return match ($estado_solicitud) {
            'Aprobada' => 'admin-bandeja-preregistros-estado-aceptado',
            'Rechazada', 'Cancelada' => 'admin-bandeja-preregistros-estado-rechazado',
            default => 'admin-bandeja-preregistros-estado-revision',
        };
    }

    private function esVerdadero(mixed $valor): bool
    {
        return in_array($valor, [true, 1, '1', 't', 'true'], true);
    }
}
