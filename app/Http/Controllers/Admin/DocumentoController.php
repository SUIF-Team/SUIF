<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ConsultaPreRegistros;
use App\Support\Admin\NotificacionResultado;
use App\Support\Admin\OrigenBandejaAdmin;
use App\Support\Admin\RevisionDocumentos;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Admin\DocumentacionController
 *
 * Migrado desde: app/controllers/admin/DocumentacionController.php
 * Responsabilidad: revisión y validación de documentación de personas por el administrador.
 */
class DocumentoController extends Controller
{
    /**
     * Muestra la pantalla base del módulo de documentos.
     */
    public function index()
    {
        return view('admin.documentos');
    }

    /**
     * Sirve un PDF cargado para un expediente visible en la bandeja.
     */
    public function ver(string $solicitud, string $documento, ConsultaPreRegistros $consulta_pre_registros)
    {
        abort_unless(ctype_digit($solicitud) && ctype_digit($documento), 404);

        $archivo_documento = $consulta_pre_registros->documento(
            (int) $solicitud,
            (int) $documento
        );

        abort_unless($archivo_documento && $this->esRutaPdfValida($archivo_documento['ruta_archivo']), 404);

        $disco = Storage::disk('local');
        $ruta_archivo = $archivo_documento['ruta_archivo'];

        abort_unless($disco->exists($ruta_archivo), 404);

        return response()->file($disco->path($ruta_archivo), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->nombreSeguro($archivo_documento['nombre']).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function show(
        Request $request,
        string $id,
        ConsultaPreRegistros $consulta_pre_registros,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        $expediente = $this->expedienteReal($id, $consulta_pre_registros);
        $resultado = $this->resultadoReal($expediente);

        if (in_array($resultado, [RevisionDocumentos::APROBADO, RevisionDocumentos::RECHAZADO], true)) {
            return redirect()->route('admin.documentos.resultado', [
                'id' => $id,
                'origen' => $contexto_bandeja['origen'],
            ]);
        }

        $persona = $this->agregarRutasVisor($expediente['persona'], $id);
        $estados = $expediente['estados'];
        $modo_solo_lectura = $resultado === RevisionDocumentos::REVISION;
        $decisiones_documentos = $modo_solo_lectura
            ? $this->decisionesDocumentos($persona['documentos'])
            : [];
        $observaciones_rechazo = $modo_solo_lectura
            ? $this->observacionesRechazo($persona['documentos'])
            : ['comentarios' => [], 'fecha_limite' => ''];

        return view('admin.preregistro-documentacion', compact(
            'persona',
            'estados',
            'contexto_bandeja',
            'modo_solo_lectura',
            'decisiones_documentos',
            'observaciones_rechazo'
        ));
    }

    public function validar(
        Request $request,
        string $id,
        ConsultaPreRegistros $consulta_pre_registros,
        RevisionDocumentos $revision_documentos,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        $expediente = $this->expedienteReal($id, $consulta_pre_registros);

        if ($this->resultadoReal($expediente)) {
            return $this->redirigirResultado($id, $contexto_bandeja)
                ->with('warning', 'La documentación ya fue resuelta y no puede guardarse nuevamente.');
        }

        $documentos = $request->input('documentos', []);
        /* Sólo se resuelven los documentos que esperan revisión; los aprobados
           en una revisión anterior ya no vuelven a decidirse. */
        $documentos_requeridos = array_map('strval', array_column(
            array_filter(
                $expediente['persona']['documentos'],
                fn (array $documento): bool => $documento['estado'] === 'En revisión'
            ),
            'id'
        ));

        $validador = Validator::make($request->all(), [
            'documentos' => ['required', 'array'],
            'documentos.*' => ['required', 'in:aprobado,rechazado'],
            'comentarios' => ['nullable', 'array'],
            'comentarios.*' => ['nullable', 'string', 'max:500'],
            'fecha_limite' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $validador->after(function ($validador) use ($documentos, $documentos_requeridos, $request): void {
            if (!is_array($documentos)) {
                return;
            }

            $documentos_recibidos = array_map('strval', array_keys($documentos));
            sort($documentos_requeridos);
            sort($documentos_recibidos);

            if ($documentos_requeridos !== $documentos_recibidos) {
                $validador->errors()->add(
                    'documentos',
                    'Resuelve exactamente los documentos que esperan revisión antes de guardar.'
                );
            }

            $comentarios = $request->input('comentarios', []);

            foreach ($documentos as $id_documento => $decision) {
                if ($decision !== RevisionDocumentos::RECHAZADO) {
                    continue;
                }

                if (trim((string) (is_array($comentarios) ? ($comentarios[$id_documento] ?? '') : '')) === '') {
                    $validador->errors()->add(
                        'comentarios.'.$id_documento,
                        'Escribe el motivo del rechazo de este documento.'
                    );
                }
            }

            if (in_array(RevisionDocumentos::RECHAZADO, $documentos, true)
                && trim((string) $request->input('fecha_limite')) === '') {
                $validador->errors()->add('fecha_limite', 'Indica la fecha límite.');
            }
        });

        if ($validador->fails()) {
            return $this->redirigirRevision($id, $contexto_bandeja)
                ->withErrors($validador)
                ->withInput();
        }

        try {
            $revision_documentos->resolver(
                (int) $id,
                $documentos,
                (array) $request->input('comentarios', []),
                $request->input('fecha_limite')
            );
        } catch (DomainException $exception) {
            return $this->redirigirRevision($id, $contexto_bandeja)
                ->with('warning', $exception->getMessage());
        }

        return $this->redirigirResultado($id, $contexto_bandeja)
            ->with('success', 'La revisión documental se guardó correctamente.');
    }

    public function interrumpir(
        Request $request,
        string $id,
        ConsultaPreRegistros $consulta_pre_registros,
        RevisionDocumentos $revision_documentos,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        $expediente = $this->expedienteReal($id, $consulta_pre_registros);

        if ($this->resultadoReal($expediente)) {
            return $this->redirigirResultado($id, $contexto_bandeja)
                ->with('warning', 'La solicitud ya fue resuelta y no puede interrumpirse nuevamente.');
        }

        $datos = $request->validate([
            'motivo_rechazo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $revision_documentos->interrumpir((int) $id, $datos['motivo_rechazo'] ?? null);
        } catch (DomainException $exception) {
            return $this->redirigirRevision($id, $contexto_bandeja)
                ->with('warning', $exception->getMessage());
        }

        return $this->redirigirResultado($id, $contexto_bandeja)
            ->with('success', 'La solicitud se rechazó y el historial fue actualizado.');
    }

    public function resultado(
        Request $request,
        string $id,
        ConsultaPreRegistros $consulta_pre_registros,
        NotificacionResultado $notificacion_resultado,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto($request->input('origen'));
        $expediente = $this->expedienteReal($id, $consulta_pre_registros);
        $resultado = $this->resultadoReal($expediente);

        if (!$resultado) {
            return redirect()->route('admin.documentos.show', [
                'id' => $id,
                'origen' => $contexto_bandeja['origen'],
            ]);
        }

        $estados = $expediente['estados'];
        $estados['resultado'] = $resultado;

        if ($resultado === RevisionDocumentos::RECHAZADO) {
            $estados['preregistro'] = 'Completado';
            $estados['documentacion'] = 'Rechazado';
        } elseif ($resultado === RevisionDocumentos::REVISION) {
            $estados['preregistro'] = 'Completado';
            $estados['documentacion'] = 'En revisión';
        }

        $notificacion = $notificacion_resultado->paraPreRegistro($expediente['persona'], $estados);
        $notificacion = array_merge($notificacion, [
            'ruta_regreso' => $contexto_bandeja['ruta'],
            'etiqueta_regreso' => 'Volver a la bandeja',
            'etiqueta_regreso_accesible' => 'Volver a la bandeja',
        ]);

        return view('admin.notificacion-resultado', [
            'persona' => $notificacion['persona'],
            'notificacion' => $notificacion,
        ]);
    }

    private function expedienteReal(string $id, ConsultaPreRegistros $consulta_pre_registros): array
    {
        abort_unless(ctype_digit($id), 404);

        $expediente = $consulta_pre_registros->solicitud((int) $id);

        abort_unless($expediente, 404);

        return $expediente;
    }

    private function agregarRutasVisor(array $persona, string $id_solicitud): array
    {
        $persona['documentos'] = collect($persona['documentos'])
            ->map(function (array $documento) use ($id_solicitud): array {
                $documento['ruta_visor'] = route('admin.personas.documentos.ver', [
                    'solicitud' => $id_solicitud,
                    'documento' => $documento['id'],
                ]);
                // Los aprobados en una revisión anterior se muestran sin decidir.
                $documento['pendiente'] = $documento['estado'] === 'En revisión';

                return $documento;
            })
            ->all();

        return $persona;
    }

    private function resultadoReal(array $expediente): ?string
    {
        return match ($expediente['estados']['general']) {
            'Aprobada' => RevisionDocumentos::APROBADO,
            'Rechazada' => RevisionDocumentos::RECHAZADO,
            default => collect($expediente['persona']['documentos'])
                ->contains(fn (array $documento): bool => $documento['estado'] === 'Rechazado')
                    ? RevisionDocumentos::REVISION
                    : null,
        };
    }

    private function decisionesDocumentos(array $documentos): array
    {
        return collect($documentos)
            ->mapWithKeys(function (array $documento): array {
                $decision = match ($documento['estado']) {
                    'Aprobado' => RevisionDocumentos::APROBADO,
                    'Rechazado' => RevisionDocumentos::RECHAZADO,
                    default => null,
                };

                return $decision ? [$documento['id'] => $decision] : [];
            })
            ->all();
    }

    /**
     * Separa el comentario de cada documento rechazado de la fecha límite que
     * comparte todo el expediente.
     */
    private function observacionesRechazo(array $documentos): array
    {
        $comentarios = [];
        $fecha_limite = '';

        foreach ($documentos as $documento) {
            if ($documento['estado'] !== 'Rechazado') {
                continue;
            }

            $comentario = (string) ($documento['comentario'] ?? '');

            if (preg_match('/\\RFecha límite: (\\d{4}-\\d{2}-\\d{2})$/u', $comentario, $coincidencias)) {
                $comentario = rtrim(substr($comentario, 0, -strlen($coincidencias[0])));
                $fecha_limite = $coincidencias[1];
            }

            $comentarios[$documento['id']] = $comentario;
        }

        return ['comentarios' => $comentarios, 'fecha_limite' => $fecha_limite];
    }

    private function redirigirRevision(string $id, array $contexto_bandeja)
    {
        return redirect()->route('admin.documentos.show', [
            'id' => $id,
            'origen' => $contexto_bandeja['origen'],
        ]);
    }

    private function redirigirResultado(string $id, array $contexto_bandeja)
    {
        return redirect()->route('admin.documentos.resultado', [
            'id' => $id,
            'origen' => $contexto_bandeja['origen'],
        ]);
    }

    private function esRutaPdfValida(string $ruta): bool
    {
        return str_starts_with($ruta, 'preregistro/cargas/')
            && !str_contains($ruta, '..')
            && strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function nombreSeguro(string $nombre): string
    {
        $nombre_seguro = preg_replace(
            '/[^A-Za-z0-9 ._-]/',
            '_',
            Str::ascii(basename($nombre))
        );

        if (!$nombre_seguro || $nombre_seguro === '.pdf') {
            return 'documento.pdf';
        }

        return str_ends_with(strtolower($nombre_seguro), '.pdf')
            ? $nombre_seguro
            : $nombre_seguro.'.pdf';
    }
}
