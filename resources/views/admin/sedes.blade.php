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
            <a class="boton boton--primario" href="{{ route('admin.sedes.create') }}">
                <span aria-hidden="true">+</span> Nueva sede
            </a>
        </header>

        <section class="admin-sedes-estadisticas" aria-label="Resumen de sedes">
            <article class="tarjeta tarjeta--compacta admin-sedes-estadistica">
                <h2>Sedes con cupo</h2>
                <p class="admin-sedes-estadistica--azul">{{ number_format($resumen['sedes_con_cupo']) }}</p>
            </article>
            <article class="tarjeta tarjeta--compacta admin-sedes-estadistica">
                <h2>Lugares disponibles</h2>
                <p class="admin-sedes-estadistica--verde">{{ number_format($resumen['lugares_disponibles']) }}</p>
            </article>
        </section>

        <section class="tarjeta tarjeta--compacta" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.sedes.index') }}" class="filtros"
                  data-filtros-tabla="admin-sedes-tabla">
                <div class="campo">
                    <label class="etiqueta" for="buscar">Buscar sede</label>
                    <input class="control" id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}">
                </div>
                <div class="campo">
                    <label class="etiqueta" for="ubicacion">Ubicación</label>
                    <input class="control" id="ubicacion" name="ubicacion" type="search" value="{{ $filtros['ubicacion'] ?? '' }}">
                </div>
                <div class="campo">
                    <label class="etiqueta" for="estado">Estado</label>
                    <select class="control" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="con-cupo" @selected(($filtros['estado'] ?? '') === 'con-cupo')>Con cupo</option>
                        <option value="sin-cupo" @selected(($filtros['estado'] ?? '') === 'sin-cupo')>Sin cupo</option>
                        <option value="pendiente" @selected(($filtros['estado'] ?? '') === 'pendiente')>Por programar</option>
                    </select>
                </div>
                <div class="admin-sedes-filtros-acciones">
                    <button class="boton boton--primario" type="submit">Filtrar</button>
                    <a class="boton boton--peligro" href="{{ route('admin.sedes.index') }}" data-filtros-limpiar>Limpiar</a>
                </div>
            </form>
        </section>

        <section class="tabla-contenedor" aria-label="Lista de sedes">
            <div class="tabla-responsive tabla-desplazable">
                <table id="admin-sedes-tabla" class="tabla tabla--centrada-desde-3 admin-sedes-tabla">
                    <thead>
                        <tr>
                            <th>Sede</th>
                            <th>Ubicación</th>
                            <th>Capacidad</th>
                            <th>Grupos</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sedes as $sede)
                            <tr data-filtro-buscar="{{ $sede['nombre'] }}"
                                data-filtro-ubicacion="{{ $sede['direccion'] }}"
                                data-filtro-estado="{{ $sede['estado_clave'] }}">
                                <td class="admin-sedes-tabla-nombre">{{ $sede['nombre'] }}</td>
                                <td>{{ $sede['direccion'] }}</td>
                                <td>
                                    <strong>{{ $sede['ocupados'] }} / {{ $sede['cupo'] * count($sede['horarios']) }}</strong>
                                    <small>{{ $sede['cupo'] }} por aplicación</small>
                                </td>
                                {{-- La programación se administra en el módulo Grupos; aquí
                                     sólo interesa cuántos tiene registrados la sede. --}}
                                <td>{{ count($sede['horarios']) }}</td>
                                <td>
                                    <span class="estado estado--{{ $sede['estado_clave'] }}">
                                        {{ $sede['estado'] }}
                                    </span>
                                </td>
                                <td>
                                    <a class="boton boton--texto" href="{{ route('admin.sedes.edit', $sede['id']) }}">Editar</a>
                                </td>
                            </tr>
                        @endforeach

                        {{-- El renglón se escribe siempre: con la tabla filtrándose
                             en el navegador, el aviso aparece sin volver al servidor. --}}
                        <tr data-tabla-vacia @unless($sedes->isEmpty()) hidden @endunless>
                            <td colspan="6" class="vacio" role="status">No se encontraron sedes con los filtros seleccionados.</td>
                        </tr>
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
<script src="{{ asset_versionado('assets/js/pages/admin-filtros-tabla.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-sedes.js') }}"></script>
@endsection
