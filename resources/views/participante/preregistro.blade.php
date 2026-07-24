@extends('layouts.participante')

@section('title', 'SUIF — Pre-registro')

@push('styles')
<style>
.pr-shell{min-height:760px;padding:38px 44px 54px;background:rgba(255,255,255,.82)}.pr-layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:28px;max-width:1220px;margin:auto}.pr-side{background:#101927;color:#fff;border-radius:16px;padding:22px;height:max-content;position:sticky;top:20px}.pr-step{display:flex;gap:12px;align-items:center;padding:14px;border-radius:12px;background:#1d2a3c}.pr-step__icon{width:34px;height:34px;display:grid;place-items:center;border-radius:50%;background:#c39208;font-weight:700}.pr-step--done .pr-step__icon{background:#19a956}.pr-step small{display:block;color:#bac3ce;margin-top:3px}.pr-card{background:#fff;border-radius:18px;padding:32px;box-shadow:0 12px 35px rgba(23,42,68,.09)}.pr-card h1,.pr-card h2{margin-top:0;color:#172a44}.pr-muted{color:#6e7887}.pr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.pr-field{display:grid;gap:7px}.pr-field label{font-size:13px;font-weight:700}.pr-field input,.pr-field select{width:100%;padding:13px 14px;border:1px solid #d9dee7;border-radius:9px;background:#fff}.pr-actions{margin-top:24px;display:flex;justify-content:flex-end;gap:12px}.pr-btn{display:inline-flex;align-items:center;justify-content:center;min-width:150px;padding:12px 18px;border:0;border-radius:9px;background:#b98709;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.pr-btn--secondary{background:#fff3bd;color:#172a44;border:1px solid #ead99a}.pr-btn[disabled]{background:#dfe3e9;cursor:not-allowed}.pr-key{display:inline-block;padding:15px 24px;border-radius:10px;background:#b98709;color:#fff;font-size:21px;letter-spacing:1px}.pr-docs{display:grid;gap:13px;margin-top:24px}.pr-doc{display:grid;grid-template-columns:minmax(0,1fr) auto auto auto;gap:14px;align-items:center;padding:14px;border:1px solid #dce1e9;border-radius:12px}.pr-status{min-width:110px;padding:8px 12px;border-radius:999px;text-align:center;font-size:12px}.pr-status--pending{background:#e5e8ed}.pr-status--loaded{background:#dff2e4;color:#22713b}.pr-status--review{background:#ffe0a9;color:#7a4d00}.pr-status--approved{background:#16a653;color:#fff}.pr-status--rejected{background:#b5000a;color:#fff}.pr-file{position:relative;overflow:hidden}.pr-file input{position:absolute;inset:0;opacity:0;cursor:pointer}.pr-observation{margin-top:18px;padding:18px;border:1px solid #ebc7c7;border-radius:12px;background:#fff8f8;color:#8d1117}.pr-format{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:14px;align-items:center;padding:14px;border:1px solid #dce1e9;border-radius:12px}.pr-alert{margin-bottom:18px;padding:13px 16px;border-radius:10px;background:#eef7f0;color:#245f35}.pr-error{background:#fff0f0;color:#9b1717}.pr-center{text-align:center;padding:58px 20px}.pr-demo{margin-top:22px;padding-top:16px;border-top:1px dashed #d7dce5;font-size:12px}.pr-demo a{margin-right:12px}.pr-preview{display:none;margin-top:8px;font-size:12px}.pr-preview.is-visible{display:block}.pr-preview iframe{width:100%;height:360px;border:1px solid #dce1e9;border-radius:10px;margin-top:8px}@media(max-width:900px){.pr-layout{grid-template-columns:1fr}.pr-side{position:static}.pr-grid{grid-template-columns:1fr}.pr-doc,.pr-format{grid-template-columns:1fr}.pr-shell{padding:24px 16px}}
</style>
@endpush

@section('content')
<section class="pr-shell">
    <div class="pr-layout">
        <aside class="pr-side">
            <div class="pr-step {{ $estado['fase'] === 'completado' ? 'pr-step--done' : '' }}">
                <span class="pr-step__icon">{{ $estado['fase'] === 'completado' ? '✓' : '1' }}</span>
                <div><strong>Pre-registro</strong><small>Datos y documentación</small></div>
            </div>
        </aside>

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
                    <div class="pr-actions"><button class="pr-btn" type="submit">Continuar</button></div>
                </form>

            @elseif($estado['fase'] === 'clave')
                <div class="pr-center"><h1>¡Datos registrados!</h1><p>Tu clave de acceso es:</p><p><span class="pr-key" id="pr-key">{{ $estado['clave'] }}</span></p><button type="button" class="pr-btn pr-btn--secondary" data-copy-key>Copiar clave</button></div>
                <form method="POST" action="{{ route('participante.preregistro.avanzar') }}" class="pr-actions">{{ csrf_field() }}<button class="pr-btn">Continuar</button></form>

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
<script>
(function(){
  var copy=document.querySelector('[data-copy-key]');if(copy){copy.addEventListener('click',function(){var t=document.getElementById('pr-key').textContent;navigator.clipboard&&navigator.clipboard.writeText(t);copy.textContent='Clave copiada';});}
  document.querySelectorAll('.pr-upload-form input[type=file]').forEach(function(input){input.addEventListener('change',function(){var f=input.files&&input.files[0],box=input.closest('form').querySelector('.pr-preview');if(!f)return;if(f.type!=='application/pdf'||f.size>1048576){alert('Selecciona un PDF de máximo 1 MB.');input.value='';return;}box.querySelector('span').textContent=f.name+' · '+Math.ceil(f.size/1024)+' KB';box.querySelector('iframe').src=URL.createObjectURL(f);box.classList.add('is-visible');});});
})();
</script>
@endpush
