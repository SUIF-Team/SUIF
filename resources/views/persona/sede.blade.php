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
        <div class="sede-alerta sede-alerta--error" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="sede-confirmada-layout">
        <div class="sede-confirmada">
            <span class="sede-confirmada__icono" aria-hidden="true">✓</span>
            <h1>¡Sede confirmada!</h1>
            <p class="sede-muted">Tu lugar quedó apartado para la evaluación.</p>

            <div class="sede-resumen">
                <p class="sede-resumen__etiqueta">Sede seleccionada</p>
                <h3 class="sede-resumen__nombre">{{ $sede['nombre'] }}</h3>
                <dl class="sede-resumen__datos">
                    <dt>Dirección</dt><dd>{{ $sede['direccion'] }}</dd>
                    <dt>Fecha</dt><dd>{{ $sede['fecha'] }}</dd>
                    <dt>Horario</dt><dd>{{ $sede['horario'] }}</dd>
                </dl>
            </div>

            <div class="sede-acciones">
                <a href="{{ route('persona.sede.comprobante') }}" class="sede-boton sede-boton--secundario">
                    Generar comprobante
                </a>
                <a href="{{ route('persona.dashboard') }}" class="sede-boton">Continuar</a>
            </div>
        </div>

        <aside class="sede-mapa" aria-labelledby="sede-mapa-titulo">
            <h2 id="sede-mapa-titulo">Cómo llegar</h2>
            <p class="sede-mapa__ayuda">Ubicación aproximada a partir de la dirección de la sede.</p>
            <div class="sede-mapa__marco">
                <iframe
                    src="https://maps.google.com/maps?q={{ urlencode($sede['direccion']) }}&amp;hl=es&amp;z=16&amp;output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Mapa con la ubicación de {{ $sede['nombre'] }}"></iframe>
            </div>
            <a
                class="sede-boton sede-boton--secundario sede-mapa__enlace"
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
    data-disponibilidad-url="{{ route('persona.sede.disponibilidad') }}">
    @if($errors->any())
        <div class="sede-alerta sede-alerta--error" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h1>Elige tu sede y horario</h1>
    <p class="sede-muted">Selecciona dónde y cuándo presentarás tu evaluación. Cada sede puede aplicar el examen en varios horarios; los lugares se actualizan automáticamente.</p>

    <noscript>
        <div class="sede-alerta sede-alerta--error" role="alert">
            Esta pantalla necesita JavaScript para mostrar los horarios disponibles y sus lugares al día.
            Habilítalo en tu navegador o escríbenos a {{ config('suif.soporte_correo') }}.
        </div>
    </noscript>

    <form method="GET" action="{{ route('persona.sede.index') }}" class="sede-filtro">
        <input type="search" name="buscar" placeholder="Buscar por nombre o dirección…" value="{{ $buscarActual }}">
        <button type="submit" class="sede-boton sede-boton--filtrar">Filtrar</button>
        @if($buscarActual !== '')
            <a href="{{ route('persona.sede.index') }}" class="sede-boton sede-boton--secundario">Limpiar</a>
        @endif
    </form>

    <p class="sede-contador">Sedes programadas · @{{ sedes.length }}</p>

    <div class="sede-lista" aria-live="polite">
        <article v-for="sede in sedes" :key="sede.id" class="sede-tarjeta">
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
                        class="sede-horario"
                        :class="{ 'sede-horario--lleno': !horario.con_cupo }">
                        <input
                            type="radio"
                            name="evaluacion_id"
                            :value="horario.evaluacion_id"
                            v-model="seleccion[sede.id]"
                            :disabled="!horario.con_cupo">
                        <span class="sede-horario__datos">
                            <span class="sede-chip">@{{ etiquetaFecha(horario) }}</span>
                            <span class="sede-fecha">@{{ horario.hora_inicio }}–@{{ horario.hora_fin }} h</span>
                        </span>
                        <span class="sede-horario__cupo">
                            <span class="sede-cupo" :class="claseCupo(horario)">
                                @{{ horario.disponibles }} disponibles
                            </span>
                            <small>@{{ horario.con_cupo ? 'Lugares disponibles' : 'Sin cupo' }}</small>
                        </span>
                    </label>
                </fieldset>
                <button
                    type="submit"
                    class="sede-boton"
                    :class="{ 'sede-boton--deshabilitado': !puedeEnviar(sede) }"
                    :disabled="!puedeEnviar(sede)">
                    @{{ sede.con_cupo ? 'Seleccionar horario' : 'Sin cupo' }}
                </button>
            </form>
        </article>

        <p v-if="!sedes.length" class="sede-muted">No hay sedes programadas que coincidan con tu búsqueda.</p>
    </div>

    {{-- La selección no se puede deshacer, así que se pide confirmación
         explícita antes de enviar el formulario. --}}
    <div class="sede-modal" v-if="confirmacion" @keydown.esc="cerrarConfirmacion">
        <div class="sede-modal__fondo" @click="cerrarConfirmacion"></div>
        <section
            class="sede-modal__card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="sede-modal-titulo"
            aria-describedby="sede-modal-descripcion"
            @keydown.tab="atraparFoco">
            <h2 id="sede-modal-titulo">¿Confirmas esta sede y horario?</h2>
            <p id="sede-modal-descripcion">
                Tu lugar quedará apartado en el horario que elegiste.
                <strong>Una vez confirmado ya no podrás cambiarlo.</strong>
            </p>

            <dl class="sede-modal__datos">
                <dt>Sede</dt><dd>@{{ confirmacion.sede.nombre }}</dd>
                <dt>Fecha</dt><dd>@{{ etiquetaFecha(confirmacion.horario) }}</dd>
                <dt>Horario</dt><dd>@{{ confirmacion.horario.hora_inicio }}–@{{ confirmacion.horario.hora_fin }} h</dd>
            </dl>

            <div class="sede-modal__acciones">
                <button type="button" class="sede-boton sede-boton--secundario" ref="cancelar" @click="cerrarConfirmacion">
                    Cancelar
                </button>
                <button type="button" class="sede-boton" :disabled="enviando" @click="confirmarSeleccion">
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
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
    <script src="{{ asset_versionado('assets/js/pages/persona-sede.js') }}"></script>
    @endpush
@endif
