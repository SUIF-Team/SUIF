{{--
    Bandeja de cuentas administrativas. Sólo la abre quien tiene el privilegio
    "Gestionar usuarios", es decir el Superusuario.

    Reutiliza el diseño de la bandeja de sedes: mismas tarjetas, mismos filtros
    y misma tabla. Lo propio de esta pantalla vive en admin-administradores.css.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Gestión de usuarios')

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
                <h1 id="admin-administradores-titulo">Gestión de usuarios</h1>
                <p>Da de alta a quienes operan el sistema y define de qué área es cada quien.</p>
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
            <form method="GET" action="{{ route('admin.administradores.index') }}" class="admin-sedes-filtros-formulario"
                  data-filtros-tabla="admin-administradores-tabla">
                <div class="admin-sedes-campo">
                    <label for="buscar">Buscar por nombre o CURP</label>
                    <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}">
                </div>
                <div class="admin-sedes-campo">
                    <label for="rol">Tipo</label>
                    <select id="rol" name="rol">
                        <option value="">Todos</option>
                        @foreach($roles as $rol)
                            <option value="{{ $rol['nombre'] }}" @selected(($filtros['rol'] ?? '') === $rol['nombre'])>
                                {{ $rol['etiqueta'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-sedes-campo">
                    <label for="estatus">Estado</label>
                    <select id="estatus" name="estatus">
                        <option value="">Todos</option>
                        <option value="activos" @selected(($filtros['estatus'] ?? '') === 'activos')>Con acceso</option>
                        <option value="inactivos" @selected(($filtros['estatus'] ?? '') === 'inactivos')>Sin acceso</option>
                    </select>
                </div>
                <div class="admin-sedes-campo">
                    <label for="orden">Ordenar por</label>
                    <select id="orden" name="orden" data-filtro-orden>
                        <option value="">Nombre (A-Z)</option>
                        <option value="za" @selected(($filtros['orden'] ?? '') === 'za')>Nombre (Z-A)</option>
                    </select>
                </div>
                <div class="admin-sedes-filtros-acciones">
                    <button class="admin-sedes-boton admin-sedes-boton--filtrar" type="submit">Filtrar</button>
                    <a class="admin-sedes-boton admin-sedes-boton--limpiar" href="{{ route('admin.administradores.index') }}" data-filtros-limpiar>Limpiar</a>
                </div>
            </form>
        </section>

        <section class="admin-sedes-tarjeta admin-sedes-tabla-contenedor" aria-label="Lista de administradores">
            <div class="admin-sedes-tabla-responsive admin-tabla-bandeja">
                <table id="admin-administradores-tabla" class="admin-sedes-tabla admin-sedes-tabla--centrada">
                    <thead>
                        <tr>
                            <th>Administrador</th>
                            <th>CURP</th>
                            <th>Tipo</th>
                            <th>Alta</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($administradores as $administrador)
                            <tr data-filtro-buscar="{{ $administrador['nombre'].' '.$administrador['curp'] }}"
                                data-filtro-rol="{{ $administrador['rol'] }}"
                                data-filtro-estatus="{{ $administrador['activo'] ? 'activos' : 'inactivos' }}">
                                <td class="admin-sedes-tabla-nombre">{{ $administrador['nombre'] }}</td>
                                <td><code class="admin-administradores-curp">{{ $administrador['curp'] }}</code></td>
                                <td>
                                    <span class="admin-administradores-tipo admin-administradores-tipo--{{ \Illuminate\Support\Str::slug($administrador['rol']) }}">
                                        {{ $administrador['rol_etiqueta'] }}
                                    </span>
                                </td>
                                <td>
                                    {{ $administrador['fecha_registro']
                                        ? \Illuminate\Support\Carbon::parse($administrador['fecha_registro'])->format('d/m/Y')
                                        : '—' }}
                                </td>
                                <td>
                                    <span class="admin-sedes-estado admin-sedes-estado--{{ $administrador['activo'] ? 'con-cupo' : 'sin-cupo' }}">
                                        {{ $administrador['activo'] ? 'Con acceso' : 'Sin acceso' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="admin-administradores-acciones">
                                        <a class="admin-sedes-editar" href="{{ route('admin.administradores.edit', $administrador['id_usuario']) }}">Editar</a>
                                        @unless($administrador['activo'])
                                            {{-- Devolver el acceso es una sola acción: no necesita
                                                 confirmación porque no destruye nada. --}}
                                            <form method="POST" action="{{ route('admin.administradores.reactivar', $administrador['id_usuario']) }}">
                                                @csrf
                                                <button class="admin-administradores-reactivar" type="submit">Reactivar</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        {{-- El renglón se escribe siempre: con la tabla filtrándose
                             en el navegador, el aviso aparece sin volver al servidor. --}}
                        <tr data-tabla-vacia @unless($administradores->isEmpty()) hidden @endunless>
                            <td colspan="6" class="admin-sedes-vacio" role="status">No se encontraron administradores con los filtros seleccionados.</td>
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
<script src="{{ asset_versionado('assets/js/pages/admin-administradores.js') }}"></script>
@endsection
