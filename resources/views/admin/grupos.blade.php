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
            <a class="admin-sedes-boton admin-sedes-boton--primario" href="{{ route('admin.grupos.create') }}">
                <span aria-hidden="true">+</span> Nuevo grupo
            </a>
        </header>

        <section class="admin-sedes-estadisticas" aria-label="Resumen de grupos">
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Grupos registrados</h2>
                <p class="admin-sedes-estadistica--azul">{{ number_format($resumen['grupos_registrados']) }}</p>
            </article>
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Lugares disponibles</h2>
                <p class="admin-sedes-estadistica--verde">{{ number_format($resumen['lugares_disponibles']) }}</p>
            </article>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-filtros" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.grupos.index') }}" class="admin-sedes-filtros-formulario">
                <div class="admin-sedes-campo">
                    <label for="sede">Sede</label>
                    <select id="sede" name="sede">
                        <option value="">Todas</option>
                        @foreach($sedes as $sede)
                            <option value="{{ $sede['id'] }}" @selected((string) ($filtros['sede'] ?? '') === (string) $sede['id'])>
                                {{ $sede['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-sedes-campo">
                    <label for="estado">Estatus</label>
                    <select id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="con-cupo" @selected(($filtros['estado'] ?? '') === 'con-cupo')>Con cupo</option>
                        <option value="sin-cupo" @selected(($filtros['estado'] ?? '') === 'sin-cupo')>Sin cupo</option>
                    </select>
                </div>
                <div class="admin-sedes-filtros-acciones">
                    <button class="admin-sedes-boton admin-sedes-boton--filtrar" type="submit">Filtrar</button>
                    <a class="admin-sedes-boton admin-sedes-boton--limpiar" href="{{ route('admin.grupos.index') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-tabla-contenedor" aria-label="Lista de grupos">
            <div class="admin-sedes-tabla-responsive">
                <table class="admin-sedes-tabla">
                    <thead>
                        <tr>
                            <th>Sede</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Cupo</th>
                            <th>Estatus</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grupos as $grupo)
                            <tr>
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
                                    <span class="admin-sedes-estado admin-sedes-estado--{{ $grupo['estado_clave'] }}">
                                        {{ $grupo['estado'] }}
                                    </span>
                                </td>
                                <td>
                                    <a class="admin-sedes-editar" href="{{ route('admin.grupos.edit', $grupo['id']) }}">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="admin-sedes-vacio">No se encontraron grupos con los filtros seleccionados.</td>
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
