@extends('layouts.participante')

@section('title', 'SUIF — Pre-registro')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/participante-preregistro.css') }}">
@endpush

@section('participante_sidebar')
<div class="pr-sidebar-step"><span>1</span><div><strong>Pre-registro</strong><small>Captura de datos</small></div></div>
@endsection

@section('content')
<section class="pr-shell">
    <div class="pr-layout">
        <main class="pr-card">
            @if(session('success'))<div class="pr-alert">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="pr-alert pr-error"><strong>Revisa la información:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @if($estado['fase'] === 'datos')
                <h1>Datos de identificación</h1>
                <p class="pr-muted">Todos los campos son obligatorios. No podrás avanzar hasta completarlos.</p>
                <form method="POST" action="{{ route('participante.preregistro.datos.store') }}" id="pr-data-form">
                    {{ csrf_field() }}
                    <div class="pr-grid">
                        <div class="pr-field"><label>Nombre *</label><input name="nombre" value="{{ old('nombre', isset($estado['datos']['nombre']) ? $estado['datos']['nombre'] : '') }}" required></div>
                        <div class="pr-field"><label>Primer apellido *</label><input name="primer_apellido" value="{{ old('primer_apellido', isset($estado['datos']['primer_apellido']) ? $estado['datos']['primer_apellido'] : '') }}" required></div>
                        <div class="pr-field"><label>Segundo apellido *</label><input name="segundo_apellido" value="{{ old('segundo_apellido', isset($estado['datos']['segundo_apellido']) ? $estado['datos']['segundo_apellido'] : '') }}" required></div>
                        <div class="pr-field"><label>CURP *</label><input name="curp" maxlength="18" value="{{ old('curp', isset($estado['datos']['curp']) ? $estado['datos']['curp'] : '') }}" required></div>
                        <div class="pr-field"><label>Correo principal *</label><input type="email" name="correo_principal" value="{{ old('correo_principal', isset($estado['datos']['correo_principal']) ? $estado['datos']['correo_principal'] : '') }}" required></div>
                        <div class="pr-field"><label>Teléfono celular *</label><input name="telefono" maxlength="10" pattern="[0-9]{10}" value="{{ old('telefono', isset($estado['datos']['telefono']) ? $estado['datos']['telefono'] : '') }}" required></div>
                        <div class="pr-field"><label>Entidad federativa *</label><select name="entidad_federativa" required><option value="">Selecciona una opción</option>@foreach($entidades as $entidad)<option value="{{ $entidad }}" {{ old('entidad_federativa') === $entidad ? 'selected' : '' }}>{{ $entidad }}</option>@endforeach</select></div>
                        <div class="pr-field"><label>Correo alterno *</label><input type="email" name="correo_alterno" value="{{ old('correo_alterno', isset($estado['datos']['correo_alterno']) ? $estado['datos']['correo_alterno'] : '') }}" required></div>
                        <div class="pr-field"><label>Último grado de estudios *</label><select name="grado_estudios" required><option value="">Selecciona una opción</option>@foreach($grados as $valor => $texto)<option value="{{ $valor }}" {{ old('grado_estudios') === $valor ? 'selected' : '' }}>{{ $texto }}</option>@endforeach</select></div>
                        <div class="pr-field"><label>¿Realiza actividades vulnerables? *</label><select name="actividad_vulnerable" required><option value="">Selecciona una opción</option><option value="si">Sí</option><option value="no">No</option></select></div>
                        <div class="pr-field"><label>¿Es responsable de cumplimiento? *</label><select name="responsable_cumplimiento" required><option value="">Selecciona una opción</option><option value="si">Sí</option><option value="no">No</option></select></div>
                    </div>
                    <p class="pr-notice">Al continuar, la clave de acceso se enviará al correo principal y podrás continuar el proceso desde la plataforma.</p><div class="pr-actions"><button class="pr-btn" type="submit" disabled>Continuar</button></div>
                </form>

            @elseif($estado['fase'] === 'clave')
                <section class="pr-clave" aria-labelledby="pr-clave-titulo">
                    <span class="pr-clave__icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                    <p class="pr-clave__eyebrow">Pre-registro iniciado</p>
                    <h1 id="pr-clave-titulo">Tus datos fueron registrados correctamente</h1>
                    <p class="pr-clave__texto">Guarda tu clave de acceso. También la enviamos a tu correo principal para que puedas retomar el proceso.</p>

                    <div class="pr-clave__codigo" aria-label="Clave de acceso generada">
                        <span id="pr-key">{{ $estado['clave'] }}</span>
                        <button type="button" class="pr-clave__copiar" data-copy-key aria-describedby="pr-clave-ayuda">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                            <span>Copiar</span>
                        </button>
                    </div>
                    <p id="pr-clave-ayuda" class="pr-clave__ayuda">La necesitarás para consultar y continuar tu trámite.</p>
                    <form method="POST" action="{{ route('participante.preregistro.avanzar') }}" class="pr-actions">
                        {{ csrf_field() }}
                        <button class="pr-btn" type="submit">Continuar</button>
                    </form>
                </section>

            @elseif($estado['fase'] === 'formatos')
                <h1>Formatos requeridos</h1><p class="pr-muted">Previsualiza cada formato antes de descargarlo.</p>
                <div class="pr-docs">@foreach($documentos as $slug => $nombre)<div class="pr-format"><strong>{{ $nombre }}</strong><a class="pr-btn pr-btn--secondary" target="_blank" href="{{ route('participante.preregistro.formatos.ver', $slug) }}">Previsualizar</a><a class="pr-btn" href="{{ route('participante.preregistro.formatos.descargar', $slug) }}">Descargar</a></div>@endforeach</div>
                <form method="POST" action="{{ route('participante.preregistro.avanzar') }}" class="pr-actions">{{ csrf_field() }}<button class="pr-btn">Continuar</button></form>

            @elseif(in_array($estado['fase'], ['documentos','revision','rechazado','aprobado']))
                <h1>Documentación requerida</h1><p class="pr-muted">Adjunta un PDF por documento. Tamaño máximo: 1 MB.</p>
                <div class="pr-docs">
                    @foreach($documentos as $slug => $nombre)
                        <?php $doc = isset($estado['documentos'][$slug]) ? $estado['documentos'][$slug] : null; $docEstado = $doc ? $doc['estado'] : 'pendiente'; ?>
                        <div class="pr-doc">
                            <strong>{{ $nombre }}</strong>
                            <span class="pr-status pr-status--{{ $docEstado === 'cargado' ? 'loaded' : ($docEstado === 'revision' ? 'review' : ($docEstado === 'aprobado' ? 'approved' : ($docEstado === 'rechazado' ? 'rejected' : 'pending'))) }}">{{ $docEstado === 'cargado' ? 'Cargado' : ($docEstado === 'revision' ? 'En revisión' : ($docEstado === 'aprobado' ? 'Aprobado' : ($docEstado === 'rechazado' ? 'Rechazado' : 'Pendiente'))) }}</span>
                            @if($doc)<a class="pr-btn pr-btn--secondary" target="_blank" href="{{ route('participante.preregistro.documentos.ver', $slug) }}">Abrir</a>@endif
                            @if(!in_array($estado['fase'], ['revision','aprobado']))
                            <form method="POST" action="{{ route('participante.preregistro.documentos.store', $slug) }}" enctype="multipart/form-data" class="pr-upload-form">
                                {{ csrf_field() }}
                                <label class="pr-btn pr-file">{{ $docEstado === 'rechazado' ? 'Subsanar' : 'Seleccionar PDF' }}<input type="file" name="archivo" accept="application/pdf" required></label>
                                <div class="pr-preview"><span></span><iframe title="Previsualización del archivo"></iframe><button class="pr-btn" type="submit">Confirmar carga</button></div>
                            </form>
                            @endif
                        </div>
                        @if($doc && $docEstado === 'rechazado' && !empty($doc['observacion']))<div class="pr-observation"><strong>Motivo del rechazo</strong><p>{{ $doc['observacion'] }}</p></div>@endif
                    @endforeach
                </div>
                @if($estado['fase'] === 'aprobado')
                    <form method="POST" action="{{ route('participante.preregistro.finalizar') }}" class="pr-actions">{{ csrf_field() }}<button class="pr-btn">Finalizar pre-registro</button></form>
                @elseif(!in_array($estado['fase'], ['revision','rechazado']))
                    <form method="POST" action="{{ route('participante.preregistro.documentos.enviar') }}" class="pr-actions">{{ csrf_field() }}<button class="pr-btn">Enviar a revisión</button></form>
                @endif
                @if(config('app.debug'))<div class="pr-demo">Simular: <a href="{{ route('participante.preregistro.demo','revision') }}">En revisión</a><a href="{{ route('participante.preregistro.demo','rechazado') }}">Rechazado</a><a href="{{ route('participante.preregistro.demo','aprobado') }}">Aprobado</a><a href="{{ route('participante.preregistro.reiniciar') }}">Reiniciar</a></div>@endif

            @elseif($estado['fase'] === 'completado')
                <div class="pr-center"><h1>¡Registro completado!</h1><p>Tu registro está sujeto a validación. Espera el correo de confirmación.</p><a class="pr-btn" href="{{ route('participante.dashboard') }}">Volver al inicio</a></div>
            @endif
        </main>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/pages/participante-preregistro.js') }}"></script>
<script>
(function(){
  var copy=document.querySelector('[data-copy-key]');if(copy){copy.addEventListener('click',function(){var t=document.getElementById('pr-key').textContent;navigator.clipboard&&navigator.clipboard.writeText(t);copy.textContent='Clave copiada';});}
  document.querySelectorAll('.pr-upload-form input[type=file]').forEach(function(input){input.addEventListener('change',function(){var f=input.files&&input.files[0],box=input.closest('form').querySelector('.pr-preview');if(!f)return;if(f.type!=='application/pdf'||f.size>1048576){alert('Selecciona un PDF de máximo 1 MB.');input.value='';return;}box.querySelector('span').textContent=f.name+' · '+Math.ceil(f.size/1024)+' KB';box.querySelector('iframe').src=URL.createObjectURL(f);box.classList.add('is-visible');});});
})();
</script>
@endpush
