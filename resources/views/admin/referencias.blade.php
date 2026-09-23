{{--
    admin/referencias.blade.php
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
            <a class="boton boton--primario" href="{{ route('admin.referencias.carga') }}">
                <span aria-hidden="true">+</span> Subir referencias
            </a>
        </header>

        <section class="admin-referencias-estadisticas" aria-label="Resumen del catálogo">
            <article class="tarjeta tarjeta--compacta admin-referencias-estadistica">
                <h2>Referencias cargadas</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['total']) }}</p>
            </article>
            <article class="tarjeta tarjeta--compacta admin-referencias-estadistica">
                <h2>Disponibles</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['disponibles']) }}</p>
            </article>
            <article class="tarjeta tarjeta--compacta admin-referencias-estadistica">
                <h2>Listas para entregar</h2>
                <p class="admin-referencias-estadistica--verde">{{ number_format($resumen['entregables']) }}</p>
            </article>
            <article class="tarjeta tarjeta--compacta admin-referencias-estadistica">
                <h2>Asignadas</h2>
                <p class="admin-referencias-estadistica--naranja">{{ number_format($resumen['asignadas']) }}</p>
            </article>
            <article class="tarjeta tarjeta--compacta admin-referencias-estadistica">
                <h2>Con formato PDF</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['con_formato']) }}</p>
            </article>
        </section>

        <section class="tarjeta tarjeta--compacta" aria-label="Filtros de búsqueda">
            <form method="GET" action="{{ route('admin.referencias.index') }}" class="filtros"
                  data-filtros-tabla="admin-referencias-tabla">
                <div class="campo">
                    <label class="etiqueta" for="buscar">Referencia o CURP</label>
                    <input class="control" id="buscar" name="buscar" type="search" value="{{ $filtros['buscar'] ?? '' }}">
                </div>
                <div class="campo">
                    <label class="etiqueta" for="estado">Estado</label>
                    <select class="control" id="estado" name="estado" data-filtro-modo="token">
                        <option value="">Todas</option>
                        <option value="disponible" @selected(($filtros['estado'] ?? '') === 'disponible')>Disponibles</option>
                        <option value="asignada" @selected(($filtros['estado'] ?? '') === 'asignada')>Asignadas</option>
                        <option value="sin-formato" @selected(($filtros['estado'] ?? '') === 'sin-formato')>Sin formato PDF</option>
                    </select>
                </div>
                <div class="admin-referencias-filtros-acciones">
                    <button class="boton boton--primario" type="submit">Filtrar</button>
                    <a class="boton boton--peligro" href="{{ route('admin.referencias.index') }}" data-filtros-limpiar>Limpiar</a>
                </div>
            </form>
        </section>

        <section class="tabla-contenedor" aria-label="Catálogo de referencias">
            <div class="tabla-responsive tabla-desplazable">
                <table id="admin-referencias-tabla" class="tabla admin-referencias-tabla admin-referencias-tabla--centrada">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Monto</th>
                            <th>Vigencia</th>
                            <th>Formato</th>
                            <th>Estado</th>
                            <th>Asignada a</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- ponytail: el catálogo se pinta entero y el filtro lo acota en
                             el navegador. La carga sin filtro ya era así —catalogo() no
                             tiene límite—, pero la carga filtrada ahora también trae todo.
                             Si el catálogo crece hasta doler, lo que falta es paginación,
                             que hoy no existe en ninguna pantalla del sistema. --}}
                        @foreach($referencias as $referencia)
                            <tr data-filtro-buscar="{{ $referencia['referencia'].' '.$referencia['curp'] }}"
                                data-filtro-estado="{{ $referencia['asignada'] ? 'asignada' : 'disponible' }}{{ $referencia['tiene_formato'] ? '' : ' sin-formato' }}">
                                <td class="admin-referencias-tabla-numero">
                                    <div class="admin-referencias-celda">{{ $referencia['referencia'] }}</div>
                                </td>
                                <td>
                                    <div class="admin-referencias-celda">
                                        @if($referencia['monto'] !== null)
                                            ${{ number_format($referencia['monto'], 2) }} {{ config('suif.moneda', 'MXN') }}
                                        @else
                                            <span class="atenuado">Cuota vigente</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-referencias-celda">
                                        @if($referencia['vigencia'])
                                            {{ \Illuminate\Support\Carbon::parse($referencia['vigencia'])->format('d/m/Y') }}
                                        @else
                                            <span class="atenuado">Sin vigencia</span>
                                        @endif

                                        @if($referencia['fecha_emision'])
                                            <div class="atenuado">
                                                Emitida el {{ \Illuminate\Support\Carbon::parse($referencia['fecha_emision'])->format('d/m/Y') }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-referencias-celda">
                                        @if($referencia['tiene_formato'])
                                            <a class="boton boton--texto" target="_blank" rel="noopener"
                                               href="{{ route('admin.referencias.formato', ['id' => $referencia['id']]) }}">Ver PDF</a>
                                        @else
                                            <span class="atenuado">Sin PDF</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-referencias-celda">
                                        <span class="estado estado--{{ $referencia['asignada'] ? 'asignada' : 'disponible' }}">
                                            {{ $referencia['asignada'] ? 'Asignada' : 'Disponible' }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="admin-referencias-celda">
                                        @if($referencia['asignada'])
                                            {{ $referencia['titular'] ?: 'Sin nombre registrado' }}
                                            <small>{{ $referencia['curp'] }}</small>
                                            @if($referencia['fecha_asignacion'])
                                                <small>{{ \Illuminate\Support\Carbon::parse($referencia['fecha_asignacion'])->format('d/m/Y') }}</small>
                                            @endif
                                        @else
                                            <span class="atenuado">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        {{-- El renglón se escribe siempre: con la tabla filtrándose
                             en el navegador, el aviso aparece sin volver al servidor. --}}
                        <tr data-tabla-vacia @unless($referencias->isEmpty()) hidden @endunless>
                            <td colspan="6" class="vacio" role="status">
                                No hay referencias que coincidan con los filtros seleccionados.
                            </td>
                        </tr>
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
<script src="{{ asset_versionado('assets/js/pages/admin-filtros-tabla.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-referencias.js') }}"></script>
@endsection
