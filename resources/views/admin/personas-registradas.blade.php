@extends('layouts.admin')

@section('title', 'SUIF — Personas registradas')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-bandeja-preregistros.css') }}">
@endsection

@section('content')
<section
    id="bandeja-personas-registradas-app"
    class="admin-bandeja-preregistros"
    data-bandeja-administrativa="personas-registradas"
    data-campo-estado="estado"
    data-campo-fecha="fecha_registro"
    data-vista='@json($datos_vista)'
    aria-labelledby="bandeja-personas-registradas-titulo"
    v-cloak>
    <div class="admin-bandeja-preregistros-contenedor">
        <header class="admin-bandeja-preregistros-encabezado">
            <h1 id="bandeja-personas-registradas-titulo">Personas registradas</h1>
            <p>Consulta todas las personas registradas en el sistema y el estado actual de su solicitud.</p>
        </header>

        @include('admin.partials.bandeja-filtros', [
            'prefijo_filtros' => 'bandeja-personas-registradas',
            'estados_filtro' => array_merge(['Todos'], $datos_vista['estados']),
        ])

        <section class="admin-bandeja-preregistros-tarjeta admin-bandeja-preregistros-solicitudes" aria-labelledby="personas-registradas-listado-titulo">
            <h2 id="personas-registradas-listado-titulo">Personas</h2>

            <div class="admin-bandeja-preregistros-lista" aria-live="polite">
                <div class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-fila--sin-accion admin-bandeja-preregistros-encabezados" aria-hidden="true">
                    <span>Persona</span>
                    <span>Estado</span>
                </div>

                <article v-for="persona in personasFiltradas" :key="persona.id" class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-fila--sin-accion admin-bandeja-preregistros-solicitud">
                    <div class="admin-bandeja-preregistros-persona">
                        <span class="admin-bandeja-preregistros-avatar" aria-hidden="true">@{{ iniciales(persona) }}</span>
                        <div>
                            <h3>@{{ persona.nombre_completo }}</h3>
                            <p>Registro: @{{ fechaRegistro(persona[campoFecha]) }}</p>
                        </div>
                    </div>
                    <div class="admin-bandeja-preregistros-estado-contenedor">
                        <span class="admin-bandeja-preregistros-estado" :class="claseEstado(persona)">@{{ persona.estado }}</span>
                    </div>
                </article>

                <p v-if="!personasFiltradas.length" class="admin-bandeja-preregistros-vacio" role="status">
                    No se encontraron personas con los filtros seleccionados.
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
