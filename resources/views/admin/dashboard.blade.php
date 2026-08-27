{{--
    Panel principal del administrador.
--}}
@extends('layouts.admin')

@section('title', 'SUIF - Panel Administrador')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-dashboard.css') }}">
@endsection

@section('content')
<section class="admin-dashboard" aria-labelledby="admin-dashboard-titulo">
    <div class="admin-dashboard-contenedor">
        <h1 id="admin-dashboard-titulo" class="admin-dashboard-titulo">Panel administrativo</h1>

        <section class="admin-dashboard-indicadores" aria-label="Resumen administrativo">
            <article class="admin-dashboard-indicador admin-dashboard-indicador-azul">
                <h2>Personas registradas</h2>
                <p>{{ number_format($resumen['personas_registradas']) }}</p>
            </article>

            <article class="admin-dashboard-indicador admin-dashboard-indicador-naranja">
                <h2>Solicitudes en revisión</h2>
                <p>{{ number_format($resumen['solicitudes_en_revision']) }}</p>
            </article>

            <article class="admin-dashboard-indicador admin-dashboard-indicador-naranja">
                <h2>Pagos por validar</h2>
                <p>{{ number_format($resumen['pagos_pendientes']) }}</p>
            </article>

            <article class="admin-dashboard-indicador admin-dashboard-indicador-verde">
                <h2>Certificados pendientes</h2>
                <p class="admin-dashboard-indicador-sin-datos">
                    {{ is_null($resumen['certificados_pendientes']) ? 'Sin datos persistidos' : number_format($resumen['certificados_pendientes']) }}
                </p>
            </article>
        </section>

        <section class="admin-dashboard-acciones" aria-labelledby="admin-dashboard-acciones-titulo">
            <h2 id="admin-dashboard-acciones-titulo">Acciones</h2>

            <div class="admin-dashboard-acciones-grid">
                @foreach ($acciones as $accion)
                    <article class="admin-dashboard-accion">
                        <div>
                            <h3>{{ $accion['titulo'] }}</h3>
                            <p>{{ $accion['descripcion'] }}</p>
                        </div>
                        @if (!empty($accion['ruta']))
                            <a class="admin-dashboard-accion-disponible" href="{{ route($accion['ruta']) }}">Abrir</a>
                        @else
                            <span class="admin-dashboard-accion-pendiente" aria-disabled="true">Próximamente</span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</section>
@endsection
