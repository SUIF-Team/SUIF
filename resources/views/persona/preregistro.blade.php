@extends('layouts.persona')

@section('title', 'SUIF — Pre-registro')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-preregistro.css') }}">
@endpush

@section('content')
<section class="pr-shell">
    <div class="pr-layout">
        <main class="pr-card">
            @if(session('success'))<div class="pr-alert">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="pr-alert pr-error"><strong>Revisa la información:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @if($estado['fase'] === 'datos')
                <h1>Datos de identificación</h1>
                <p class="pr-muted">Todos los campos son obligatorios. No podrás avanzar hasta completarlos.</p>

                <div class="pr-notice pr-notice--identidad">
                    <p><strong>Escribe tus datos exactamente como aparecen en tu identificación oficial, sin incluir acentos.</strong></p>
                    <p>No importa si los escribes en mayúsculas: el sistema ajusta el formato automáticamente. Tu CURP se guardará siempre en mayúsculas.</p>
                </div>

                @include('partials.preregistro-formulario', [
                    'accion' => route('persona.preregistro.datos.store'),
                    'textoBoton' => 'Continuar',
                    'mostrarCancelar' => false,
                    'avisoClave' => true,
                    'mostrarAviso' => true,
                    'botonDeshabilitado' => true,
                ])

            @elseif($estado['fase'] === 'clave')
                <section class="pr-clave" aria-labelledby="pr-clave-titulo">
                    <span class="pr-clave__icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                    <p class="pr-clave__eyebrow">Pre-registro iniciado</p>
                    <h1 id="pr-clave-titulo">Tus datos fueron registrados correctamente</h1>
                    @if($estado['correo_enviado'] ?? true)
                        <p class="pr-clave__texto">Guarda tu clave de acceso. También la enviamos a tu correo principal para que puedas retomar el proceso.</p>
                    @else
                        <div class="pr-alert pr-error">No pudimos enviar el correo con tu clave. Cópiala y guárdala ahora desde esta pantalla: es el único lugar donde podrás verla.</div>
                    @endif

                    <div class="pr-clave__codigo" aria-label="Clave de acceso generada">
                        <span id="pr-key">{{ $estado['clave'] }}</span>
                        <button type="button" class="pr-clave__copiar" data-copy-key aria-describedby="pr-clave-ayuda">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                            <span>Copiar</span>
                        </button>
                    </div>
                    <p id="pr-clave-ayuda" class="pr-clave__ayuda">La necesitarás para consultar y continuar tu trámite.</p>
                    <form method="POST" action="{{ route('persona.preregistro.avanzar') }}" class="pr-actions">
                        @csrf
                        <button class="pr-btn" type="submit">Continuar</button>
                    </form>
                </section>

            @elseif($estado['fase'] === 'editar')
                <h1>Editar mis datos</h1>
                <p class="pr-muted">Modifica solo lo necesario. Podrás hacerlo hasta que tu documentación entre a revisión.</p>

                <div class="pr-notice pr-notice--identidad">
                    <p><strong>Escribe tus datos exactamente como aparecen en tu identificación oficial</strong>, sin incluir acentos.</p>
                </div>

                @include('partials.preregistro-formulario', [
                    'accion' => route('persona.preregistro.editar.store'),
                    'textoBoton' => 'Guardar cambios',
                    'mostrarCancelar' => true,
                    'avisoClave' => false,
                    'mostrarAviso' => false,
                    'botonDeshabilitado' => false,
                ])

            @elseif($estado['fase'] === 'registrado')
                <h1>Mis datos de identificación</h1>
                <p class="pr-muted">Estos son los datos con los que quedó registrada tu solicitud.</p>

                <dl class="pr-datos">
                    <div><dt>Nombre</dt><dd>{{ $estado['datos']['nombre'] ?? '' }} {{ $estado['datos']['primer_apellido'] ?? '' }} {{ $estado['datos']['segundo_apellido'] ?? '' }}</dd></div>
                    <div><dt>CURP</dt><dd>{{ $estado['datos']['curp'] ?? '' }}</dd></div>
                    <div><dt>RFC</dt><dd>{{ $estado['datos']['rfc'] ?? '' }}</dd></div>
                    <div><dt>Correo principal</dt><dd>{{ $estado['datos']['correo_principal'] ?? '' }}</dd></div>
                    <div><dt>Correo alterno</dt><dd>{{ $estado['datos']['correo_alterno'] ?? '' }}</dd></div>
                    <div><dt>Teléfono celular</dt><dd>{{ $estado['datos']['telefono'] ?? '' }}</dd></div>
                    <div><dt>Entidad federativa</dt><dd>{{ $estado['datos']['entidad_federativa'] ?? '' }}</dd></div>
                    <div><dt>Último grado de estudios</dt><dd>{{ $grados[$estado['datos']['grado_estudios'] ?? ''] ?? '' }}</dd></div>
                    <div><dt>¿Realiza actividades vulnerables?</dt><dd>{{ ($estado['datos']['actividad_vulnerable'] ?? '') === 'si' ? 'Sí' : 'No' }}</dd></div>
                    <div><dt>¿Es responsable de cumplimiento?</dt><dd>{{ ($estado['datos']['responsable_cumplimiento'] ?? '') === 'si' ? 'Sí' : 'No' }}</dd></div>
                </dl>

                <div class="pr-actions">
                    @if($puedeEditar)
                        <a class="pr-btn pr-btn--secondary" href="{{ route('persona.preregistro.editar') }}">Editar mis datos</a>
                    @endif
                    <a class="pr-btn" href="{{ route('persona.documentos.index') }}">Continuar a documentación</a>
                </div>

                @unless($puedeEditar)
                    <p class="pr-muted">Tus datos ya no se pueden modificar porque tu documentación entró a revisión.</p>
                @endunless
            @endif
        </main>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset_versionado('assets/js/pages/persona-preregistro.js') }}"></script>
@endpush