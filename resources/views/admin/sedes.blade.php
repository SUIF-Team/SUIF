@extends('layouts.admin')

@section('title', 'SUIF — Gestión de sedes')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
@endsection

@section('content')
<section class="admin-sedes" aria-labelledby="admin-sedes-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-sedes-titulo">Gestión de sedes</h1>
                <p>Administra las sedes disponibles y su programación de evaluación.</p>
            </div>
            <a class="admin-sedes-boton admin-sedes-boton--primario" href="{{ route('admin.sedes.create') }}">
                <span aria-hidden="true">+</span> Nueva sede
            </a>
        </header>

        <section class="admin-sedes-estadisticas" aria-label="Resumen de sedes">
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Sedes con cupo</h2>
                <p class="admin-sedes-estadistica--azul">{{ number_format($resumen['sedes_con_cupo']) }}</p>
            </article>
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Lugares disponibles</h2>
                <p class="admin-sedes-estadistica--verde">{{ number_format($resumen['lugares_disponibles']) }}</p>
            </article>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-filtros" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.sedes.index') }}" class="admin-sedes-filtros-formulario">
                <div class="admin-sedes-campo">
                    <label for="buscar">Buscar sede</label>
                    <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}">
                </div>
                <div class="admin-sedes-campo">
                    <label for="ubicacion">Ubicación</label>
                    <input id="ubicacion" name="ubicacion" type="search" value="{{ $filtros['ubicacion'] ?? '' }}">
                </div>
                <div class="admin-sedes-campo">
                    <label for="estado">Estatus</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="con-cupo" @selected(($filtros['estado'] ?? '') === 'con-cupo')>Con cupo</option>
                        <option value="sin-cupo" @selected(($filtros['estado'] ?? '') === 'sin-cupo')>Sin cupo</option>
                        <option value="pendiente" @selected(($filtros['estado'] ?? '') === 'pendiente')>Pendiente</option>
                    </select>
                </div>
                <div class="admin-sedes-filtros-acciones">
                    <button class="admin-sedes-boton admin-sedes-boton--filtrar" type="submit">Filtrar</button>
                    <a class="admin-sedes-boton admin-sedes-boton--limpiar" href="{{ route('admin.sedes.index') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-tabla-contenedor" aria-label="Lista de sedes">
            <div class="admin-sedes-tabla-responsive">
                <table class="admin-sedes-tabla">
                    <thead>
                        <tr>
                            <th>Sede</th>
                            <th>Ubicación</th>
                            <th>Capacidad</th>
                            <th>Programación</th>
                            <th>Estatus</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sedes as $sede)
                            <tr>
                                <td class="admin-sedes-tabla-nombre">{{ $sede['nombre'] }}</td>
                                <td>{{ $sede['direccion'] }}</td>
                                <td>
                                    <strong>{{ $sede['ocupados'] }} / {{ $sede['cupo'] }}</strong>
                                    <small>{{ $sede['disponibles'] }} disponibles</small>
                                </td>
                                <td>
                                    @if($sede['programada'])
                                        {{ \Illuminate\Support\Carbon::parse($sede['fecha_inicio'])->format('d/m/Y') }}
                                        @if($sede['fecha_inicio'] !== $sede['fecha_fin'])
                                            –{{ \Illuminate\Support\Carbon::parse($sede['fecha_fin'])->format('d/m/Y') }}
                                        @endif
                                        <small>{{ $sede['hora_inicio'] }}–{{ $sede['hora_fin'] }} h</small>
                                    @else
                                        <span class="admin-sedes-texto-atenuado">Sin programación</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-sedes-estado admin-sedes-estado--{{ $sede['estado_clave'] }}">
                                        {{ $sede['estado'] }}
                                    </span>
                                </td>
                                <td>
                                    <a class="admin-sedes-editar" href="{{ route('admin.sedes.edit', $sede['id']) }}">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="admin-sedes-vacio">No se encontraron sedes con los filtros seleccionados.</td>
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
<script src="{{ asset_versionado('assets/js/pages/admin-sedes.js') }}"></script>
@endsection
