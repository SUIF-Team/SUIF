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
@endphp
<section class="admin-sedes" data-admin-sede-formulario aria-labelledby="admin-sede-formulario-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-sede-formulario-titulo">{{ $modoEdicion ? 'Editar sede' : 'Crear sede' }}</h1>
                <p>Captura el lugar donde se aplicará el examen. Su programación se registra después en el módulo de grupos.</p>
            </div>
        </header>

        @if($errors->any())
            <div class="notificacion notificacion--error" role="alert">
                <i class="fa-solid fa-circle-exclamation notificacion__icono" aria-hidden="true"></i>
                <div>
                    <p>Corrige estos datos:</p>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="admin-sedes-formulario-layout">
            <aside class="tarjeta tarjeta--amplia admin-sedes-mapa">
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

            <section class="tarjeta tarjeta--amplia admin-sedes-formulario-tarjeta" data-formulario-ajax>
                <h2>Datos generales</h2>
                {{-- data-formulario-ajax monta la app compartida de envío: el guardado
                     va por fetch y lo que el servidor rechace se dice aquí mismo,
                     sin recargar ni volver a subir la pantalla. La raíz envuelve al
                     formulario y no es el formulario, porque Vue compila los hijos
                     del elemento montado. Sin JavaScript se envía como siempre. --}}
                <alertas
                    :mensaje="avisoError"
                    tipo="error"
                    :errores="erroresServidor"
                    clase="notificacion notificacion--error"></alertas>

                <form
                    method="POST"
                    action="{{ $modoEdicion ? route('admin.sedes.update', $sede->sede_id_sede) : route('admin.sedes.store') }}"
                    class="admin-sedes-formulario"
                    @submit.prevent="enviar($event)">
                    @csrf
                    @if($modoEdicion)
                        @method('PUT')
                    @endif

                    <div class="campo admin-sedes-campo--completo">
                        <label class="etiqueta" for="nombre">Nombre de sede *</label>
                        <input class="control"
                            id="nombre"
                            name="nombre"
                            type="text"
                            maxlength="150"
                            required
                            value="{{ old('nombre', $sede?->sede_nombre ?? '') }}"
                            placeholder="Ej. Sede Centro">
                    </div>

                    <div class="campo admin-sedes-campo--completo">
                        <label class="etiqueta" for="direccion">Dirección completa *</label>
                        <textarea class="control"
                            id="direccion"
                            name="direccion"
                            maxlength="1000"
                            rows="3"
                            required
                            data-sede-direccion
                            placeholder="Calle, número, colonia, municipio, código postal y entidad federativa">{{ $direccionActual }}</textarea>
                    </div>

                    <div class="admin-sedes-formulario-grid">
                        <div class="campo">
                            <label class="etiqueta" for="cupo">Aforo máximo por aplicación *</label>
                            <input class="control" id="cupo" name="cupo" type="number" min="1" max="2147483647" required value="{{ old('cupo', $sede?->sede_cupo ?? '') }}" aria-describedby="cupo-ayuda">
                            <p id="cupo-ayuda" class="ayuda">Lugares disponibles en cada aplicación.</p>
                        </div>
                    </div>

                    <div class="admin-sedes-formulario-acciones">
                        @if($modoEdicion)
                            <button class="boton boton--peligro" type="button" data-abrir-eliminacion>Eliminar</button>
                        @endif
                        <a class="boton boton--secundario" href="{{ route('admin.sedes.index') }}">Cancelar</a>
                        <button class="boton boton--primario" type="submit">Guardar</button>
                    </div>
                </form>
            </section>
        </div>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.sedes.index') }}"
                etiqueta="Volver a la bandeja"></back-navigation>
        </div>
    </div>

    @if($modoEdicion)
        <div class="dialogo" data-modal-eliminacion hidden>
            <div class="dialogo__velo" data-cerrar-eliminacion></div>
            <section class="dialogo__tarjeta" role="dialog" aria-modal="true" aria-labelledby="eliminar-sede-titulo" aria-describedby="eliminar-sede-descripcion">
                <h2 class="dialogo__titulo" id="eliminar-sede-titulo">¿Eliminar esta sede?</h2>
                <p class="dialogo__texto" id="eliminar-sede-descripcion">Se eliminará <strong>{{ $sede->sede_nombre }}</strong> y su programación. Después ya no podrás recuperarla.</p>
                <form method="POST" action="{{ route('admin.sedes.destroy', $sede->sede_id_sede) }}" class="dialogo__acciones">
                    @csrf
                    @method('DELETE')
                    <button class="boton boton--secundario" type="button" data-cerrar-eliminacion>Cancelar</button>
                    <button class="boton boton--peligro-solido" type="submit">Eliminar sede</button>
                </form>
            </section>
        </div>
    @endif
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-sedes.js') }}"></script>
@endsection
