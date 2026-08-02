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
    data-bandeja-preregistros
    data-vista='@json($datos_vista)'
    aria-labelledby="bandeja-preregistros-titulo"
    v-cloak>
    <div class="admin-bandeja-preregistros-contenedor">
        <header class="admin-bandeja-preregistros-encabezado">
            <h1 id="bandeja-preregistros-titulo">Bandeja de pre-registros</h1>
            <p>Acepta o rechaza las solicitudes de nuevos participantes.</p>
        </header>

        <section class="admin-bandeja-preregistros-tarjeta" aria-label="Filtros de búsqueda">
            <form class="admin-bandeja-preregistros-filtros" v-on:submit.prevent="filtrar">
                <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-tipo">
                    <label for="bandeja-filtro-campo">Filtrar por</label>
                    <select id="bandeja-filtro-campo" v-model="filtros.campo">
                        <option value="nombre">Nombre(s)</option>
                        <option value="primer_apellido">Apellido paterno</option>
                        <option value="segundo_apellido">Apellido materno</option>
                    </select>
                </div>

                <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-termino">
                    <label for="bandeja-filtro-termino">Término de búsqueda</label>
                    <input id="bandeja-filtro-termino" v-model="filtros.termino" type="search" placeholder="Escribe aquí tu búsqueda..." autocomplete="off">
                </div>

                <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-estado">
                    <label for="bandeja-filtro-estado">Estado</label>
                    <select id="bandeja-filtro-estado" v-model="filtros.estado">
                        <option value="Todos">Todos</option>
                        <option value="En revisión">En revisión</option>
                        <option value="Aceptado">Aceptado</option>
                        <option value="Rechazado">Rechazado</option>
                    </select>
                </div>

                <div class="admin-bandeja-preregistros-acciones-filtro">
                    <button class="admin-bandeja-preregistros-boton admin-bandeja-preregistros-boton-filtrar" type="submit">Filtrar</button>
                    <button class="admin-bandeja-preregistros-boton admin-bandeja-preregistros-boton-limpiar" type="button" v-on:click="limpiar">Limpiar</button>
                </div>
            </form>
        </section>

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
                            <p>Registro: @{{ fechaRegistro(participante.fecha_registro) }}</p>
                        </div>
                    </div>
                    <div class="admin-bandeja-preregistros-estado-contenedor">
                        <span class="admin-bandeja-preregistros-estado" :class="claseEstado(participante.estado_bandeja)">@{{ participante.estado_bandeja }}</span>
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

        <back-navigation destino="{{ route('admin.dashboard') }}"></back-navigation>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset('assets/js/pages/admin-bandeja-preregistros.js') }}"></script>
@endsection
