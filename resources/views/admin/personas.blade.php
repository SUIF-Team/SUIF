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
            <p>Acepta o rechaza las solicitudes de nuevas personas.</p>
        </header>

        @include('admin.partials.bandeja-filtros', [
            'prefijo_filtros' => 'bandeja-preregistros',
            'estados_filtro' => array_merge(['Todos'], $datos_vista['estados']),
        ])

        <section class="admin-bandeja-preregistros-tarjeta admin-bandeja-preregistros-solicitudes" aria-labelledby="solicitudes-titulo">
            <h2 id="solicitudes-titulo">Solicitudes</h2>

            <div class="admin-bandeja-preregistros-lista" aria-live="polite">
                <div class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-encabezados" aria-hidden="true">
                    <span>Persona</span>
                    <span>Estado</span>
                    <span>Acción</span>
                </div>

                <article v-for="persona in personasFiltradas" :key="persona.id" class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-solicitud">
                    <div class="admin-bandeja-preregistros-persona">
                        <span class="admin-bandeja-preregistros-avatar" aria-hidden="true">@{{ iniciales(persona) }}</span>
                        <div>
                            <h3>@{{ persona.nombre_completo }}</h3>
                            <p>Registro: @{{ fechaRegistro(persona[campoFecha]) }}</p>
                        </div>
                    </div>
                    <div class="admin-bandeja-preregistros-estado-contenedor">
                        <span class="admin-bandeja-preregistros-estado" :class="claseEstado(persona)">@{{ persona.estado_bandeja }}</span>
                    </div>
                    <div class="admin-bandeja-preregistros-accion">
                        <a class="admin-bandeja-preregistros-expediente" :href="persona.ruta_expediente">Ver expediente</a>
                    </div>
                </article>

                <p v-if="!personasFiltradas.length" class="admin-bandeja-preregistros-vacio" role="status">
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
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset('assets/js/pages/admin-bandeja-preregistros.js') }}"></script>
@endsection
