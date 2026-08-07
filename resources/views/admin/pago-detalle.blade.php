@extends('layouts.admin')

@section('title', 'SUIF — Revisión de pago')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-pago.css') }}">
@endsection

@section('content')
<section
    class="admin-preregistro-flujo admin-pago-detalle"
    data-pago-detalle
    aria-labelledby="detalle-pago-titulo"
    v-cloak>
    <header class="admin-preregistro-tarjeta admin-preregistro-perfil">
        <div class="admin-preregistro-usuario">
            <span class="admin-preregistro-avatar" aria-hidden="true">{{ $pago['iniciales'] }}</span>
            <div>
                <h1 id="detalle-pago-titulo">{{ $pago['nombre_completo'] }}</h1>
                <p>CURP: {{ $pago['curp'] }} · {{ $pago['entidad_federativa'] }}</p>
            </div>
        </div>
        <span class="admin-preregistro-estado {{ $pago['clase_estado_detalle'] }}" role="status">
            {{ $pago['estatus'] }}
        </span>
    </header>

    <nav class="admin-preregistro-progreso admin-pago-progreso" aria-label="Progreso del trámite">
        <div class="admin-preregistro-paso {{ $pago['clase_paso_preregistro'] }}">
            <span class="admin-preregistro-paso-titulo">Pre-registro</span>
            <span class="admin-preregistro-paso-estado">{{ $pago['estado_preregistro'] }}</span>
        </div>
        <div class="admin-preregistro-paso {{ $pago['clase_paso_documentacion'] }}">
            <span class="admin-preregistro-paso-titulo">Documentación</span>
            <span class="admin-preregistro-paso-estado">{{ $pago['estado_documentacion'] }}</span>
        </div>
        <div class="admin-preregistro-paso {{ $pago['clase_paso_pago'] }}" @if($pago['puede_revisarse']) aria-current="step" @endif>
            <span class="admin-preregistro-paso-titulo">Pago</span>
            <span class="admin-preregistro-paso-estado">{{ $pago['estatus'] }}</span>
        </div>
    </nav>

    <main class="admin-preregistro-tarjeta admin-preregistro-detalle admin-pago-detalle-tarjeta" aria-labelledby="datos-pago-titulo">
        <h2 id="datos-pago-titulo">Pago / Referencia bancaria</h2>

        <section class="admin-pago-comprobante" aria-labelledby="comprobante-titulo">
            <div>
                <h3 id="comprobante-titulo">{{ $pago['comprobante']['nombre'] }}</h3>
                <p>Comprobante enviado por la persona.</p>
            </div>
            @if($pago['comprobante_disponible'])
                <a
                    class="admin-preregistro-previsualizar admin-pago-enlace-comprobante"
                    href="{{ route('admin.pagos.comprobante', ['id' => $pago['id']]) }}"
                    target="_blank"
                    rel="noopener noreferrer">
                    Abrir comprobante
                </a>
            @else
                <span class="admin-pago-archivo-no-disponible">Archivo no disponible</span>
            @endif
        </section>

        <dl class="admin-preregistro-datos admin-pago-datos">
            <div class="admin-preregistro-dato">
                <dt>Monto</dt>
                <dd>{{ $pago['monto'] }}</dd>
            </div>
            <div class="admin-preregistro-dato">
                <dt>Referencia bancaria</dt>
                <dd>{{ $pago['referencia_bancaria'] }}</dd>
            </div>
            <div class="admin-preregistro-dato">
                <dt>Fecha de pago</dt>
                <dd>{{ \Illuminate\Support\Carbon::parse($pago['fecha_pago'])->translatedFormat('d M Y') }}</dd>
            </div>
            <div class="admin-preregistro-dato">
                <dt>Hora de pago</dt>
                <dd>{{ \Illuminate\Support\Carbon::parse($pago['hora_pago'])->translatedFormat('H:i') }}</dd>
            </div>
        </dl>

        @if($pago['motivo_rechazo'])
            <section class="admin-pago-motivo" aria-labelledby="motivo-rechazo-titulo">
                <h3 id="motivo-rechazo-titulo">Motivo del rechazo</h3>
                <p>{{ $pago['motivo_rechazo'] }}</p>
            </section>
        @endif

        @if($pago['puede_revisarse'])
            <section id="acciones-pago" class="admin-pago-resolucion" aria-label="Acciones del pago">
                <p id="acciones-pago-ayuda" class="visually-hidden">
                    Revisa el comprobante antes de validar o rechazar el pago.
                </p>
                <form method="POST" action="{{ route('admin.pagos.validar', ['id' => $pago['id']]) }}">
                    @csrf
                    <button class="admin-preregistro-boton admin-preregistro-boton--aceptar" type="submit" aria-describedby="acciones-pago-ayuda">
                        Validar pago
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.pagos.rechazar', ['id' => $pago['id']]) }}" class="admin-pago-rechazo">
                    @csrf
                    <label for="motivo-rechazo">Motivo del rechazo</label>
                    <textarea id="motivo-rechazo" name="motivo_rechazo" rows="4" maxlength="2000" required aria-describedby="motivo-rechazo-ayuda">{{ old('motivo_rechazo') }}</textarea>
                    <p id="motivo-rechazo-ayuda">Este mensaje se mostrará a la persona para que pueda subsanar su comprobante.</p>
                    @error('motivo_rechazo')
                        <p class="admin-preregistro-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                    <button class="admin-preregistro-boton admin-preregistro-boton--rechazar" type="submit" aria-describedby="acciones-pago-ayuda">
                        Rechazar pago
                    </button>
                </form>
            </section>
        @else
            <p class="admin-preregistro-solo-lectura">
                {{ $pago['mensaje_revision_no_disponible'] ?: 'Este pago se muestra en modo de sólo lectura.' }}
            </p>
        @endif
    </main>

    <back-navigation
        destino="{{ route('admin.pagos.index') }}"
        etiqueta="Atrás"
        etiqueta-accesible="Atrás"></back-navigation>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset('assets/js/pages/admin-pago-detalle.js') }}"></script>
@endsection
