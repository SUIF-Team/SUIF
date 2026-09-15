{{--
    Alta y edición de un responsable de pago. Mismo formulario que sedes, sin
    mapa. La baja vive aquí y no en la bandeja, como en administradores, y no
    pide confirmación: se deshace con «Reactivar» desde la bandeja.
--}}
@extends('layouts.admin')

@section('title', $responsable ? 'SUIF — Editar responsable' : 'SUIF — Nuevo responsable')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
@endsection

@section('content')
<section class="admin-sedes" aria-labelledby="admin-responsable-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-responsable-titulo">{{ $responsable ? 'Editar responsable' : 'Nuevo responsable' }}</h1>
                <p>Su nombre aparece en «Atendido por» del formato de pago de la DEC.</p>
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

        <section class="admin-sedes-tarjeta admin-sedes-formulario-tarjeta" data-formulario-ajax>
            <h2>Datos del responsable</h2>
            <alertas
                :mensaje="avisoError"
                tipo="error"
                :errores="erroresServidor"
                clase="admin-sedes-alerta"></alertas>

            <form
                method="POST"
                action="{{ $responsable ? route('admin.responsables.update', $responsable['id']) : route('admin.responsables.store') }}"
                class="admin-sedes-formulario"
                @submit.prevent="enviar($event)">
                @csrf
                @if($responsable)
                    @method('PUT')
                @endif

                <div class="admin-sedes-campo admin-sedes-campo--completo">
                    <label for="nombre">Nombre(s) *</label>
                    <input
                        id="nombre"
                        name="nombre"
                        type="text"
                        maxlength="55"
                        required
                        value="{{ old('nombre', $responsable['nombre'] ?? '') }}">
                </div>

                <div class="admin-sedes-formulario-grid">
                    <div class="admin-sedes-campo">
                        <label for="apellido_paterno">Apellido paterno *</label>
                        <input
                            id="apellido_paterno"
                            name="apellido_paterno"
                            type="text"
                            maxlength="55"
                            required
                            value="{{ old('apellido_paterno', $responsable['apellido_paterno'] ?? '') }}">
                    </div>

                    <div class="admin-sedes-campo">
                        <label for="apellido_materno">Apellido materno</label>
                        <input
                            id="apellido_materno"
                            name="apellido_materno"
                            type="text"
                            maxlength="55"
                            value="{{ old('apellido_materno', $responsable['apellido_materno'] ?? '') }}">
                    </div>
                </div>

                <div class="admin-sedes-formulario-acciones">
                    @if($responsable && $responsable['activo'])
                        {{-- Envía el formulario de baja, que va fuera de éste: un
                             formulario no puede ir dentro de otro. --}}
                        <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="submit" form="baja-responsable">Dar de baja</button>
                    @endif
                    <a class="admin-sedes-boton admin-sedes-boton--secundario" href="{{ route('admin.responsables.index') }}">Cancelar</a>
                    <button class="admin-sedes-boton admin-sedes-boton--primario" type="submit">Guardar</button>
                </div>
            </form>
        </section>

        @if($responsable && $responsable['activo'])
            <form id="baja-responsable" method="POST" action="{{ route('admin.responsables.destroy', $responsable['id']) }}">
                @csrf
                @method('DELETE')
            </form>
        @endif

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.responsables.index') }}"
                etiqueta="Volver a la bandeja"></back-navigation>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-sedes.js') }}"></script>
@endsection
