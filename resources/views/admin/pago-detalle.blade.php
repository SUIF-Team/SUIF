@extends('layouts.admin')

@section('title', 'SUIF — Revisión de pago')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-pago.css') }}">
@endsection

@section('content')
<section
    class="admin-preregistro-flujo admin-pago-detalle"
    data-pago-detalle
    @error('motivo_rechazo') data-rechazo-abierto @enderror
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
            {{-- La persona declara el monto que pagó; el de la referencia es el
                 que se le cobró. Revisar el comprobante es comparar los dos. --}}
            <div class="admin-preregistro-dato">
                <dt>Monto pagado</dt>
                <dd>{{ $pago['monto'] }}</dd>
            </div>
            <div class="admin-preregistro-dato">
                <dt>Monto de la referencia</dt>
                <dd>{{ $pago['monto_referencia'] ?? 'Sin registro' }}</dd>
            </div>
            <div class="admin-preregistro-dato">
                <dt>Referencia bancaria</dt>
                <dd>{{ $pago['referencia_bancaria'] }}</dd>
            </div>
            {{-- Ambas columnas son nulables: el renglón de PAGO nace al asignar
                 la referencia y la persona las llena hasta que paga. --}}
            <div class="admin-preregistro-dato">
                <dt>Fecha de pago</dt>
                <dd>{{ $pago['fecha_pago'] ? \Illuminate\Support\Carbon::parse($pago['fecha_pago'])->translatedFormat('d M Y') : 'Sin capturar' }}</dd>
            </div>
            <div class="admin-preregistro-dato">
                <dt>Hora de pago</dt>
                <dd>{{ $pago['hora_pago'] ? \Illuminate\Support\Carbon::parse($pago['hora_pago'])->translatedFormat('H:i') : 'Sin capturar' }}</dd>
            </div>
            {{-- Lo que la persona pidió para su pago. Es opcional: no haber
                 elegido nada es válido y su trámite sigue igual. --}}
            <div class="admin-preregistro-dato">
                <dt>Comprobante solicitado</dt>
                <dd>{{ $pago['comprobante_solicitado'] }}</dd>
            </div>
        </dl>

        @if($pago['datos_fiscales'])
            <section class="admin-pago-fiscales" aria-labelledby="datos-fiscales-titulo">
                <h3 id="datos-fiscales-titulo">Datos para el CFDI</h3>
                <dl class="admin-preregistro-datos admin-pago-datos">
                    <div class="admin-preregistro-dato">
                        <dt>Nombre o razón social</dt>
                        <dd>{{ $pago['datos_fiscales']['razon_social'] }}</dd>
                    </div>
                    <div class="admin-preregistro-dato">
                        <dt>Tipo de persona</dt>
                        <dd>{{ $pago['datos_fiscales']['tipo_persona'] }}</dd>
                    </div>
                    <div class="admin-preregistro-dato">
                        <dt>RFC</dt>
                        <dd>{{ $pago['datos_fiscales']['rfc'] }}</dd>
                    </div>
                    <div class="admin-preregistro-dato">
                        <dt>Régimen fiscal</dt>
                        <dd>{{ $pago['datos_fiscales']['regimen_fiscal'] }}</dd>
                    </div>
                    <div class="admin-preregistro-dato">
                        <dt>Código postal</dt>
                        <dd>{{ $pago['datos_fiscales']['codigo_postal'] }}</dd>
                    </div>
                    <div class="admin-preregistro-dato">
                        <dt>Correo para el CFDI</dt>
                        <dd>{{ $pago['datos_fiscales']['correo'] }}</dd>
                    </div>
                </dl>
            </section>
        @elseif($pago['comprobante_solicitado'] === 'CFDI')
            <p class="admin-preregistro-solo-lectura">
                La persona eligió CFDI y todavía no captura sus datos de facturación.
            </p>
        @endif

        @if($pago['motivo_rechazo'])
            <section class="admin-pago-motivo" aria-labelledby="motivo-rechazo-titulo">
                <h3 id="motivo-rechazo-titulo">Motivo del rechazo</h3>
                <p>{{ $pago['motivo_rechazo'] }}</p>
            </section>
        @endif

        @if($pago['puede_revisarse'])
            {{-- Sólo la decisión: el motivo se pide después, y aparte, para no
                 desbalancear la tarjeta con un cuadro de texto que casi nunca
                 se usa. --}}
            <alertas
                :mensaje="avisoError"
                tipo="error"
                :errores="erroresServidor"
                clase="admin-preregistro-alerta admin-preregistro-alerta--error"></alertas>

            <section id="acciones-pago" class="admin-pago-resolucion" aria-label="Acciones del pago">
                <p id="acciones-pago-ayuda" class="visually-hidden">
                    Revisa el comprobante antes de validar o rechazar el pago.
                </p>
                <form
                    method="POST"
                    action="{{ route('admin.pagos.validar', ['id' => $pago['id']]) }}"
                    v-on:submit.prevent="enviar($event)">
                    @csrf
                    <button class="admin-preregistro-boton admin-preregistro-boton--aceptar" type="submit" :disabled="enviando" aria-describedby="acciones-pago-ayuda">
                        @{{ enviando ? 'Validando…' : 'Validar pago' }}
                    </button>
                </form>

                <button
                    class="admin-preregistro-boton admin-preregistro-boton--rechazar"
                    type="button"
                    aria-describedby="acciones-pago-ayuda"
                    :aria-expanded="rechazoAbierto ? 'true' : 'false'"
                    aria-controls="panel-rechazo"
                    v-on:click="abrirRechazo">
                    Rechazar pago
                </button>
            </section>
        @else
            <p class="admin-preregistro-solo-lectura">
                {{ $pago['mensaje_revision_no_disponible'] ?: 'Este pago se muestra en modo de sólo lectura.' }}
            </p>
        @endif
    </main>

    @if($pago['puede_revisarse'])
        <section
            id="panel-rechazo"
            class="admin-preregistro-tarjeta admin-preregistro-detalle admin-pago-rechazo-panel"
            v-if="rechazoAbierto"
            aria-labelledby="panel-rechazo-titulo">
            <h2 id="panel-rechazo-titulo">Motivo del rechazo</h2>
            <form
                method="POST"
                action="{{ route('admin.pagos.rechazar', ['id' => $pago['id']]) }}"
                v-on:submit.prevent="enviar($event)">
                @csrf
                {{-- El valor lo escribe Blade con old(); admin-pago-detalle.js lo
                     lee del DOM antes de montar para sembrar el v-model. --}}
                <textarea
                    id="motivo-rechazo"
                    name="motivo_rechazo"
                    rows="3"
                    maxlength="2000"
                    required
                    ref="motivo"
                    v-model="motivo"
                    aria-describedby="motivo-rechazo-ayuda">{{ old('motivo_rechazo') }}</textarea>
                <p id="motivo-rechazo-ayuda">Este mensaje se mostrará a la persona para que pueda subsanar su comprobante.</p>
                @error('motivo_rechazo')
                    <p class="admin-preregistro-mensaje-validacion" role="alert">{{ $message }}</p>
                @enderror
                <div class="admin-pago-rechazo-panel-acciones">
                    <button class="admin-preregistro-boton admin-preregistro-boton--neutral" type="button" v-on:click="cerrarRechazo">
                        Cancelar
                    </button>
                    <button
                        class="admin-preregistro-boton admin-preregistro-boton--rechazar"
                        type="submit"
                        :disabled="!motivoValido">
                        Confirmar rechazo
                    </button>
                </div>
            </form>
        </section>
    @endif

    @include('partials.admin.acciones-reversion', ['acciones' => $acciones ?? []])

    <back-navigation
        destino="{{ route('admin.pagos.index') }}"
        etiqueta="Atrás"
        etiqueta-accesible="Atrás"></back-navigation>
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-pago-detalle.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-reversion.js') }}"></script>
@endsection
