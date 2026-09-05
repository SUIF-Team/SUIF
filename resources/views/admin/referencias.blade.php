{{--
    admin/referencias.blade.php
    Migrado desde: app/views/admin/referencias.php
    Catálogo de referencias bancarias y la persona a la que se entregó cada una.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Referencias de Pago')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-referencias.css') }}">
@endsection

@section('content')
<section class="admin-referencias" aria-labelledby="admin-referencias-titulo">
    <div class="admin-referencias-contenedor">
        <header class="admin-referencias-encabezado">
            <div>
                <h1 id="admin-referencias-titulo">Referencias bancarias</h1>
                <p>Cada referencia se entrega a una sola persona y queda ligada a su trámite.</p>
            </div>
            <a class="admin-referencias-boton admin-referencias-boton--primario" href="{{ route('admin.referencias.carga') }}">
                <span aria-hidden="true">+</span> Subir referencias
            </a>
        </header>

        <section class="admin-referencias-estadisticas" aria-label="Resumen del catálogo">
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Referencias cargadas</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['total']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Disponibles</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['disponibles']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Listas para entregar</h2>
                <p class="admin-referencias-estadistica--verde">{{ number_format($resumen['entregables']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Asignadas</h2>
                <p class="admin-referencias-estadistica--naranja">{{ number_format($resumen['asignadas']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Con formato PDF</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['con_formato']) }}</p>
            </article>
        </section>

        <section class="admin-referencias-tarjeta admin-referencias-filtros" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.referencias.index') }}" class="admin-referencias-filtros-formulario">
                <div class="admin-referencias-campo">
                    <label for="buscar">Referencia o CURP</label>
                    <input id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}">
                </div>
                <div class="admin-referencias-campo">
                    <label for="estado">Estatus</label>
                    <select id="estado" name="estado">
                        <option value="">Todas</option>
                        <option value="disponible" @selected(($filtros['estado'] ?? '') === 'disponible')>Disponibles</option>
                        <option value="asignada" @selected(($filtros['estado'] ?? '') === 'asignada')>Asignadas</option>
                        <option value="sin-formato" @selected(($filtros['estado'] ?? '') === 'sin-formato')>Sin formato PDF</option>
                    </select>
                </div>
                <div class="admin-referencias-filtros-acciones">
                    <button class="admin-referencias-boton admin-referencias-boton--filtrar" type="submit">Filtrar</button>
                    <a class="admin-referencias-boton admin-referencias-boton--limpiar" href="{{ route('admin.referencias.index') }}">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="admin-referencias-tarjeta admin-referencias-tabla-contenedor" aria-label="Catálogo de referencias">
            <div class="admin-referencias-tabla-responsive">
                <table class="admin-referencias-tabla">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Monto</th>
                            <th>Vigencia</th>
                            <th>Formato</th>
                            <th>Estatus</th>
                            <th>Asignada a</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($referencias as $referencia)
                            <tr>
                                <td class="admin-referencias-tabla-numero">{{ $referencia['referencia'] }}</td>
                                <td>
                                    @if($referencia['monto'] !== null)
                                        ${{ number_format($referencia['monto'], 2) }} {{ config('suif.moneda', 'MXN') }}
                                    @else
                                        <span class="admin-referencias-texto-atenuado">Cuota vigente</span>
                                    @endif
                                </td>
                                <td>
                                    @if($referencia['vigencia'])
                                        {{ \Illuminate\Support\Carbon::parse($referencia['vigencia'])->format('d/m/Y') }}
                                    @else
                                        <span class="admin-referencias-texto-atenuado">Sin vigencia</span>
                                    @endif

                                    @if($referencia['fecha_emision'])
                                        <div class="admin-referencias-texto-atenuado">
                                            Emitida el {{ \Illuminate\Support\Carbon::parse($referencia['fecha_emision'])->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($referencia['tiene_formato'])
                                        <a class="admin-referencias-enlace" target="_blank" rel="noopener"
                                           href="{{ route('admin.referencias.formato', ['id' => $referencia['id']]) }}">Ver PDF</a>
                                    @else
                                        <span class="admin-referencias-texto-atenuado">Sin PDF</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="admin-referencias-estado admin-referencias-estado--{{ $referencia['asignada'] ? 'asignada' : 'disponible' }}">
                                        {{ $referencia['asignada'] ? 'Asignada' : 'Disponible' }}
                                    </span>
                                </td>
                                <td>
                                    @if($referencia['asignada'])
                                        {{ $referencia['titular'] ?: 'Sin nombre registrado' }}
                                        <small>
                                            {{ $referencia['curp'] }}
                                            @if($referencia['fecha_asignacion'])
                                                · {{ \Illuminate\Support\Carbon::parse($referencia['fecha_asignacion'])->format('d/m/Y') }}
                                            @endif
                                        </small>
                                    @else
                                        <span class="admin-referencias-texto-atenuado">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="admin-referencias-vacio">
                                    No hay referencias que coincidan con los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="admin-referencias-navegacion">
            <back-navigation
                destino="{{ route('admin.dashboard') }}"
                etiqueta="Volver al dashboard"
                etiqueta-accesible="Volver al dashboard"></back-navigation>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-referencias.js') }}"></script>
@endsection
