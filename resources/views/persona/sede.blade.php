{{--
    persona/sede.blade.php
    Migrado desde: app/views/persona/sede.php
    Vista para seleccionar sede y horario del proceso de certificación.
--}}
@extends('layouts.persona')

@section('title', 'SUIF — Selección de Sede')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-sede.css') }}">
@endpush

@section('content')
<section class="sede-shell">

    @if($errors->any())
        <div class="sede-alerta sede-alerta--error">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($confirmada)

        <div class="sede-confirmada">
            <span class="sede-confirmada__icono">✓</span>
            <h1>¡Sede confirmada!</h1>
            <p class="sede-muted">Tu lugar quedó apartado. Por favor genere su comprobante.</p>

            <div class="sede-resumen">
                <p class="sede-resumen__etiqueta">Sede seleccionada</p>
                <p class="sede-resumen__nombre">{{ $sede['nombre'] }}</p>
                <dl class="sede-resumen__datos">
                    <dt>Dirección</dt><dd>{{ $sede['direccion'] }}</dd>
                    <dt>Entidad federativa</dt><dd>{{ $sede['entidad'] }}</dd>
                    <dt>Fecha</dt><dd>{{ $sede['fecha'] }}</dd>
                    <dt>Hora</dt><dd>{{ $sede['hora'] }}</dd>
                </dl>
            </div>

            <div class="sede-acciones">
                <button type="button" class="sede-boton sede-boton--secundario">Generar comprobante</button>
                <a href="{{ route('persona.dashboard') }}" class="sede-boton">Continuar</a>
            </div>

            @if(config('app.debug'))
                <p class="sede-demo"><a href="{{ route('persona.sede.reiniciar') }}">Elegir otra sede (demo)</a></p>
            @endif
        </div>

    @else

        <h1>Elige tu sede de evaluación</h1>
        <p class="sede-muted">Selecciona la sede donde presentarás tu examen de certificación. Revisa los cupos disponibles antes de elegir.</p>

        <form method="GET" action="{{ route('persona.sede.index') }}" class="sede-filtro">
            <select name="entidad">
                <option value="">Todas las entidades</option>
                @foreach($entidades as $entidad)
                    <option value="{{ $entidad }}" {{ $entidadActual === $entidad ? 'selected' : '' }}>{{ $entidad }}</option>
                @endforeach
            </select>
            <input type="text" name="buscar" placeholder="Buscar sede…" value="{{ $buscarActual }}">
            <button type="submit" class="sede-boton sede-boton--filtrar">Filtrar</button>
        </form>

        <p class="sede-contador">Sedes disponibles · {{ count($sedes) }}</p>

        <div class="sede-lista">
            @forelse($sedes as $sede)
                <div class="sede-tarjeta">
                    <div class="sede-tarjeta__info">
                        <p class="sede-tarjeta__nombre">{{ $sede['nombre'] }}</p>
                        <p class="sede-tarjeta__direccion">{{ $sede['direccion'] }}</p>
                        <div class="sede-tarjeta__meta">
                            <span class="sede-chip">{{ $sede['entidad'] }}</span>
                            <span class="sede-fecha">{{ $sede['fecha'] }} · {{ $sede['hora'] }}</span>
                        </div>
                    </div>
                    <div class="sede-tarjeta__cupo">
                        <span class="sede-cupo sede-cupo--{{ $sede['sin_cupo'] ? 'lleno' : ($sede['cupo_disponible'] <= 15 ? 'bajo' : 'libre') }}">
                            {{ $sede['sin_cupo'] ? 'Sin cupo' : $sede['cupo_usado'].' / '.$sede['cupo_total'].' lugares' }}
                        </span>
                        <small>Cupos disponibles</small>
                    </div>
                    @if($sede['sin_cupo'])
                        <span class="sede-boton sede-boton--deshabilitado">Sin cupo</span>
                    @else
                        <form method="POST" action="{{ route('persona.sede.seleccionar') }}">
                            @csrf
                            <input type="hidden" name="sede_id" value="{{ $sede['id'] }}">
                            <button type="submit" class="sede-boton">Seleccionar</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="sede-muted">No hay sedes que coincidan con tu búsqueda.</p>
            @endforelse
        </div>

    @endif

</section>
@endsection