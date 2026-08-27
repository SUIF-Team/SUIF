{{--
    admin/administradores.blade.php
    Bandeja de quienes administran el sistema. Reutiliza el diseño y las clases
    de la gestión de sedes, igual que la de grupos.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Administradores')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-administradores.css') }}">
@endsection

@section('content')
<section class="admin-sedes" aria-labelledby="admin-administradores-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-administradores-titulo">Administradores</h1>
                <p>Da de alta a quienes operan el sistema y decide de qué se encarga cada quien.</p>
            </div>
            <a class="admin-sedes-boton admin-sedes-boton--primario" href="{{ route('admin.administradores.create') }}">
                <span aria-hidden="true">+</span> Nuevo administrador
            </a>
        </header>

        <section class="admin-sedes-estadisticas" aria-label="Resumen de administradores">
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Con acceso</h2>
                <p class="admin-sedes-estadistica--verde">{{ number_format($resumen['activos']) }}</p>
            </article>
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Sin acceso</h2>
                <p class="admin-sedes-estadistica--azul">{{ number_format($resumen['inactivos']) }}</p>
            </article>
            <article class="admin-sedes-tarjeta admin-sedes-estadistica">
                <h2>Superusuarios activos</h2>
                <p class="admin-sedes-estadistica--azul">{{ number_format($resumen['superusuarios']) }}</p>
            </article>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-filtros" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.administradores.index') }}" class="admin-sedes-filtros-formulario">
                <div class="admin-sedes-campo">
                    <label for="buscar">Buscar por nombre o CURP</label>
                    <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}">
                </div>
                <div class="admin-sedes-campo">
                    <label for="rol">Tipo</label>
                    <select id="rol" name="rol">
                        <option value="">Todos</option>
                        @foreach ($roles as $rol)
                            <option value="{{ $rol['nombre'] }}" @selected(($filtros['rol'] ?? '') === $rol['nombre'])>
                                {{ $rol['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-sedes-campo">
                    <label for="estatus">Estatus</label>
                    <select id="estatus" name="estatus">
                        <option value="">Todos</option>
                        <option value="activo" @selected(($filtros['estatus'] ?? '') === 'activo')>Con acceso</option>
                        <option value="inactivo" @selected(($filtros['estatus'] ?? '') === 'inactivo')>Sin acceso</option>
                    </select>
                </div>
                <div class="admin-sedes-filtros-acciones">
                    <button class="admin-sedes-boton admin-sedes-boton--filtrar" type="submit">Filtrar</button>
                    <a class="admin-sedes-boton admin-sedes-boton--limpiar" href="{{ route('admin.administradores.index') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-tabla-contenedor" aria-label="Lista de administradores">
            <div class="admin-sedes-tabla-responsive">
                <table class="admin-sedes-tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>CURP</th>
                            <th>Tipo</th>
                            <th>Entidad</th>
                            <th>Estatus</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($administradores as $administrador)
                            <tr>
                                <td class="admin-sedes-tabla-nombre">{{ $administrador['nombre_completo'] }}</td>
                                <td>{{ $administrador['curp'] }}</td>
                                <td>
                                    <span class="admin-administradores-rol">{{ $administrador['rol'] }}</span>
                                    <small>{{ $administrador['rol_descripcion'] }}</small>
                                </td>
                                <td>{{ $administrador['entidad_federativa'] }}</td>
                                <td>
                                    <span class="admin-sedes-estado admin-administradores-estado--{{ $administrador['estatus_clave'] }}">
                                        {{ $administrador['estatus'] }}
                                    </span>
                                </td>
                                <td>
                                    @if ($administrador['activo'])
                                        <a class="admin-sedes-editar" href="{{ route('admin.administradores.edit', $administrador['id']) }}">Editar</a>
                                    @else
                                        {{-- Recuperar el acceso es un solo movimiento: no hace falta
                                             pasar por el formulario para devolverlo. --}}
                                        <form method="POST" action="{{ route('admin.administradores.reactivar', $administrador['id']) }}">
                                            @csrf
                                            <button class="admin-sedes-editar admin-administradores-reactivar" type="submit">
                                                Dar acceso
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="admin-sedes-vacio">No se encontraron administradores con los filtros seleccionados.</td>
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
