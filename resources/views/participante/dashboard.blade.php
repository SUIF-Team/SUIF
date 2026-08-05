@extends('layouts.participante')

@section('title', 'Dashboard del participante')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/participante-dashboard.css') }}">
@endsection

@section('content')
<section class="participante-dashboard" aria-labelledby="dashboard-title">
    <header class="participante-dashboard__header">
        <div>
            <h1 id="dashboard-title">Hola, {{ $participante['nombre'] }}</h1>
            <p>@if($participante['identificador']){{ $participante['identificador'] }} · @endif Este es tu avance. Continúa donde te quedaste.</p>
        </div>
        <span class="dashboard-status dashboard-status--{{ $tramite['clase'] }}" role="status">
            {{ $tramite['texto'] }}
        </span>
    </header>

    <div class="process-list" aria-label="Avance del proceso de certificación">
        @foreach ($pasos as $paso)
            <article
                class="process-card process-card--{{ $paso['estado'] }}{{ $paso['mostrarBoton'] ? ' process-card--interactive' : '' }}"
                aria-labelledby="paso-{{ $paso['numero'] }}-titulo">
                {{-- Las etapas completadas muestran palomita en lugar del número (Figma 1370:775). --}}
                <div class="process-card__number" aria-hidden="true">{{ $paso['estado'] === 'completed' ? '✓' : $paso['numero'] }}</div>
                <div class="process-card__content">
                    <h2 id="paso-{{ $paso['numero'] }}-titulo">{{ $paso['titulo'] }}</h2>
                    <p>{{ $paso['descripcion'] }}</p>
                </div>
                <div class="process-card__actions">
                                    <div class="process-card__actions">
                    <span class="process-badge process-badge--{{ $paso['estado'] }}">{{ $paso['etiqueta'] }}</span>
                        @if ($paso['mostrarBoton'])
                        <a class="process-button{{ $paso['estado'] === 'completed' ? ' process-button--ver' : '' }}" href="{{ route($paso['ruta']) }}">
                            {{ $paso['textoBoton'] }}
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
