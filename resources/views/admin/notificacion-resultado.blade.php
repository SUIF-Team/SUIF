@extends('layouts.admin')

@section('title', $notificacion['titulo_pagina'])

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
@if(!empty($formato))
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-pago.css') }}">
@endif
@endsection

@section('content')
@php
    $datos_vista = [
        'persona' => $persona,
        'estados' => [],
    ];
@endphp
<section
    id="preregistro-admin-app"
    class="admin-preregistro-flujo admin-preregistro-resultado-flujo"
    data-preregistro-admin
    data-contexto="{{ $notificacion['contexto'] }}"
    data-vista='@json($datos_vista)'
    aria-labelledby="resultado-notificacion-titulo"
    v-cloak>
    {{-- La persona la pinta Vue desde data-vista: como texto de Blade dentro
         de la raíz, Vue compilaría lo que ella hubiera escrito entre llaves.
         Se leen los campos tal cual y no los computados de
         admin-preregistro.js, que esperan nombre y primer_apellido. --}}
    <header class="tarjeta admin-preregistro-perfil">
        <div class="admin-preregistro-usuario">
            <span class="admin-preregistro-avatar" aria-hidden="true">@{{ persona.iniciales }}</span>
            <div>
                <h1 id="resultado-notificacion-titulo">@{{ persona.nombre_completo }}</h1>
                <p>CURP: @{{ persona.curp }} · @{{ persona.entidad_federativa }}</p>
            </div>
        </div>
        <span class="estado {{ $notificacion['clase_estado'] }}" role="status">
            {{ $notificacion['estado_general'] }}
        </span>
    </header>

    <nav class="pasos admin-preregistro-progreso {{ $notificacion['clase_progreso'] }}" aria-label="Progreso del trámite">
        @foreach ($notificacion['pasos'] as $paso)
            <div class="paso {{ $paso['clase'] }}" @if ($paso['actual']) aria-current="step" @endif>
                <span class="paso__titulo">{{ $paso['titulo'] }}</span>
                <span class="paso__estado">{{ $paso['estado'] }}</span>
            </div>
        @endforeach
    </nav>

    <main class="tarjeta admin-preregistro-resultado-principal">
        <div class="admin-preregistro-resultado-mensaje {{ $notificacion['clase_mensaje'] }}" role="status" aria-live="polite">
            <h2>{{ $notificacion['titulo'] }}</h2>
        </div>
    </main>

    @include('partials.admin.formato-pago', ['formato' => $formato ?? null])

    @include('partials.admin.acciones-reversion', ['acciones' => $notificacion['acciones'] ?? []])

    <back-navigation
        destino="{{ $notificacion['ruta_regreso'] }}"
        etiqueta="{{ $notificacion['etiqueta_regreso'] }}"
        etiqueta-accesible="{{ $notificacion['etiqueta_regreso_accesible'] }}"></back-navigation>
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-preregistro.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-reversion.js') }}"></script>
@endsection
