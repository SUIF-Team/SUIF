@extends('layouts.admin')

@section('title', $modoEdicion ? 'SUIF — Editar sede' : 'SUIF — Nueva sede')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
@endsection

@section('content')
@php
    $direccionActual = old('direccion', $sede?->sede_direccion ?? '');
    $consultaMapa = $direccionActual !== '' ? $direccionActual : '19.324167,-99.184722';
    $horariosActuales = old('horarios', $horarios ?: [[
        'grupo_id' => '',
        'hora_inicio' => '',
        'fecha_inicio' => '',
        'hora_fin' => '',
        'fecha_fin' => '',
    ]]);
@endphp
<section class="admin-sedes admin-sedes--formulario" data-admin-sede-formulario data-horarios='@json(array_values($horariosActuales))' aria-labelledby="admin-sede-formulario-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-sede-formulario-titulo">{{ $modoEdicion ? 'Editar sede' : 'Crear sede' }}</h1>
                <p>Captura la sede y los horarios que se mostrarán a las personas participantes.</p>
            </div>
        </header>

        @if($errors->any())
            <div class="admin-sedes-alerta" role="alert">
                <p>Revisa la información capturada:</p>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="admin-sedes-formulario-layout">
            <aside class="admin-sedes-tarjeta admin-sedes-mapa">
                <h2>Mapa de referencia</h2>
                <p>La vista se actualiza a partir de la dirección capturada.</p>
                <div class="admin-sedes-mapa-marco">
                    <iframe
                        data-sede-mapa
                        src="https://maps.google.com/maps?q={{ urlencode($consultaMapa) }}&amp;hl=es&amp;z=16&amp;output=embed"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Mapa mostrando la ubicación de la sede"></iframe>
                </div>
            </aside>

            <section class="admin-sedes-tarjeta admin-sedes-formulario-tarjeta">
                <h2>Datos generales</h2>
                <form
                    method="POST"
                    action="{{ $modoEdicion ? route('admin.sedes.update', $sede->sede_id_sede) : route('admin.sedes.store') }}"
                    class="admin-sedes-formulario">
                    @csrf
                    @if($modoEdicion)
                        @method('PUT')
                    @endif

                    <div class="admin-sedes-campo admin-sedes-campo--completo">
                        <label for="nombre">Nombre de sede *</label>
                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            maxlength="150"
                            required
                            value="{{ old('nombre', $sede?->sede_nombre ?? '') }}"
                            placeholder="Ej. Sede Centro">
                    </div>

                    <div class="admin-sedes-campo admin-sedes-campo--completo">
                        <label for="direccion">Dirección completa *</label>
                        <textarea
                            id="direccion"
                            name="direccion"
                            maxlength="1000"
                            rows="3"
                            required
                            data-sede-direccion
                            placeholder="Calle, número, colonia, municipio, código postal y entidad federativa">{{ $direccionActual }}</textarea>
                    </div>

                    <h3 class="admin-sedes-subtitulo">Aplicaciones del examen</h3>
                    <p class="admin-sedes-ayuda">
                        Una sede puede aplicar el examen varias veces. Cada aplicación se muestra
                        a las personas participantes como un horario que pueden elegir.
                    </p>

                    <div data-horarios-app>
                        <fieldset v-for="(horario, indice) in horarios" :key="indice" class="admin-sedes-horario">
                            <legend class="admin-sedes-horario-titulo">Aplicación @{{ indice + 1 }}</legend>
                            <input type="hidden" :name="`horarios[${indice}][grupo_id]`" :value="horario.grupo_id || ''">
                            <div class="admin-sedes-formulario-grid">
                                <div class="admin-sedes-campo">
                                    <label :for="`hora_inicio_${indice}`">Hora de inicio *</label>
                                    <input :id="`hora_inicio_${indice}`" :name="`horarios[${indice}][hora_inicio]`" type="time" required v-model="horario.hora_inicio">
                                </div>
                                <div class="admin-sedes-campo">
                                    <label :for="`fecha_inicio_${indice}`">Fecha de inicio *</label>
                                    <input :id="`fecha_inicio_${indice}`" :name="`horarios[${indice}][fecha_inicio]`" type="date" required v-model="horario.fecha_inicio">
                                </div>
                                <div class="admin-sedes-campo">
                                    <label :for="`hora_fin_${indice}`">Hora de fin *</label>
                                    <input :id="`hora_fin_${indice}`" :name="`horarios[${indice}][hora_fin]`" type="time" required v-model="horario.hora_fin">
                                </div>
                                <div class="admin-sedes-campo">
                                    <label :for="`fecha_fin_${indice}`">Fecha de fin *</label>
                                    <input :id="`fecha_fin_${indice}`" :name="`horarios[${indice}][fecha_fin]`" type="date" required v-model="horario.fecha_fin">
                                </div>
                            </div>
                            <button
                                v-if="horarios.length > 1"
                                class="admin-sedes-boton admin-sedes-boton--secundario admin-sedes-horario-quitar"
                                type="button"
                                v-on:click="quitarHorario(indice)">Quitar aplicación</button>
                        </fieldset>

                        <button class="admin-sedes-boton admin-sedes-boton--secundario" type="button" v-on:click="agregarHorario">
                            Agregar aplicación
                        </button>
                    </div>

                    <div class="admin-sedes-formulario-grid">
                        <div class="admin-sedes-campo">
                            <label for="cupo">Aforo máximo por aplicación *</label>
                            <input id="cupo" name="cupo" type="number" min="1" max="2147483647" required value="{{ old('cupo', $sede?->sede_cupo ?? '') }}" aria-describedby="cupo-ayuda">
                            <p id="cupo-ayuda" class="admin-sedes-ayuda">Lugares disponibles en cada aplicación.</p>
                        </div>
                    </div>

                    <div class="admin-sedes-formulario-acciones">
                        @if($modoEdicion)
                            <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="button" data-abrir-eliminacion>Eliminar</button>
                        @endif
                        <a class="admin-sedes-boton admin-sedes-boton--secundario" href="{{ route('admin.sedes.index') }}">Cancelar</a>
                        <button class="admin-sedes-boton admin-sedes-boton--primario" type="submit">Guardar</button>
                    </div>
                </form>
            </section>
        </div>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.sedes.index') }}"
                etiqueta="Volver a la bandeja"
                etiqueta-accesible="Volver a la bandeja de sedes"></back-navigation>
        </div>
    </div>

    @if($modoEdicion)
        <div class="admin-sedes-modal" data-modal-eliminacion hidden>
            <div class="admin-sedes-modal-fondo" data-cerrar-eliminacion></div>
            <section class="admin-sedes-modal-card" role="dialog" aria-modal="true" aria-labelledby="eliminar-sede-titulo" aria-describedby="eliminar-sede-descripcion">
                <h2 id="eliminar-sede-titulo">¿Eliminar esta sede?</h2>
                <p id="eliminar-sede-descripcion">Se eliminará <strong>{{ $sede->sede_nombre }}</strong> y su programación. Esta acción no se puede deshacer.</p>
                <form method="POST" action="{{ route('admin.sedes.destroy', $sede->sede_id_sede) }}" class="admin-sedes-modal-acciones">
                    @csrf
                    @method('DELETE')
                    <button class="admin-sedes-boton admin-sedes-boton--secundario" type="button" data-cerrar-eliminacion>Cancelar</button>
                    <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="submit">Sí, eliminar</button>
                </form>
            </section>
        </div>
    @endif
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset_versionado('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-sedes.js') }}"></script>
@endsection
