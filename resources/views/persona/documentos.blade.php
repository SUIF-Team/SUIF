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

                                            @if(!in_array($estado['fase'], ['revision','aprobado']))
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
                @elseif(!in_array($estado['fase'], ['revision','rechazado']))
                    <form method="POST" action="{{ route('persona.preregistro.documentos.enviar') }}" class="pr-actions">@csrf<button class="pr-btn">Enviar a revisión</button></form>
                @endif
            @endif
        </main>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function(){
  document.querySelectorAll('.pr-upload-form input[type=file]').forEach(function(input){input.addEventListener('change',function(){var f=input.files&&input.files[0],box=input.closest('form').querySelector('.pr-preview');if(!f)return;if(f.type!=='application/pdf'||f.size>1048576){alert('Selecciona un PDF de máximo 1 MB.');input.value='';return;}box.querySelector('span').textContent=f.name+' · '+Math.ceil(f.size/1024)+' KB';box.querySelector('iframe').src=URL.createObjectURL(f);box.classList.add('is-visible');});});
})();
</script>
@endpush
