{{--
    admin/administrador-formulario.blade.php
    Alta y edición de un administrador: sus datos personales, la CURP con la que
    inicia sesión y de qué área se encarga. Reutiliza el diseño de la gestión de
    sedes.
--}}
@extends('layouts.admin')

@section('title', $modoEdicion ? 'SUIF — Editar administrador' : 'SUIF — Nuevo administrador')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-administradores.css') }}">
@endsection

@section('content')
@php
    $rolActual = old('rol_id', $administrador['rol_id'] ?? '');
    $entidadActual = old('entidad_federativa', $administrador['clave_inegi'] ?? '');
    $esUnoMismo = $modoEdicion && (int) $administrador['id'] === (int) auth()->id();
@endphp
<section class="admin-sedes admin-sedes--formulario" data-admin-administrador-formulario aria-labelledby="admin-administrador-formulario-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-administrador-formulario-titulo">{{ $modoEdicion ? 'Editar administrador' : 'Crear administrador' }}</h1>
                <p>La CURP es con la que inicia sesión, y el tipo decide qué módulos puede abrir.</p>
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

        <section class="admin-sedes-tarjeta admin-sedes-formulario-tarjeta">
            <h2>Datos del administrador</h2>
            <form
                method="POST"
                action="{{ $modoEdicion ? route('admin.administradores.update', $administrador['id']) : route('admin.administradores.store') }}"
                class="admin-sedes-formulario">
                @csrf
                @if($modoEdicion)
                    @method('PUT')
                @endif

                <div class="admin-sedes-formulario-grid">
                    <div class="admin-sedes-campo">
                        <label for="nombre">Nombre(s) *</label>
                        <input id="nombre" name="nombre" type="text" maxlength="45" required
                            value="{{ old('nombre', $administrador['nombre'] ?? '') }}">
                    </div>
                    <div class="admin-sedes-campo">
                        <label for="primer_apellido">Apellido paterno *</label>
                        <input id="primer_apellido" name="primer_apellido" type="text" maxlength="45" required
                            value="{{ old('primer_apellido', $administrador['primer_apellido'] ?? '') }}">
                    </div>
                    <div class="admin-sedes-campo">
                        <label for="segundo_apellido">Apellido materno *</label>
                        <input id="segundo_apellido" name="segundo_apellido" type="text" maxlength="45" required
                            value="{{ old('segundo_apellido', $administrador['segundo_apellido'] ?? '') }}">
                    </div>
                    <div class="admin-sedes-campo">
                        <label for="curp">CURP *</label>
                        <input id="curp" name="curp" type="text" maxlength="18" minlength="18" required
                            autocapitalize="characters" autocomplete="off"
                            value="{{ old('curp', $administrador['curp'] ?? '') }}"
                            aria-describedby="curp-ayuda">
                        <p id="curp-ayuda" class="admin-sedes-ayuda">Con ella inicia sesión en el sistema.</p>
                    </div>
                </div>

                <div class="admin-sedes-campo admin-sedes-campo--completo">
                    <label for="entidad_federativa">Entidad federativa *</label>
                    <select id="entidad_federativa" name="entidad_federativa" required>
                        <option value="">Selecciona una entidad…</option>
                        @foreach($entidades as $entidad)
                            <option value="{{ $entidad['clave'] }}" @selected((string) $entidadActual === $entidad['clave'])>
                                {{ $entidad['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <fieldset class="admin-sedes-horario">
                    <legend class="admin-sedes-horario-titulo">Tipo de administrador *</legend>
                    <div class="admin-administradores-roles">
                        @foreach($roles as $rol)
                            <label class="admin-administradores-rol-opcion" for="rol-{{ $rol['id'] }}">
                                <input
                                    id="rol-{{ $rol['id'] }}"
                                    name="rol_id"
                                    type="radio"
                                    value="{{ $rol['id'] }}"
                                    required
                                    @checked((string) $rolActual === (string) $rol['id'])
                                    @disabled($esUnoMismo)>
                                <span>
                                    <strong>{{ $rol['nombre'] }}</strong>
                                    <small>{{ $rol['descripcion'] }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if($esUnoMismo)
                        {{-- Cambiarse el rol a uno mismo es la forma más rápida de
                             quedarse fuera del módulo desde el que se está trabajando. --}}
                        <input type="hidden" name="rol_id" value="{{ $administrador['rol_id'] }}">
                        <p class="admin-sedes-ayuda">Estás editando tu propia cuenta: tu tipo de administrador no se puede cambiar desde aquí.</p>
                    @endif
                </fieldset>

                <div class="admin-sedes-campo admin-sedes-campo--completo">
                    <label for="clave">Clave de acceso {{ $modoEdicion ? '' : '*' }}</label>
                    <input id="clave" name="clave" type="password" minlength="8" maxlength="255"
                        autocomplete="new-password"
                        @required(!$modoEdicion)
                        aria-describedby="clave-ayuda">
                    <p id="clave-ayuda" class="admin-sedes-ayuda">
                        Al menos 8 caracteres.
                        @if($modoEdicion)
                            Déjala vacía para conservar la clave actual.
                        @endif
                    </p>
                </div>

                <div class="admin-sedes-formulario-acciones">
                    @if($modoEdicion && !$esUnoMismo)
                        <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="button" data-abrir-eliminacion>Retirar acceso</button>
                    @endif
                    <a class="admin-sedes-boton admin-sedes-boton--secundario" href="{{ route('admin.administradores.index') }}">Cancelar</a>
                    <button class="admin-sedes-boton admin-sedes-boton--primario" type="submit">Guardar</button>
                </div>
            </form>
        </section>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.administradores.index') }}"
                etiqueta="Volver a la bandeja"
                etiqueta-accesible="Volver a la bandeja de administradores"></back-navigation>
        </div>
    </div>

    @if($modoEdicion && !$esUnoMismo)
        <div class="admin-sedes-modal" data-modal-eliminacion hidden>
            <div class="admin-sedes-modal-fondo" data-cerrar-eliminacion></div>
            <section class="admin-sedes-modal-card" role="dialog" aria-modal="true" aria-labelledby="baja-admin-titulo" aria-describedby="baja-admin-descripcion">
                <h2 id="baja-admin-titulo">¿Retirar el acceso a este administrador?</h2>
                <p id="baja-admin-descripcion">
                    <strong>{{ $administrador['nombre_completo'] }}</strong> dejará de poder entrar al sistema.
                    Su registro se conserva —es el rastro de lo que dictaminó— y puedes devolverle el acceso cuando quieras.
                </p>
                <form method="POST" action="{{ route('admin.administradores.destroy', $administrador['id']) }}" class="admin-sedes-modal-acciones">
                    @csrf
                    @method('DELETE')
                    <button class="admin-sedes-boton admin-sedes-boton--secundario" type="button" data-cerrar-eliminacion>Cancelar</button>
                    <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="submit">Sí, retirar acceso</button>
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
