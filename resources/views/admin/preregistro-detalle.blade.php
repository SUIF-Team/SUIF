@extends('layouts.admin')

@section('title', 'SUIF — Detalle de Pre-registro')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
@endsection

@section('content')
@php
    $datos_vista = ['persona' => $persona, 'estados' => $estados];
@endphp
<section id="preregistro-admin-app" class="admin-preregistro-flujo" data-preregistro-admin data-vista='@json($datos_vista)' aria-labelledby="detalle-preregistro-titulo" v-cloak>
    <header class="tarjeta admin-preregistro-perfil">
        <div class="admin-preregistro-usuario">
            <span class="admin-preregistro-avatar" aria-hidden="true">@{{ iniciales }}</span>
            <div>
                <h1 id="detalle-preregistro-titulo">@{{ nombreCompleto }}</h1>
                <p>CURP: @{{ persona.curp }} · @{{ persona.entidad_federativa }}</p>
            </div>
        </div>
        <span class="estado" :class="claseEstadoGeneral" role="status">@{{ estados.general }}</span>
    </header>

    <nav class="pasos admin-preregistro-progreso" aria-label="Progreso del trámite">
        <div class="paso" :class="clasePaso('preregistro')" :aria-current="pasoActual === 'preregistro' ? 'step' : null">
            <span class="paso__titulo">Pre-registro</span>
            <span class="paso__estado">@{{ estados.preregistro }}</span>
        </div>
        <div class="paso" :class="clasePaso('documentacion')" :aria-current="pasoActual === 'documentacion' ? 'step' : null">
            <span class="paso__titulo">Documentación</span>
            <span class="paso__estado">@{{ estados.documentacion }}</span>
        </div>
    </nav>

    <section class="tarjeta admin-preregistro-detalle" aria-labelledby="datos-persona-titulo">
        <h2 id="datos-persona-titulo">Datos de la persona</h2>
        <dl class="admin-preregistro-datos">
            <div v-for="campo in camposPersona" :key="campo.etiqueta" class="admin-preregistro-dato">
                <dt>@{{ campo.etiqueta }}</dt>
                <dd>@{{ campo.valor }}</dd>
            </div>
        </dl>

        @if ($modo_solo_lectura ?? false)
            <div class="admin-preregistro-acciones">
                <a class="boton boton--exito" href="{{ $ruta_documentacion }}">
                    {{ $estados['general'] === 'En revisión' ? 'Revisar documentación' : 'Consultar resolución' }}
                </a>
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
<script src="{{ asset_versionado('assets/js/pages/admin-preregistro.js') }}"></script>
@endsection
