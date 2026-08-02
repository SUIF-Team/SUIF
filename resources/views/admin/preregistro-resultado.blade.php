@extends('layouts.admin')

@section('title', 'SUIF — Resultado de revisión documental')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-preregistro.css') }}">
@endsection

@section('content')
@php
    $resultado_aprobado = $tipo_resultado === 'aprobado';
    $titulo_resultado = $resultado_aprobado ? 'SOLICITUD APROBADA' : 'SOLICITUD EN REVISIÓN';
    $clase_resultado = $resultado_aprobado
        ? 'admin-preregistro-paso--completado'
        : 'admin-preregistro-paso--actual';
    $iniciales = mb_strtoupper(
        mb_substr($participante['nombre'], 0, 1).mb_substr($participante['primer_apellido'], 0, 1)
    );
    $datos_vista = ['participante' => $participante, 'estados' => $estados];
@endphp

<section
    id="preregistro-admin-app"
    class="admin-preregistro-flujo admin-preregistro-resultado-flujo"
    data-preregistro-admin
    data-vista='@json($datos_vista)'
    aria-labelledby="resultado-preregistro-titulo"
    v-cloak>
    <header class="admin-preregistro-tarjeta admin-preregistro-perfil">
        <div class="admin-preregistro-usuario">
            <span class="admin-preregistro-avatar" aria-hidden="true">{{ $iniciales }}</span>
            <div>
                <h1 id="resultado-preregistro-titulo">{{ $participante['nombre_completo'] }}</h1>
                <p>CURP: {{ $participante['curp'] }} · Folio {{ $participante['folio'] }} · {{ $participante['entidad_federativa'] }}</p>
            </div>
        </div>
        <span class="admin-preregistro-estado admin-preregistro-estado--revision" role="status">{{ $estados['general'] }}</span>
    </header>

    <nav class="admin-preregistro-progreso" aria-label="Progreso del trámite">
        <div class="admin-preregistro-paso admin-preregistro-paso--completado">
            <span class="admin-preregistro-paso-titulo">Pre-registro</span>
            <span class="admin-preregistro-paso-estado">{{ $estados['preregistro'] }}</span>
        </div>
        <div class="admin-preregistro-paso {{ $clase_resultado }}" @if (! $resultado_aprobado) aria-current="step" @endif>
            <span class="admin-preregistro-paso-titulo">Documentación</span>
            <span class="admin-preregistro-paso-estado">{{ $estados['documentacion'] }}</span>
        </div>
    </nav>

    <main class="admin-preregistro-tarjeta admin-preregistro-resultado-principal">
        <div class="admin-preregistro-paso admin-preregistro-resultado-mensaje {{ $clase_resultado }}">
            <h2 class="admin-preregistro-paso-estado">{{ $titulo_resultado }}</h2>
        </div>
    </main>

    <back-navigation
        destino="{{ route('admin.participantes.index') }}"
        etiqueta="Volver a la bandeja"
        etiqueta-accesible="Volver a la bandeja de pre-registros"></back-navigation>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset('assets/js/pages/admin-preregistro.js') }}"></script>
@endsection
