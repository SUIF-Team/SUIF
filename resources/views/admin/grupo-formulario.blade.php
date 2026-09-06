{{--
    admin/grupo-formulario.blade.php
    Alta y edición de un grupo: la sede donde se aplica el examen y sus datos
    de aplicación. Reutiliza el diseño y las clases de la gestión de sedes.
--}}
@extends('layouts.admin')

@section('title', $modoEdicion ? 'SUIF — Editar grupo' : 'SUIF — Nuevo grupo')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
@endsection

@section('content')
@php
    $sedeActual = old('sede_id', $grupo['sede_id'] ?? $sedePreseleccionada ?? '');
    $ocupados = $grupo['ocupados'] ?? 0;
@endphp
<section class="admin-sedes admin-sedes--formulario" data-admin-grupo-formulario aria-labelledby="admin-grupo-formulario-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-grupo-formulario-titulo">{{ $modoEdicion ? 'Editar grupo' : 'Crear grupo' }}</h1>
                <p>Elige la sede y captura los datos de aplicación que verán las personas participantes.</p>
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
            <h2>Datos del grupo</h2>
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
                action="{{ $modoEdicion ? route('admin.grupos.update', $grupo['id']) : route('admin.grupos.store') }}"
                class="admin-sedes-formulario"
                @submit.prevent="enviar($event)">
                @csrf
                @if($modoEdicion)
                    @method('PUT')
                @endif

                <div class="admin-sedes-campo admin-sedes-campo--completo">
                    <label for="sede_id">Sede *</label>
                    <select id="sede_id" name="sede_id" required aria-describedby="sede-ayuda">
                        <option value="">Selecciona una sede…</option>
                        @foreach($sedes as $sede)
                            <option value="{{ $sede['id'] }}" @selected((string) $sedeActual === (string) $sede['id'])>
                                {{ $sede['nombre'] }} · aforo {{ $sede['cupo'] }} · {{ $sede['grupos'] }} {{ $sede['grupos'] === 1 ? 'grupo' : 'grupos' }} · {{ $sede['estado'] }}
                            </option>
                        @endforeach
                    </select>
                    <p id="sede-ayuda" class="admin-sedes-ayuda">
                        El aforo es el número de lugares de cada grupo, y el estatus indica si la sede
                        todavía tiene cupo.
                        @if($modoEdicion && $ocupados > 0)
                            Este grupo ya tiene {{ $ocupados }} {{ $ocupados === 1 ? 'persona asignada' : 'personas asignadas' }}, así que no puede cambiar de sede.
                        @endif
                    </p>
                </div>

                <fieldset class="admin-sedes-horario">
                    <legend class="admin-sedes-horario-titulo">Datos de aplicación</legend>
                    <div class="admin-sedes-formulario-grid">
                        <div class="admin-sedes-campo">
                            <label for="hora_inicio">Hora de inicio *</label>
                            <input id="hora_inicio" name="hora_inicio" type="time" required value="{{ old('hora_inicio', $grupo['hora_inicio'] ?? '') }}">
                        </div>
                        <div class="admin-sedes-campo">
                            <label for="fecha_inicio">Fecha de inicio *</label>
                            <input id="fecha_inicio" name="fecha_inicio" type="date" required value="{{ old('fecha_inicio', $grupo['fecha_inicio'] ?? '') }}">
                        </div>
                        <div class="admin-sedes-campo">
                            <label for="hora_fin">Hora de fin *</label>
                            <input id="hora_fin" name="hora_fin" type="time" required value="{{ old('hora_fin', $grupo['hora_fin'] ?? '') }}">
                        </div>
                        <div class="admin-sedes-campo">
                            <label for="fecha_fin">Fecha de fin *</label>
                            <input id="fecha_fin" name="fecha_fin" type="date" required value="{{ old('fecha_fin', $grupo['fecha_fin'] ?? '') }}">
                        </div>
                    </div>
                </fieldset>

                <div class="admin-sedes-formulario-acciones">
                    @if($modoEdicion)
                        <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="button" data-abrir-eliminacion>Eliminar</button>
                    @endif
                    <a class="admin-sedes-boton admin-sedes-boton--secundario" href="{{ route('admin.grupos.index') }}">Cancelar</a>
                    <button class="admin-sedes-boton admin-sedes-boton--primario" type="submit">Guardar</button>
                </div>
            </form>
        </section>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.grupos.index') }}"
                etiqueta="Volver a la bandeja"></back-navigation>
        </div>
    </div>

    @if($modoEdicion)
        <div class="admin-sedes-modal" data-modal-eliminacion hidden>
            <div class="admin-sedes-modal-fondo" data-cerrar-eliminacion></div>
            <section class="admin-sedes-modal-card" role="dialog" aria-modal="true" aria-labelledby="eliminar-grupo-titulo" aria-describedby="eliminar-grupo-descripcion">
                <h2 id="eliminar-grupo-titulo">¿Eliminar este grupo?</h2>
                <p id="eliminar-grupo-descripcion">Se eliminará la aplicación del {{ \Illuminate\Support\Carbon::parse($grupo['fecha_inicio'])->format('d/m/Y') }} en <strong>{{ $grupo['sede_nombre'] }}</strong>. Esta acción no se puede deshacer.</p>
                <form method="POST" action="{{ route('admin.grupos.destroy', $grupo['id']) }}" class="admin-sedes-modal-acciones">
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
