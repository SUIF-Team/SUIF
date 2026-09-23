@extends('layouts.persona')

@section('title', 'Dashboard de la persona')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-dashboard.css') }}">
@endsection

@section('content')
<section class="persona-dashboard" aria-labelledby="dashboard-title">
    <header class="persona-dashboard__header">
        <div>
            <h1 id="dashboard-title">Hola, {{ $persona['nombre'] }}</h1>
            <p>@if($persona['identificador']){{ $persona['identificador'] }} · @endif Este es tu avance. Continúa donde te quedaste.</p>
        </div>
        <span class="estado estado--{{ $tramite['clase'] }} dashboard-status" role="status">
            {{ $tramite['texto'] }}
        </span>
    </header>

    <div class="pasos process-list" aria-label="Avance del proceso de certificación">
        @foreach ($pasos as $paso)
            <article
                class="paso paso--{{ $paso['clase'] }} process-card{{ $paso['mostrarBoton'] ? ' process-card--interactive' : '' }}"
                aria-labelledby="paso-{{ $paso['numero'] }}-titulo">
                {{-- Las etapas completadas muestran palomita en lugar del número (Figma 1370:775). --}}
                <div class="paso__numero" aria-hidden="true">
                    @if($paso['estado'] === 'completed')
                        <i class="fa-solid fa-check"></i>
                    @else
                        {{ $paso['numero'] }}
                    @endif
                </div>
                <div class="process-card__content">
                    <h2 id="paso-{{ $paso['numero'] }}-titulo">{{ $paso['titulo'] }}</h2>
                    <p>{{ $paso['descripcion'] }}</p>
                </div>
                <div class="process-card__actions">
                    <span class="estado estado--{{ $paso['clase'] }}">{{ $paso['etiqueta'] }}</span>
                    @if ($paso['mostrarBoton'])
                        <a
                            class="boton {{ in_array($paso['estado'], ['completed', 'review'], true) ? 'boton--secundario' : 'boton--primario' }}"
                            href="{{ route($paso['ruta']) }}">
                            {{ $paso['textoBoton'] }}
                        </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
