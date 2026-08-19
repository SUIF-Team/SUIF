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
        <h1>Elige tu sede y horario</h1>
        <p class="sede-muted">Selecciona dónde y cuándo presentarás tu evaluación. Cada sede puede aplicar el examen en varios horarios; los lugares se actualizan automáticamente.</p>

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
                <article class="sede-tarjeta" data-sede-tarjeta>
                    <div class="sede-tarjeta__info">
                        <h2 class="sede-tarjeta__nombre">{{ $sede['nombre'] }}</h2>
                        <p class="sede-tarjeta__direccion">{{ $sede['direccion'] }}</p>
                    </div>
                    <form method="POST" action="{{ route('persona.sede.seleccionar') }}" class="sede-tarjeta__seleccion">
                        @csrf
                        <fieldset class="sede-horarios">
                            <legend class="sede-horarios__titulo">
                                Horarios disponibles · {{ count($sede['horarios']) }}
                            </legend>
                            @foreach($sede['horarios'] as $horario)
                                <label class="sede-horario {{ $horario['con_cupo'] ? '' : 'sede-horario--lleno' }}" data-evaluacion-id="{{ $horario['evaluacion_id'] }}">
                                    <input
                                        type="radio"
                                        name="evaluacion_id"
                                        value="{{ $horario['evaluacion_id'] }}"
                                        data-horario-opcion
                                        @disabled(!$horario['con_cupo'])>
                                    <span class="sede-horario__datos">
                                        <span class="sede-chip">
                                            {{ \Illuminate\Support\Carbon::parse($horario['fecha_inicio'])->format('d/m/Y') }}
                                            @if($horario['fecha_inicio'] !== $horario['fecha_fin'])
                                                –{{ \Illuminate\Support\Carbon::parse($horario['fecha_fin'])->format('d/m/Y') }}
                                            @endif
                                        </span>
                                        <span class="sede-fecha">{{ $horario['hora_inicio'] }}–{{ $horario['hora_fin'] }} h</span>
                                    </span>
                                    <span class="sede-horario__cupo">
                                        <span
                                            class="sede-cupo sede-cupo--{{ !$horario['con_cupo'] ? 'lleno' : ($horario['disponibles'] <= 15 ? 'bajo' : 'libre') }}"
                                            data-cupo-estado>
                                            <span data-cupo-disponible>{{ $horario['disponibles'] }}</span> de {{ $sede['cupo'] }} disponibles
                                        </span>
                                        <small data-cupo-etiqueta>{{ $horario['con_cupo'] ? 'Lugares disponibles' : 'Sin cupo' }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        <button
                            type="submit"
                            class="sede-boton {{ $sede['con_cupo'] ? '' : 'sede-boton--deshabilitado' }}"
                            data-seleccionar-sede
                            @disabled(!$sede['con_cupo'])>
                            {{ $sede['con_cupo'] ? 'Seleccionar horario' : 'Sin cupo' }}
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
