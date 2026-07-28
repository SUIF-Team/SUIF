@extends('layouts.participante')

@section('title', 'Dashboard del participante')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/participante-dashboard.css') }}">
@endsection

@section('content')
<section class="participante-dashboard" aria-labelledby="dashboard-title">
    <header class="participante-dashboard__header">
        <div>
            <h1 id="dashboard-title">Hola, {{ $participante['nombre'] }}</h1>
            <p>Folio {{ $participante['folio'] }} · Este es tu avance. Continúa donde te quedaste.</p>
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
                <div class="process-card__number" aria-hidden="true">{{ $paso['numero'] }}</div>
                <div class="process-card__content">
                    <h2 id="paso-{{ $paso['numero'] }}-titulo">{{ $paso['titulo'] }}</h2>
                    <p>{{ $paso['descripcion'] }}</p>
                </div>
                <div class="process-card__actions">
                    <span class="process-badge process-badge--{{ $paso['estado'] }}">{{ $paso['etiqueta'] }}</span>
                    @if ($paso['mostrarBoton'])
                        @if ($modoDemo)
                            <span class="process-button process-button--demo" aria-disabled="true">
                                Continuar
                            </span>
                        @else
                            <a class="process-button" href="{{ route($paso['ruta']) }}">
                                Continuar
                            </a>
                        @endif
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
