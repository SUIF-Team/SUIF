{{--
    Bandeja de convocatorias.

    Se listan todas, incluidas las cerradas y las interrumpidas: son el
    historial de la certificación. La tabla informa; cerrar o interrumpir vive
    en la pantalla de edición, junto al resto de lo que se decide sobre una
    convocatoria concreta.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Gestión de convocatorias')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-convocatorias.css') }}">
@endsection

@section('content')
<section class="admin-sedes admin-convocatorias" aria-labelledby="admin-convocatorias-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-convocatorias-titulo">Gestión de convocatorias</h1>
                <p>Administra el calendario y la cuota de recuperación de cada convocatoria.</p>
            </div>
            <a class="admin-sedes-boton admin-sedes-boton--primario" href="{{ route('admin.convocatorias.create') }}">
                <span aria-hidden="true">+</span> Nueva convocatoria
            </a>
        </header>

        <section class="admin-sedes-estadisticas" aria-label="Resumen de convocatorias">
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Convocatorias registradas</h2>
                <p class="admin-sedes-estadistica--azul">{{ number_format($resumen['registradas']) }}</p>
            </article>
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Convocatoria vigente</h2>
                <p @class([
                    'admin-convocatorias-estadistica-texto',
                    'admin-convocatorias-sin-datos' => $resumen['vigente'] === null,
                ])>
                    {{ $resumen['vigente'] ?? 'Ninguna' }}
                </p>
            </article>
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Registro</h2>
                <p class="admin-convocatorias-estadistica-texto">
                    {{ $resumen['registro_abierto'] ? 'Abierto hoy' : 'Cerrado hoy' }}
                </p>
            </article>
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Solicitudes</h2>
                <p class="admin-sedes-estadistica--verde">{{ number_format($resumen['solicitudes_vigente']) }}</p>
            </article>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-filtros" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.convocatorias.index') }}" class="admin-sedes-filtros-formulario">
                <div class="admin-sedes-campo">
                    <label for="buscar">Buscar convocatoria</label>
                    <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}">
                </div>
                <div class="admin-sedes-campo">
                    <label for="estado">Estatus</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        @foreach($estados as $opcion)
                            <option value="{{ $opcion }}" @selected(($filtros['estado'] ?? '') === $opcion)>{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-sedes-filtros-acciones">
                    <button class="admin-sedes-boton admin-sedes-boton--filtrar" type="submit">Filtrar</button>
                    <a class="admin-sedes-boton admin-sedes-boton--limpiar" href="{{ route('admin.convocatorias.index') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-tabla-contenedor" aria-label="Lista de convocatorias">
            <div class="admin-sedes-tabla-responsive">
                <table class="admin-sedes-tabla">
                    <thead>
                        <tr>
                            <th>Convocatoria</th>
                            <th>Cuota</th>
                            <th>Registro</th>
                            <th>Entrega de documentos</th>
                            <th>Vigencia</th>
                            <th>Solicitudes</th>
                            <th>Estatus</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($convocatorias as $convocatoria)
                            <tr>
                                <td class="admin-sedes-tabla-nombre">{{ $convocatoria['nombre'] }}</td>
                                <td>${{ $convocatoria['monto_formateado'] }} {{ config('suif.moneda') }}</td>
                                <td>
                                    <strong>{{ \Illuminate\Support\Carbon::parse($convocatoria['fecha_inicio_registro'])->format('d/m/Y') }}
                                        al {{ \Illuminate\Support\Carbon::parse($convocatoria['fecha_fin_registro'])->format('d/m/Y') }}</strong>
                                    <small>{{ $convocatoria['registro_abierto'] ? 'Abierto hoy' : 'Cerrado hoy' }}</small>
                                </td>
                                <td>{{ \Illuminate\Support\Carbon::parse($convocatoria['fin_fecha_entrega_docs'])->format('d/m/Y') }}</td>
                                <td>
                                    {{ \Illuminate\Support\Carbon::parse($convocatoria['fecha_inicio'])->format('d/m/Y') }}
                                    al {{ \Illuminate\Support\Carbon::parse($convocatoria['fecha_fin'])->format('d/m/Y') }}
                                </td>
                                <td>{{ number_format($convocatoria['solicitudes']) }}</td>
                                {{-- Sólo el distintivo: desde cuándo lo está se
                                     lee en la pantalla de edición, que es donde
                                     además se puede cambiar. --}}
                                <td>
                                    <span class="admin-sedes-estado admin-convocatorias-estado--{{ $convocatoria['estado_clave'] }}">
                                        {{ $convocatoria['estado'] }}
                                    </span>
                                </td>
                                <td>
                                    <a class="admin-sedes-editar" href="{{ route('admin.convocatorias.edit', $convocatoria['id']) }}">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="admin-sedes-vacio">No se encontraron convocatorias con los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.dashboard') }}"
                etiqueta="Volver al dashboard"
                etiqueta-accesible="Volver al dashboard"></back-navigation>
        </div>
    </div>

</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset_versionado('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-convocatorias.js') }}"></script>
@endsection
