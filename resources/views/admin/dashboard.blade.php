{{--
    Panel principal del administrador.
--}}
@extends('layouts.admin')

@section('title', 'SUIF - Panel Administrador')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-dashboard.css') }}">
@endsection

@section('content')
<section class="admin-dashboard" aria-labelledby="admin-dashboard-titulo">
    <div class="admin-dashboard-contenedor">
        <h1 id="admin-dashboard-titulo" class="admin-dashboard-titulo">Panel administrativo</h1>

        <section class="admin-dashboard-indicadores" aria-label="Resumen administrativo">
            <article class="admin-dashboard-indicador admin-dashboard-indicador-azul">
                <h2>Participantes registrados</h2>
                <p>{{ number_format($resumen['participantes_registrados']) }}</p>
            </article>

            <article class="admin-dashboard-indicador admin-dashboard-indicador-naranja">
                <h2>Pre-registros pendientes</h2>
                <p>{{ number_format($resumen['preregistros_pendientes']) }}</p>
            </article>

            <article class="admin-dashboard-indicador admin-dashboard-indicador-naranja">
                <h2>Pagos por validar</h2>
                <p>{{ number_format($resumen['pagos_pendientes']) }}</p>
            </article>

            <article class="admin-dashboard-indicador admin-dashboard-indicador-verde">
                <h2>Certificados pendientes</h2>
                <p>{{ number_format($resumen['certificados_pendientes']) }}</p>
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
                        <span class="admin-dashboard-accion-pendiente" aria-disabled="true">Próximamente</span>
                    </article>
                @endforeach
            </div>
        </section>

        <form method="POST" action="{{ route('logout') }}" class="admin-dashboard-salida">
            @csrf
            <button type="submit" class="sistema-salir">
                <span class="sistema-salir-icono" aria-hidden="true">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </span>
                <span>Salir</span>
            </button>
        </form>
    </div>
</section>
@endsection
