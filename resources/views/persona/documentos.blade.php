@extends('layouts.persona')

@section('title', 'SUIF — Documentación')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-preregistro.css') }}">
@endpush

@section('content')
<section class="pr-shell">
    <div class="pr-layout">
        <main class="pr-card">
            @if(session('success'))<div class="pr-alert">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="pr-alert pr-error"><strong>Revisa la información:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @if($verFormatos)
                @include('partials.preregistro-formatos', ['soloConsulta' => true])
            @elseif($solicitudRechazada)
                {{-- El trámite se cerró durante la revisión: los documentos siguen
                     "En revisión" en la bitácora, así que en vez de la tabla —que
                     invitaría a subsanar algo que ya nadie va a revisar— se explica
                     el porqué. Espeja el bloque .pr-aceptado de abajo. --}}
                <div class="pr-interrumpido">
                    <span class="pr-interrumpido__icono" aria-hidden="true"><i class="fa-solid fa-circle-exclamation"></i></span>
                    <h1>Trámite interrumpido</h1>
                    <p class="pr-muted pr-interrumpido__texto">
                        El equipo administrativo interrumpió la revisión de tu documentación{{ $fechaEnvio ? ', enviada el '.$fechaEnvio : '' }}.
                        Tu trámite quedó cerrado en esta etapa.
                    </p>

                    @if($motivoInterrupcion)
                        <div class="pr-interrumpido__motivo">
                            <strong>Motivo</strong>
                            <p>{{ $motivoInterrupcion }}</p>
                        </div>
                    @else
                        <p class="pr-muted pr-interrumpido__sin-motivo">
                            No se registró un comentario adicional. Si necesitas más detalles,
                            comunícate con el equipo administrativo.
                        </p>
                    @endif

                    <div class="pr-interrumpido__acciones">
                        <a href="{{ route('persona.dashboard') }}" class="pr-btn pr-btn--secondary">Volver a mi panel</a>
                    </div>
                </div>
            @elseif($estado['fase'] === 'aprobado' && !$solicitudRechazada)
                {{-- La etapa terminó: en vez de la tabla se confirma el resultado. --}}
                <div class="pr-aceptado">
                    <span class="pr-aceptado__icono" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                    <h1>¡Documentación aceptada!</h1>
                    <p class="pr-muted pr-aceptado__texto">
                        Revisamos tus documentos y todos fueron aceptados{{ $fechaAprobacion ? ' el '.$fechaAprobacion : '' }}.
                    </p>

                    <ul class="pr-aceptado__lista">
                        @foreach($documentos as $slug => $nombre)
                            <li class="pr-aceptado__fila">
                                <span class="pr-aceptado__doc">{{ $nombre }}</span>
                                <span class="pr-status pr-status--approved">Aprobado</span>
                                <a class="pr-aceptado__abrir" target="_blank" href="{{ route('persona.preregistro.documentos.ver', $slug) }}">
                                    <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                                    <span>Abrir</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="pr-aceptado__acciones">
                        @if($solicitudAprobada)
                            <a href="{{ route('persona.referencia.index') }}" class="pr-btn">Continuar</a>
                        @else
                            <p class="pr-muted">Falta que el equipo administrativo cierre la revisión de tu solicitud completa.</p>
                            <a href="{{ route('persona.dashboard') }}" class="pr-btn pr-btn--secondary">Volver a mi panel</a>
                        @endif
                    </div>
                </div>
            @else
                <h1>Documentación requerida</h1>
                <p class="pr-muted">Sube los documentos uno por uno. Cada PDF debe pesar máximo 1 MB.</p>
                <p class="pr-volver-formatos">
                    <a href="{{ route('persona.documentos.index', ['ver' => 'formatos']) }}">
                        <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                        Ver o descargar los formatos otra vez
                    </a>
                </p>

                <div class="pr-tabla-envoltorio">
                    <table class="pr-tabla">
                        <thead>
                            <tr>
                                <th scope="col">Documento</th>
                                <th scope="col">Formato</th>
                                <th scope="col">Mi archivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentos as $slug => $nombre)
                                <?php
                                    $doc = isset($estado['documentos'][$slug]) ? $estado['documentos'][$slug] : null;
                                    $docEstado = $doc ? $doc['estado'] : 'pendiente';
                                    $clasesEstado = [
                                        'pendiente' => 'pending',
                                        'cargado' => 'loaded',
                                        'revision' => 'review',
                                        'aprobado' => 'approved',
                                        'rechazado' => 'rejected',
                                    ];
                                    $etiquetasEstado = [
                                        'pendiente' => 'Pendiente',
                                        'cargado' => 'Cargado',
                                        'revision' => 'En revisión',
                                        'aprobado' => 'Aprobado',
                                        'rechazado' => 'Rechazado',
                                    ];
                                    /* El archivo sólo se puede cambiar mientras nadie lo esté
                                       revisando: en revisión y aprobado quedan cerrados. */
                                    $puedeReemplazar = !in_array($docEstado, ['revision', 'aprobado'], true);
                                ?>
                                <tr class="pr-fila pr-fila--{{ $docEstado }}">
                                    <td data-titulo="Documento">
                                        <strong class="pr-fila__nombre">{{ $nombre }}</strong>
                                        <span class="pr-status pr-status--{{ $clasesEstado[$docEstado] }}">{{ $etiquetasEstado[$docEstado] }}</span>
                                    </td>

                                    <td data-titulo="Formato">
                                        @if(in_array($slug, $formatos))
                                            <div class="pr-fila__acciones">
                                                <a class="pr-btn" href="{{ route('persona.preregistro.formatos.generar', $slug) }}">
                                                    <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                                                    <span>Generar</span>
                                                </a>
                                            </div>
                                        @else
                                            <span class="pr-format__nota">Documento personal</span>
                                        @endif
                                    </td>

                                    <td data-titulo="Mi archivo">
                                        <div class="pr-fila__acciones">
                                            @if($doc)
                                                <a class="pr-btn pr-btn--secondary" target="_blank" href="{{ route('persona.preregistro.documentos.ver', $slug) }}">
                                                    <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                                                    <span>Abrir</span>
                                                </a>
                                            @endif

                                            @if($puedeReemplazar)
                                                <form method="POST" action="{{ route('persona.preregistro.documentos.store', $slug) }}" enctype="multipart/form-data" class="pr-upload-form">
                                                    @csrf
                                                    <label class="pr-btn pr-file">
                                                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                                        <span>{{ $docEstado === 'rechazado' ? 'Subsanar' : ($doc ? 'Reemplazar' : 'Adjuntar') }}</span>
                                                        <input type="file" name="archivo" accept="application/pdf" required>
                                                    </label>
                                                    <div class="pr-preview">
                                                        <span></span>
                                                        <iframe title="Previsualización del archivo"></iframe>
                                                        <button class="pr-btn" type="submit">Confirmar carga</button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>

                                        @if($doc)
                                            <small class="pr-fila__archivo">{{ $doc['nombre_original'] }}</small>
                                        @endif
                                    </td>
                                </tr>

                                @if($doc && $docEstado === 'rechazado' && !empty($doc['observacion']))
                                    <tr class="pr-fila-observacion">
                                        <td colspan="3">
                                            <div class="pr-observation">
                                                <strong>Motivo del rechazo</strong>
                                                <p>{{ $doc['observacion'] }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($estado['fase'] === 'aprobado')
                    <p class="pr-notice">Tus documentos fueron aprobados. Espera la resolución de tu solicitud.</p>
                @elseif($estado['fase'] === 'revision' && !$solicitudRechazada)
                    <p class="pr-notice pr-notice--enviado" role="status">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span>
                            Tus documentos fueron enviados a revisión{{ $fechaEnvio ? ' el '.$fechaEnvio : '' }}.
                            Te avisaremos en cuanto el equipo administrativo los revise.
                        </span>
                    </p>
                @elseif(!in_array($estado['fase'], ['revision', 'rechazado'], true) && !$solicitudRechazada)
                    <?php
                        /* Misma regla que enviarRevision(): los aprobados no se
                           reenvían, así que el conteo del diálogo no miente
                           cuando la persona está subsanando. */
                        $porEnviar = 0;
                        foreach (array_keys($documentos) as $slugConteo) {
                            $docConteo = isset($estado['documentos'][$slugConteo])
                                ? $estado['documentos'][$slugConteo]
                                : null;

                            if (!$docConteo || $docConteo['estado'] !== 'aprobado') {
                                $porEnviar++;
                            }
                        }
                    ?>
                    <form method="POST" action="{{ route('persona.preregistro.documentos.enviar') }}" class="pr-actions" data-envio-revision>
                        @csrf
                        <button type="submit" class="pr-btn" data-boton-envio>Enviar a revisión</button>
                    </form>

                    <div class="pr-modal" data-modal-envio hidden>
                        <div class="pr-modal__fondo" data-cerrar-envio></div>
                        <section class="pr-modal__card" role="dialog" aria-modal="true"
                                 aria-labelledby="pr-envio-titulo" aria-describedby="pr-envio-texto">
                            <h2 id="pr-envio-titulo">¿Enviar tus documentos a revisión?</h2>
                            <p id="pr-envio-texto">
                                Se {{ $porEnviar === 1 ? 'enviará 1 documento' : 'enviarán '.$porEnviar.' documentos' }}.
                                Después ya no podrás reemplazarlos hasta que el equipo administrativo termine de revisarlos.
                            </p>
                            <div class="pr-modal__acciones">
                                <button type="button" class="pr-btn pr-btn--secondary" data-cerrar-envio>Cancelar</button>
                                <button type="button" class="pr-btn" data-confirmar-envio>Sí, enviar</button>
                            </div>
                        </section>
                    </div>
                @endif
            @endif
        </main>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset_versionado('assets/js/pages/persona-documentos.js') }}"></script>
@endpush
