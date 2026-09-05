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
<section class="admin-sedes admin-sedes--formulario" data-admin-sede-formulario aria-labelledby="admin-sede-formulario-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-sede-formulario-titulo">{{ $modoEdicion ? 'Editar sede' : 'Crear sede' }}</h1>
                <p>Captura el lugar donde se aplicará el examen. Su programación se registra después en el módulo de grupos.</p>
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

            <section class="admin-sedes-tarjeta admin-sedes-formulario-tarjeta" data-formulario-ajax>
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
                    clase="admin-sedes-alerta"></alertas>

                <form
                    method="POST"
                    action="{{ $modoEdicion ? route('admin.sedes.update', $sede->sede_id_sede) : route('admin.sedes.store') }}"
                    class="admin-sedes-formulario"
                    @submit.prevent="enviar($event)">
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
<script src="{{ asset_versionado('assets/js/pages/admin-sedes.js') }}"></script>
@endsection
