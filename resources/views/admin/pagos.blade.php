@extends('layouts.admin')

@section('title', 'SUIF — Bandeja de pagos')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-bandeja-preregistros.css') }}">
@endsection

@section('content')
<section
    id="bandeja-pagos-app"
    class="admin-bandeja-preregistros"
    data-bandeja-administrativa="pagos"
    data-vista='@json($datos_vista)'
    aria-labelledby="bandeja-pagos-titulo"
    v-cloak>
    <div class="admin-bandeja-preregistros-contenedor">
        <header class="admin-bandeja-preregistros-encabezado">
            <h1 id="bandeja-pagos-titulo">Bandeja de pagos</h1>
            <p>Consulta y revisa los comprobantes de pago enviados por los participantes.</p>
        </header>

        <section class="admin-bandeja-preregistros-tarjeta" aria-label="Filtros de búsqueda">
            <form class="admin-bandeja-preregistros-filtros" v-on:submit.prevent="filtrar">
                <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-termino">
                    <label for="bandeja-pagos-buscar">Buscar participante</label>
                    <input id="bandeja-pagos-buscar" v-model="filtros.termino" type="search" placeholder="Escribe aquí tu búsqueda..." autocomplete="off">
                </div>

                <div class="admin-bandeja-preregistros-acciones-filtro">
                    <button class="admin-bandeja-preregistros-boton admin-bandeja-preregistros-boton-filtrar" type="submit">Filtrar</button>
                    <button class="admin-bandeja-preregistros-boton admin-bandeja-preregistros-boton-limpiar" type="button" v-on:click="limpiar">Limpiar</button>
                </div>
            </form>
        </section>

        <section class="admin-bandeja-preregistros-tarjeta admin-bandeja-preregistros-solicitudes" aria-labelledby="pagos-recibidos-titulo">
            <h2 id="pagos-recibidos-titulo">Pagos recibidos</h2>

            <div class="admin-bandeja-preregistros-lista" aria-live="polite">
                <div class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-encabezados" aria-hidden="true">
                    <span>Participante</span>
                    <span>Estatus</span>
                    <span>Acción</span>
                </div>

                <article v-for="pago in participantesFiltrados" :key="pago.id" class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-solicitud">
                    <div class="admin-bandeja-preregistros-participante">
                        <span class="admin-bandeja-preregistros-avatar" aria-hidden="true">@{{ iniciales(pago) }}</span>
                        <div>
                            <h3>@{{ pago.nombre_completo }}</h3>
                            <p>Comprobante enviado: @{{ fechaRegistro(pago.fecha_envio_comprobante) }}</p>
                        </div>
                    </div>
                    <div class="admin-bandeja-preregistros-estado-contenedor">
                        <span class="admin-bandeja-preregistros-estado" :class="claseEstado(pago.estatus)">@{{ pago.estatus }}</span>
                    </div>
                    <div class="admin-bandeja-preregistros-accion">
                        <a class="admin-bandeja-preregistros-expediente" :href="pago.ruta_detalle">Revisar pago</a>
                    </div>
                </article>

                <p v-if="!participantesFiltrados.length" class="admin-bandeja-preregistros-vacio" role="status">
                    No se encontraron pagos con los filtros seleccionados.
                </p>
            </div>
        </section>

        <back-navigation
            destino="{{ route('admin.dashboard') }}"
            etiqueta="Volver al dashboard"
            etiqueta-accesible="Volver al dashboard administrativo"></back-navigation>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset('assets/js/pages/admin-bandeja-preregistros.js') }}"></script>
@endsection
