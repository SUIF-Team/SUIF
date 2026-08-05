@extends('layouts.admin')

@section('title', 'SUIF — Bandeja de pre-registros')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-bandeja-preregistros.css') }}">
@endsection

@section('content')
<section
    id="bandeja-preregistros-app"
    class="admin-bandeja-preregistros"
    data-bandeja-administrativa="preregistros"
    data-campo-estado="estado_bandeja"
    data-campo-fecha="fecha_registro"
    data-vista='@json($datos_vista)'
    aria-labelledby="bandeja-preregistros-titulo"
    v-cloak>
    <div class="admin-bandeja-preregistros-contenedor">
        <header class="admin-bandeja-preregistros-encabezado">
            <h1 id="bandeja-preregistros-titulo">Bandeja de pre-registros</h1>
            <p>Acepta o rechaza las solicitudes de nuevos participantes.</p>
        </header>

        @include('admin.partials.bandeja-filtros', [
            'prefijo_filtros' => 'bandeja-preregistros',
            'estados_filtro' => ['Todos', 'En revisión', 'Aprobada', 'Rechazada'],
        ])

        <section class="admin-bandeja-preregistros-tarjeta admin-bandeja-preregistros-solicitudes" aria-labelledby="solicitudes-titulo">
            <h2 id="solicitudes-titulo">Solicitudes</h2>

            <div class="admin-bandeja-preregistros-lista" aria-live="polite">
                <div class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-encabezados" aria-hidden="true">
                    <span>Participante</span>
                    <span>Estado</span>
                    <span>Acción</span>
                </div>

                <article v-for="participante in participantesFiltrados" :key="participante.id" class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-solicitud">
                    <div class="admin-bandeja-preregistros-participante">
                        <span class="admin-bandeja-preregistros-avatar" aria-hidden="true">@{{ iniciales(participante) }}</span>
                        <div>
                            <h3>@{{ participante.nombre_completo }}</h3>
                            <p>Registro: @{{ fechaRegistro(participante[campoFecha]) }}</p>
                        </div>
                    </div>
                    <div class="admin-bandeja-preregistros-estado-contenedor">
                        <span class="admin-bandeja-preregistros-estado" :class="claseEstado(participante)">@{{ participante.estado_bandeja }}</span>
                    </div>
                    <div class="admin-bandeja-preregistros-accion">
                        <a class="admin-bandeja-preregistros-expediente" :href="participante.ruta_expediente">Ver expediente</a>
                    </div>
                </article>

                <p v-if="!participantesFiltrados.length" class="admin-bandeja-preregistros-vacio" role="status">
                    No se encontraron solicitudes con los filtros seleccionados.
                </p>
            </div>
        </section>

        <back-navigation
            destino="{{ route('admin.dashboard') }}"
            etiqueta="Volver al dashboard"
            etiqueta-accesible="Volver al dashboard"></back-navigation>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset('assets/js/pages/admin-bandeja-preregistros.js') }}"></script>
@endsection
