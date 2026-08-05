@extends('layouts.admin')

@section('title', 'SUIF — Detalle de Pre-registro')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
@endsection

@section('content')
@php
    $datos_vista = ['participante' => $participante, 'estados' => $estados];
@endphp
<section id="preregistro-admin-app" class="admin-preregistro-flujo" data-preregistro-admin data-vista='@json($datos_vista)' aria-labelledby="detalle-preregistro-titulo" v-cloak>
    <header class="admin-preregistro-tarjeta admin-preregistro-perfil">
        <div class="admin-preregistro-usuario">
            <span class="admin-preregistro-avatar" aria-hidden="true">@{{ iniciales }}</span>
            <div>
                <h1 id="detalle-preregistro-titulo">@{{ nombreCompleto }}</h1>
                <p>CURP: @{{ participante.curp }} · Folio @{{ participante.folio }} · @{{ participante.entidad_federativa }}</p>
            </div>
        </div>
        <span class="admin-preregistro-estado" :class="claseEstadoGeneral" role="status">@{{ estados.general }}</span>
    </header>

    <nav class="admin-preregistro-progreso" aria-label="Progreso del trámite">
        <div class="admin-preregistro-paso" :class="clasePaso('preregistro')" :aria-current="pasoActual === 'preregistro' ? 'step' : null">
            <span class="admin-preregistro-paso-titulo">Pre-registro</span>
            <span class="admin-preregistro-paso-estado">@{{ estados.preregistro }}</span>
        </div>
        <div class="admin-preregistro-paso" :class="clasePaso('documentacion')" :aria-current="pasoActual === 'documentacion' ? 'step' : null">
            <span class="admin-preregistro-paso-titulo">Documentación</span>
            <span class="admin-preregistro-paso-estado">@{{ estados.documentacion }}</span>
        </div>
    </nav>

    <section class="admin-preregistro-tarjeta admin-preregistro-detalle" aria-labelledby="datos-participante-titulo">
        <h2 id="datos-participante-titulo">Datos del participante</h2>
        <dl class="admin-preregistro-datos">
            <div v-for="campo in camposParticipante" :key="campo.etiqueta" class="admin-preregistro-dato">
                <dt>@{{ campo.etiqueta }}</dt>
                <dd>@{{ campo.valor }}</dd>
            </div>
        </dl>

        @if ($modo_solo_lectura ?? false)
            <p class="admin-preregistro-solo-lectura" role="status">
                La consulta está disponible; las acciones de revisión se habilitarán en una entrega posterior.
            </p>
        @else
        <div class="admin-preregistro-acciones">
            <form method="POST" action="{{ route('admin.participantes.preregistro.aceptar', ['id' => $participante['id'], 'origen' => $contexto_bandeja['origen']]) }}" v-on:submit="enviando = true">
                @csrf
                <input type="hidden" name="origen" value="{{ $contexto_bandeja['origen'] }}">
                <button class="admin-preregistro-boton admin-preregistro-boton--aceptar" type="submit" :disabled="enviando">
                    @{{ enviando ? 'Procesando…' : 'Aceptar solicitud' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.participantes.preregistro.rechazar', ['id' => $participante['id'], 'origen' => $contexto_bandeja['origen']]) }}" v-on:submit="enviando = true">
                @csrf
                <input type="hidden" name="origen" value="{{ $contexto_bandeja['origen'] }}">
                <button class="admin-preregistro-boton admin-preregistro-boton--rechazar" type="submit" :disabled="enviando">
                    @{{ enviando ? 'Procesando…' : 'Rechazar solicitud' }}
                </button>
            </form>
        </div>
        @endif
    </section>

    <back-navigation
        destino="{{ $contexto_bandeja['ruta'] }}"
        etiqueta="{{ $contexto_bandeja['etiqueta'] }}"
        etiqueta-accesible="{{ $contexto_bandeja['etiqueta_accesible'] }}"></back-navigation>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-preregistro.js') }}"></script>
@endsection
