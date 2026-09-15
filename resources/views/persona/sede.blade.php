{{--
    persona/sede.blade.php
    Dos pantallas en una: el catálogo con el que la persona elige sede y
    horario, y el resumen de lo que ya confirmó.

    El catálogo es una app Vue porque la lista se sondea cada 15 s y sus
    renglones aparecen y desaparecen: una aplicación que vence o que el
    administrador da de baja deja de existir, no sólo se deshabilita.

    El resumen se queda en Blade: es texto fijo, un mapa y dos enlaces.
--}}
@extends('layouts.persona')

@section('title', 'SUIF — Selección de sede')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-sede.css') }}">
@endpush

@section('content')
@if($confirmada)
<section class="sede-shell">
    @if($errors->any())
        <div class="notificacion notificacion--error" role="alert">
            <i class="fa-solid fa-circle-exclamation notificacion__icono" aria-hidden="true"></i>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="sede-confirmada-layout">
        <div class="sede-confirmada">
            <span class="sede-confirmada__icono" aria-hidden="true">
                <i class="fa-solid fa-check"></i>
            </span>
            <h1>¡Sede confirmada!</h1>
            <p class="sede-muted">Tu lugar quedó apartado para la evaluación.</p>

            <div class="tarjeta sede-resumen">
                <p class="sede-resumen__etiqueta">Sede seleccionada</p>
                <h3 class="sede-resumen__nombre">{{ $sede['nombre'] }}</h3>
                <dl class="sede-resumen__datos">
                    <dt>Dirección</dt><dd>{{ $sede['direccion'] }}</dd>
                    <dt>Fecha</dt><dd>{{ $sede['fecha'] }}</dd>
                    <dt>Horario</dt><dd>{{ $sede['horario'] }}</dd>
                </dl>
            </div>

            <div class="sede-acciones">
                <a href="{{ route('persona.sede.comprobante') }}" class="boton boton--secundario">
                    Generar comprobante
                </a>
                <a href="{{ route('persona.dashboard') }}" class="boton boton--primario">Continuar</a>
            </div>
        </div>

        <aside class="tarjeta sede-mapa" aria-labelledby="sede-mapa-titulo">
            <h2 id="sede-mapa-titulo">Cómo llegar</h2>
            <p class="ayuda sede-mapa__ayuda">Ubicación aproximada a partir de la dirección de la sede.</p>
            <div class="sede-mapa__marco">
                <iframe
                    src="https://maps.google.com/maps?q={{ urlencode($sede['direccion']) }}&amp;hl=es&amp;z=16&amp;output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Mapa con la ubicación de {{ $sede['nombre'] }}"></iframe>
            </div>
            <a
                class="boton boton--secundario sede-mapa__enlace"
                href="{{ $mapa }}"
                target="_blank"
                rel="noopener noreferrer">
                Abrir en Google Maps
            </a>
        </aside>
    </div>
</section>
@else
<section
    id="sedes-app"
    class="sede-shell"
    v-cloak
    data-vista='@json($vista)'
    data-error="{{ $errors->first() }}"
    data-disponibilidad-url="{{ route('persona.sede.disponibilidad') }}">
    {{-- El caso que se repite es que alguien mas se lleve el ultimo lugar
         entre un sondeo y el envio: <alertas> lo dice sin recargar, con el
         catalogo todavia delante. Nace con lo que trajo el servidor. Esta
         pantalla ya declara en su <noscript> que necesita JavaScript, asi que
         la lista de $errors se queda ahi para ese caso. --}}
    <alertas :mensaje="avisoError" tipo="error" clase="notificacion notificacion--error"></alertas>

    @if($errors->any())
        <noscript>
            <div class="notificacion notificacion--error" role="alert">
                <i class="fa-solid fa-circle-exclamation notificacion__icono" aria-hidden="true"></i>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </noscript>
    @endif

    <h1>Elige tu sede y horario</h1>
    <p class="sede-muted">Selecciona dónde y cuándo presentarás tu evaluación. Cada sede puede aplicar el examen en varios horarios; los lugares se actualizan automáticamente.</p>

    <noscript>
        <div class="notificacion notificacion--error" role="alert">
            <i class="fa-solid fa-circle-exclamation notificacion__icono" aria-hidden="true"></i>
            <span>Esta pantalla necesita JavaScript para mostrar los horarios disponibles y sus lugares al día.
            Habilítalo en tu navegador o escríbenos a {{ config('suif.soporte_correo') }}.</span>
        </div>
    </noscript>

    {{-- La lista se acota mientras se escribe, como en las bandejas del admin,
         así que no hay botón de filtrar. El submit se intercepta porque Enter
         enviaría el formulario igual. --}}
    <form class="filtros sede-filtro" role="search" @submit.prevent>
        <input
            class="control"
            type="search"
            v-model="buscar"
            ref="buscador"
            placeholder="Buscar por nombre o dirección…"
            aria-label="Buscar sede por nombre o dirección"
            autocomplete="off">
        <button type="button" class="boton boton--peligro" v-if="buscar" @click="limpiarBusqueda">Limpiar</button>
    </form>

    {{-- Lo anuncia el contador y no la lista: con el filtro en vivo, la lista
         entera se volvería a leer en cada tecla. --}}
    <p class="sede-contador" role="status">Sedes programadas · @{{ sedesFiltradas.length }}</p>

    <div class="sede-lista">
        <article v-for="sede in sedesFiltradas" :key="sede.id" class="tarjeta sede-tarjeta">
            <div class="sede-tarjeta__info">
                <h2 class="sede-tarjeta__nombre">@{{ sede.nombre }}</h2>
                <p class="sede-tarjeta__direccion">@{{ sede.direccion }}</p>
            </div>
            <form
                method="POST"
                action="{{ route('persona.sede.seleccionar') }}"
                class="sede-tarjeta__seleccion"
                @submit.prevent="abrirConfirmacion(sede, $event)">
                @csrf
                <fieldset class="sede-horarios">
                    <legend class="sede-horarios__titulo">
                        Horarios disponibles · @{{ sede.horarios.length }}
                    </legend>
                    <label
                        v-for="horario in sede.horarios"
                        :key="horario.evaluacion_id"
                        class="opcion sede-horario"
                        :class="{ 'sede-horario--lleno': !horario.con_cupo }">
                        <input
                            type="radio"
                            name="evaluacion_id"
                            :value="horario.evaluacion_id"
                            v-model="seleccion[sede.id]"
                            :disabled="!horario.con_cupo">
                        <span class="sede-horario__datos">
                            <span class="estado estado--info">@{{ etiquetaFecha(horario) }}</span>
                            <span class="sede-fecha">@{{ horario.hora_inicio }}–@{{ horario.hora_fin }} h</span>
                        </span>
                        <span class="sede-horario__cupo">
                            <span class="estado" :class="claseCupo(horario)">
                                @{{ horario.disponibles }} disponibles
                            </span>
                            <small>@{{ horario.con_cupo ? 'Lugares disponibles' : 'Sin cupo' }}</small>
                        </span>
                    </label>
                </fieldset>
                <button
                    type="submit"
                    class="boton boton--primario"
                    :disabled="!puedeEnviar(sede)">
                    @{{ sede.con_cupo ? 'Seleccionar horario' : 'Sin cupo' }}
                </button>
            </form>
        </article>

        <p v-if="!sedesFiltradas.length" class="vacio" role="status">
            <i class="fa-regular fa-map" aria-hidden="true"></i>
            No hay sedes programadas que coincidan con tu búsqueda.
        </p>
    </div>

    {{-- La selección no se puede deshacer, así que se pide confirmación
         explícita antes de enviar el formulario. --}}
    <div class="dialogo" v-if="confirmacion" @keydown.esc="cerrarConfirmacion">
        <div class="dialogo__velo" @click="cerrarConfirmacion"></div>
        <section
            class="dialogo__tarjeta"
            role="dialog"
            aria-modal="true"
            aria-labelledby="sede-modal-titulo"
            aria-describedby="sede-modal-descripcion"
            @keydown.tab="atraparFoco">
            <h2 class="dialogo__titulo" id="sede-modal-titulo">¿Confirmas esta sede y horario?</h2>
            <p class="dialogo__texto" id="sede-modal-descripcion">
                Tu lugar quedará apartado en el horario que elegiste.
                <strong>Una vez confirmado ya no podrás cambiarlo.</strong>
            </p>

            <dl class="sede-dialogo__datos">
                <dt>Sede</dt><dd>@{{ confirmacion.sede.nombre }}</dd>
                <dt>Fecha</dt><dd>@{{ etiquetaFecha(confirmacion.horario) }}</dd>
                <dt>Horario</dt><dd>@{{ confirmacion.horario.hora_inicio }}–@{{ confirmacion.horario.hora_fin }} h</dd>
            </dl>

            <div class="dialogo__acciones">
                <button type="button" class="boton boton--secundario" ref="cancelar" @click="cerrarConfirmacion">
                    Cancelar
                </button>
                <button
                    type="button"
                    class="boton boton--primario"
                    :class="{ 'boton--cargando': enviando }"
                    :disabled="enviando"
                    @click="confirmarSeleccion">
                    @{{ enviando ? 'Confirmando…' : 'Sí, confirmar' }}
                </button>
            </div>
        </section>
    </div>
</section>
@endif
@endsection

@if(!$confirmada)
    @push('scripts')
    <script src="{{ asset_versionado('assets/js/pages/persona-sede.js') }}"></script>
    @endpush
@endif
