{{--
    admin/grupos.blade.php
    Bandeja de grupos: cada grupo es una aplicación del examen en una sede.
    Reutiliza el diseño y las clases de la gestión de sedes.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Gestión de grupos')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
@endsection

@section('content')
<section class="admin-sedes" aria-labelledby="admin-grupos-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-grupos-titulo">Gestión de grupos</h1>
                <p>Registra las aplicaciones del examen y la sede en la que se presentan.</p>
            </div>
            <a class="boton boton--primario" href="{{ route('admin.grupos.create') }}">
                <span aria-hidden="true">+</span> Nuevo grupo
            </a>
        </header>

        <section class="admin-sedes-estadisticas" aria-label="Resumen de grupos">
            <article class="tarjeta tarjeta--compacta admin-sedes-estadistica">
                <h2>Grupos registrados</h2>
                <p class="admin-sedes-estadistica--azul">{{ number_format($resumen['grupos_registrados']) }}</p>
            </article>
            <article class="tarjeta tarjeta--compacta admin-sedes-estadistica">
                <h2>Lugares disponibles</h2>
                <p class="admin-sedes-estadistica--verde">{{ number_format($resumen['lugares_disponibles']) }}</p>
            </article>
        </section>

        <section class="tarjeta tarjeta--compacta" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.grupos.index') }}" class="filtros"
                  data-filtros-tabla="admin-grupos-tabla">
                <div class="campo">
                    <label class="etiqueta" for="sede">Sede</label>
                    <select class="control" id="sede" name="sede">
                        <option value="">Todas</option>
                        @foreach($sedes as $sede)
                            <option value="{{ $sede['id'] }}" @selected((string) ($filtros['sede'] ?? '') === (string) $sede['id'])>
                                {{ $sede['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="campo">
                    <label class="etiqueta" for="estado">Estado</label>
                    <select class="control" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="con-cupo" @selected(($filtros['estado'] ?? '') === 'con-cupo')>Con cupo</option>
                        <option value="sin-cupo" @selected(($filtros['estado'] ?? '') === 'sin-cupo')>Sin cupo</option>
                    </select>
                </div>
                <div class="admin-sedes-filtros-acciones">
                    <button class="boton boton--primario" type="submit">Filtrar</button>
                    <a class="boton boton--peligro" href="{{ route('admin.grupos.index') }}" data-filtros-limpiar>Limpiar</a>
                </div>
            </form>
        </section>

        <section class="tabla-contenedor" aria-label="Lista de grupos">
            <div class="tabla-responsive tabla-desplazable">
                <table id="admin-grupos-tabla" class="tabla tabla--centrada admin-sedes-tabla">
                    <thead>
                        <tr>
                            <th>Sede</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Cupo</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grupos as $grupo)
                            <tr data-filtro-sede="{{ $grupo['sede_id'] }}"
                                data-filtro-estado="{{ $grupo['estado_clave'] }}">
                                <td class="admin-sedes-tabla-nombre">
                                    {{ $grupo['sede_nombre'] }}
                                    <small>{{ $grupo['sede_direccion'] }}</small>
                                </td>
                                <td>
                                    {{ \Illuminate\Support\Carbon::parse($grupo['fecha_inicio'])->format('d/m/Y') }}
                                    <small>{{ $grupo['hora_inicio'] }} h</small>
                                </td>
                                <td>
                                    {{ \Illuminate\Support\Carbon::parse($grupo['fecha_fin'])->format('d/m/Y') }}
                                    <small>{{ $grupo['hora_fin'] }} h</small>
                                </td>
                                <td>
                                    <strong>{{ $grupo['ocupados'] }} / {{ $grupo['cupo'] }}</strong>
                                    <small>{{ $grupo['disponibles'] }} disponibles</small>
                                </td>
                                <td>
                                    <span class="estado estado--{{ $grupo['estado_papel'] }}">
                                        {{ $grupo['estado'] }}
                                    </span>
                                </td>
                                <td>
                                    <a class="boton boton--texto" href="{{ route('admin.grupos.edit', $grupo['id']) }}">Editar</a>
                                </td>
                            </tr>
                        @endforeach

                        {{-- El renglón se escribe siempre: con la tabla filtrándose
                             en el navegador, el aviso aparece sin volver al servidor. --}}
                        <tr data-tabla-vacia @unless($grupos->isEmpty()) hidden @endunless>
                            <td colspan="6" class="vacio" role="status">No se encontraron grupos con los filtros seleccionados.</td>
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
