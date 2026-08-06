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
    data-campo-estado="estatus"
    data-campo-fecha="fecha_envio_comprobante"
    data-vista='@json($datos_vista)'
    aria-labelledby="bandeja-pagos-titulo"
    v-cloak>
    <div class="admin-bandeja-preregistros-contenedor">
        <header class="admin-bandeja-preregistros-encabezado">
            <h1 id="bandeja-pagos-titulo">Bandeja de pagos</h1>
            <p>Consulta y revisa los comprobantes de pago enviados por las personas.</p>
        </header>

        @include('admin.partials.bandeja-filtros', [
            'prefijo_filtros' => 'bandeja-pagos',
            'estados_filtro' => ['Todos', 'Por revisar', 'Aprobado', 'Rechazado'],
        ])

        <section class="admin-bandeja-preregistros-tarjeta admin-bandeja-preregistros-solicitudes" aria-labelledby="pagos-recibidos-titulo">
            <h2 id="pagos-recibidos-titulo">Pagos recibidos</h2>

            <div class="admin-bandeja-preregistros-lista" aria-live="polite">
                <div class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-encabezados" aria-hidden="true">
                    <span>Persona</span>
                    <span>Estatus</span>
                    <span>Acción</span>
                </div>

                <article v-for="pago in personasFiltradas" :key="pago.id" class="admin-bandeja-preregistros-fila admin-bandeja-preregistros-solicitud">
                    <div class="admin-bandeja-preregistros-persona">
                        <span class="admin-bandeja-preregistros-avatar" aria-hidden="true">@{{ iniciales(pago) }}</span>
                        <div>
                            <h3>@{{ pago.nombre_completo }}</h3>
                            <p>Comprobante enviado: @{{ fechaRegistro(pago[campoFecha]) }}</p>
                        </div>
                    </div>
                    <div class="admin-bandeja-preregistros-estado-contenedor">
                        <span class="admin-bandeja-preregistros-estado" :class="claseEstado(pago)">@{{ pago.estatus }}</span>
                    </div>
                    <div class="admin-bandeja-preregistros-accion">
                        <a v-if="pago.puede_revisarse" class="admin-bandeja-preregistros-expediente" :href="pago.ruta_detalle">Revisar pago</a>
                        <span v-else class="admin-bandeja-preregistros-accion-no-disponible">Revisión no disponible</span>
                    </div>
                </article>

                <p v-if="!personasFiltradas.length" class="admin-bandeja-preregistros-vacio" role="status">
                    No se encontraron pagos con los filtros seleccionados.
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
