@extends('layouts.admin')

@section('title', 'SUIF — Personas registradas')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-bandeja-preregistros.css') }}">
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
            <p>Consulta y gestiona a las personas registradas en el sistema y el estado actual de su solicitud.</p>
        </header>

        @include('admin.partials.bandeja-filtros', [
            'prefijo_filtros' => 'bandeja-personas-registradas',
            'estados_filtro' => array_merge(['Todos'], $datos_vista['estados']),
        ])

        <section class="admin-bandeja-preregistros-tarjeta admin-bandeja-preregistros-solicitudes" aria-labelledby="personas-registradas-listado-titulo">
            <h2 id="personas-registradas-listado-titulo">Personas</h2>

            <div class="admin-bandeja-preregistros-lista" aria-live="polite">
                {{-- El @can duplica el middleware de la ruta a propósito:
                     quien no puede gestionar usuarios ve la bandeja sin la
                     columna de acción, exactamente como antes. --}}
                <div class="admin-bandeja-preregistros-fila @cannot('gestionar-usuarios') admin-bandeja-preregistros-fila--sin-accion @endcannot admin-bandeja-preregistros-encabezados" aria-hidden="true">
                    <span>Persona</span>
                    <span>Estado</span>
                    @can('gestionar-usuarios')
                        <span>Acción</span>
                    @endcan
                </div>

                <article v-for="persona in personasFiltradas" :key="persona.id" class="admin-bandeja-preregistros-fila @cannot('gestionar-usuarios') admin-bandeja-preregistros-fila--sin-accion @endcannot admin-bandeja-preregistros-solicitud">
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
                    @can('gestionar-usuarios')
                        <div class="admin-bandeja-preregistros-accion">
                            <button
                                type="button"
                                class="admin-bandeja-preregistros-expediente"
                                v-on:click="abrirRestaurar(persona, $event)">Restaurar clave</button>
                        </div>
                    @endcan
                </article>

                <p v-if="!personasFiltradas.length" class="admin-bandeja-preregistros-vacio" role="status">
                    No se encontraron personas con los filtros seleccionados.
                </p>
            </div>
        </section>

        {{-- Modal único de confirmación controlado por Vue: las filas se
             re-renderizan al filtrar, así que el patrón de reversión en JS
             llano perdería sus escuchadores. Los estilos vienen de
             admin-preregistro.css, ya enlazado en esta vista. --}}
        @can('gestionar-usuarios')
            <div class="admin-reversion-modal" v-if="persona_seleccionada" v-on:keydown.esc="cerrarRestaurar">
                <div class="admin-reversion-modal-fondo" v-on:click="cerrarRestaurar"></div>
                <section
                    class="admin-reversion-modal-card"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="restaurar-clave-titulo"
                    aria-describedby="restaurar-clave-descripcion">
                    <h2 id="restaurar-clave-titulo">¿Restaurar la clave de acceso?</h2>
                    <p id="restaurar-clave-descripcion">Se generará una clave nueva para @{{ persona_seleccionada.nombre_completo }} y se enviará a su correo principal. La clave anterior dejará de funcionar.</p>
                    <form method="POST" :action="persona_seleccionada.ruta_restaurar_clave" class="admin-reversion-modal-acciones">
                        @csrf
                        <button class="admin-preregistro-boton admin-preregistro-boton--neutral" type="button" ref="cancelar_restaurar" v-on:click="cerrarRestaurar">
                            Cancelar
                        </button>
                        <button class="admin-preregistro-boton admin-preregistro-boton--aceptar" type="submit">
                            Sí, restaurar
                        </button>
                    </form>
                </section>
            </div>
        @endcan

        <back-navigation
            destino="{{ route('admin.dashboard') }}"
            etiqueta="Volver al dashboard"
            etiqueta-accesible="Volver al dashboard"></back-navigation>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset_versionado('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-bandeja-preregistros.js') }}"></script>
@endsection
