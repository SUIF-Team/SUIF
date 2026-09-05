{{--
    admin/referencias-especiales.blade.php
    Bandeja de las referencias especiales que las empresas pidieron desde el
    trámite y que todavía no se emiten. Reutiliza la hoja del catálogo: es la
    misma pantalla de la DEC, sólo que aquí se entrega un número por grupo.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Referencias especiales')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-referencias.css') }}">
@endsection

@section('content')
<section class="admin-referencias" aria-labelledby="admin-referencias-especiales-titulo">
    <div class="admin-referencias-contenedor">
        <header class="admin-referencias-encabezado">
            <div>
                <h1 id="admin-referencias-especiales-titulo">Referencias especiales</h1>
                <p>
                    Un tercero paga la certificación de varios participantes con una sola referencia.
                    Emítela por el importe total y el sistema avisará a todo el grupo.
                </p>
            </div>
            <a class="admin-referencias-boton admin-referencias-boton--primario" href="{{ route('admin.referencias.carga') }}">
                <span aria-hidden="true">+</span> Subir referencias
            </a>
        </header>

        <section class="admin-referencias-tarjeta admin-referencias-tabla-contenedor" aria-label="Solicitudes pendientes">
            <div class="admin-referencias-tabla-responsive">
                <table class="admin-referencias-tabla">
                    <thead>
                        <tr>
                            <th>Solicitud</th>
                            <th>Quien paga</th>
                            <th>RFC</th>
                            <th>Participantes</th>
                            <th>Importe total</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($solicitudes as $solicitud)
                            <tr>
                                <td class="admin-referencias-tabla-numero">#{{ $solicitud['id_pago'] }}</td>
                                <td>{{ $solicitud['razon_social'] ?: 'Sin razón social registrada' }}</td>
                                <td>{{ $solicitud['rfc'] ?: '—' }}</td>
                                <td>{{ number_format($solicitud['participantes']) }}</td>
                                <td>${{ number_format($solicitud['monto'], 2) }} {{ config('suif.moneda', 'MXN') }}</td>
                                <td>
                                    <a class="admin-referencias-enlace"
                                       href="{{ route('admin.referencias.especiales.show', ['id' => $solicitud['id_pago']]) }}">
                                        Emitir referencia
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="admin-referencias-vacio">
                                    No hay referencias especiales pendientes de emisión.
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
