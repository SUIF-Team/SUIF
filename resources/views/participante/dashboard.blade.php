@extends('layouts.participante')

@section('title', 'Dashboard del participante')

@section('content')
<section class="dashboard" aria-labelledby="dashboard-title">
    <header class="dashboard__header">
        <div>
            <h1 id="dashboard-title">Hola, {{ $participante['nombre'] }}</h1>
            <p>Folio {{ $participante['folio'] }} · Este es tu avance. Continúa donde te quedaste.</p>
        </div>
        <span class="dashboard-status dashboard-status--{{ $tramite['clase'] }}">{{ $tramite['texto'] }}</span>
    </header>

    <div class="process-list">
        @foreach ($pasos as $paso)
            <article class="process-card process-card--{{ $paso['estado'] }}">
                <div class="process-card__number">{{ $paso['numero'] }}</div>
                <div class="process-card__content">
                    <h2>{{ $paso['titulo'] }}</h2>
                    <p>{{ $paso['descripcion'] }}</p>
                </div>
                <div class="process-card__actions">
                    <span class="process-badge process-badge--{{ $paso['estado'] }}">{{ $paso['etiqueta'] }}</span>
                    @if ($paso['habilitado'])
                        <a class="process-button" href="{{ route($paso['ruta']) }}">{{ $paso['accion'] }}</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
