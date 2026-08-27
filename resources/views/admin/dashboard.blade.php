{{--
    Panel principal del administrador.

    Ni los indicadores ni las acciones son fijos: el controlador ya entregó
    sólo lo que quien mira tiene permiso de abrir, así que aquí se recorren tal
    cual. Un Admin UIF y un Admin DEC ven tableros distintos.
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

        @if (!empty($indicadores))
            <section class="admin-dashboard-indicadores" aria-label="Resumen administrativo">
                @foreach ($indicadores as $indicador)
                    <article class="admin-dashboard-indicador {{ $indicador['clase'] }}">
                        <h2>{{ $indicador['titulo'] }}</h2>
                        <p @class(['admin-dashboard-indicador-sin-datos' => $indicador['sin_datos']])>
                            {{ $indicador['valor'] }}
                        </p>
                    </article>
                @endforeach
            </section>
        @endif

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
