@extends('layouts.persona')

@section('title', 'SUIF — Selección de sede')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-sede.css') }}">
@endpush

@section('content')
<section
    class="sede-shell"
    @if(!$confirmada)
        data-sedes-participante
        data-disponibilidad-url="{{ route('persona.sede.disponibilidad') }}"
    @endif>
    @if($errors->any())
        <div class="sede-alerta sede-alerta--error" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($confirmada)
        <div class="sede-confirmada">
            <span class="sede-confirmada__icono" aria-hidden="true">✓</span>
            <h1>¡Sede confirmada!</h1>
            <p class="sede-muted">Tu lugar quedó apartado para la evaluación.</p>

            <div class="sede-resumen">
                <p class="sede-resumen__etiqueta">Sede seleccionada</p>
                <p class="sede-resumen__nombre">{{ $sede['nombre'] }}</p>
                <dl class="sede-resumen__datos">
                    <dt>Dirección</dt><dd>{{ $sede['direccion'] }}</dd>
                    <dt>Fecha</dt><dd>{{ $sede['fecha'] }}</dd>
                    <dt>Horario</dt><dd>{{ $sede['horario'] }}</dd>
                </dl>
            </div>

            <div class="sede-acciones">
                <button type="button" class="sede-boton sede-boton--secundario">Generar comprobante</button>
                <a href="{{ route('persona.dashboard') }}" class="sede-boton">Continuar</a>
            </div>
        </div>
    @else
        <h1>Elige tu sede de evaluación</h1>
        <p class="sede-muted">Selecciona dónde presentarás tu evaluación. Los lugares se actualizan automáticamente.</p>

        <form method="GET" action="{{ route('persona.sede.index') }}" class="sede-filtro">
            <input type="search" name="buscar" placeholder="Buscar por nombre o dirección…" value="{{ $buscarActual }}">
            <button type="submit" class="sede-boton sede-boton--filtrar">Filtrar</button>
            @if($buscarActual !== '')
                <a href="{{ route('persona.sede.index') }}" class="sede-boton sede-boton--secundario">Limpiar</a>
            @endif
        </form>

        <p class="sede-contador">Sedes programadas · {{ count($sedes) }}</p>

        <div class="sede-lista" aria-live="polite">
            @forelse($sedes as $sede)
                <article class="sede-tarjeta" data-evaluacion-id="{{ $sede['evaluacion_id'] }}">
                    <div class="sede-tarjeta__info">
                        <h2 class="sede-tarjeta__nombre">{{ $sede['nombre'] }}</h2>
                        <p class="sede-tarjeta__direccion">{{ $sede['direccion'] }}</p>
                        <div class="sede-tarjeta__meta">
                            <span class="sede-chip">
                                {{ \Illuminate\Support\Carbon::parse($sede['fecha_inicio'])->format('d/m/Y') }}
                                @if($sede['fecha_inicio'] !== $sede['fecha_fin'])
                                    –{{ \Illuminate\Support\Carbon::parse($sede['fecha_fin'])->format('d/m/Y') }}
                                @endif
                            </span>
                            <span class="sede-fecha">{{ $sede['hora_inicio'] }}–{{ $sede['hora_fin'] }} h</span>
                        </div>
                    </div>
                    <div class="sede-tarjeta__cupo">
                        <span
                            class="sede-cupo sede-cupo--{{ !$sede['con_cupo'] ? 'lleno' : ($sede['disponibles'] <= 15 ? 'bajo' : 'libre') }}"
                            data-cupo-estado>
                            <span data-cupo-disponible>{{ $sede['disponibles'] }}</span> de {{ $sede['cupo'] }} disponibles
                        </span>
                        <small data-cupo-etiqueta>{{ $sede['con_cupo'] ? 'Lugares disponibles' : 'Sin cupo' }}</small>
                    </div>
                    <form method="POST" action="{{ route('persona.sede.seleccionar') }}">
                        @csrf
                        <input type="hidden" name="evaluacion_id" value="{{ $sede['evaluacion_id'] }}">
                        <button
                            type="submit"
                            class="sede-boton {{ $sede['con_cupo'] ? '' : 'sede-boton--deshabilitado' }}"
                            data-seleccionar-sede
                            @disabled(!$sede['con_cupo'])>
                            {{ $sede['con_cupo'] ? 'Seleccionar' : 'Sin cupo' }}
                        </button>
                    </form>
                </article>
            @empty
                <p class="sede-muted">No hay sedes programadas que coincidan con tu búsqueda.</p>
            @endforelse
        </div>
    @endif
</section>
@endsection

@if(!$confirmada)
    @push('scripts')
    <script src="{{ asset_versionado('assets/js/pages/persona-sede.js') }}"></script>
    @endpush
@endif
