{{--
    Alta y edición de una cuenta administrativa. La misma vista sirve para las
    dos, igual que en sedes y grupos: cambia $modoEdicion.

    $administrador es el arreglo que devuelve GestionAdministradores, no un
    modelo Eloquent: un administrador vive repartido entre USUARIO y PERSONA.
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
    $rolSeleccionado = (int) old('rol_id', $administrador['id_rol'] ?? 0);
    $entidadSeleccionada = old('entidad_federativa', $administrador['entidad_federativa'] ?? '009');
@endphp
<section class="admin-sedes admin-sedes--formulario" data-admin-administrador-formulario aria-labelledby="admin-administrador-formulario-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-administrador-formulario-titulo">{{ $modoEdicion ? 'Editar administrador' : 'Nuevo administrador' }}</h1>
                <p>La CURP es con la que inicia sesión. El tipo decide qué módulos verá en su tablero.</p>
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
                action="{{ $modoEdicion ? route('admin.administradores.update', $administrador['id_usuario']) : route('admin.administradores.store') }}"
                class="admin-sedes-formulario"
                @submit.prevent="enviar($event)">
                @csrf
                @if($modoEdicion)
                    @method('PUT')
                @endif

                <h2>Datos de la persona</h2>

                <div class="admin-sedes-formulario-grid">
                    <div class="admin-sedes-campo">
                        <label for="nombre">Nombre *</label>
                        <input id="nombre" name="nombre" type="text" maxlength="45" required
                               value="{{ old('nombre', $administrador['nombre_pila'] ?? '') }}">
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
                        <label for="entidad_federativa">Entidad federativa *</label>
                        <select id="entidad_federativa" name="entidad_federativa" required>
                            @foreach($entidades as $entidad)
                                <option value="{{ $entidad['clave'] }}" @selected($entidadSeleccionada === $entidad['clave'])>
                                    {{ $entidad['nombre'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <h2>Acceso al sistema</h2>

                <div class="admin-sedes-formulario-grid">
                    <div class="admin-sedes-campo">
                        <label for="curp">CURP *</label>
                        <input id="curp" name="curp" type="text" maxlength="18" minlength="18" required
                               class="admin-administradores-campo-curp"
                               value="{{ old('curp', $administrador['curp'] ?? '') }}"
                               aria-describedby="curp-ayuda">
                        <p id="curp-ayuda" class="admin-sedes-ayuda">18 caracteres. Es el usuario con el que entra al sistema.</p>
                    </div>
                    <div class="admin-sedes-campo">
                        <label for="clave">Clave de acceso {{ $modoEdicion ? '' : '*' }}</label>
                        <input id="clave" name="clave" type="password" minlength="8" maxlength="255"
                               autocomplete="new-password"
                               @if(!$modoEdicion) required @endif
                               aria-describedby="clave-ayuda">
                        <p id="clave-ayuda" class="admin-sedes-ayuda">
                            @if($modoEdicion)
                                Déjala vacía para conservar la que ya tiene.
                            @else
                                Mínimo 8 caracteres. Entrégasela por el canal institucional: el sistema no la vuelve a mostrar.
                            @endif
                        </p>
                    </div>
                </div>

                <h2>Tipo de administrador *</h2>
                <p class="admin-sedes-ayuda">Determina qué módulos aparecen en su tablero y qué puede abrir.</p>

                <fieldset class="admin-administradores-roles">
                    <legend class="admin-administradores-roles-leyenda">Tipo de administrador</legend>
                    @foreach($roles as $rol)
                        <label class="admin-administradores-rol" for="rol-{{ $rol['id'] }}">
                            <input
                                id="rol-{{ $rol['id'] }}"
                                type="radio"
                                name="rol_id"
                                value="{{ $rol['id'] }}"
                                required
                                @checked($rolSeleccionado === $rol['id'])>
                            <span class="admin-administradores-rol-cuerpo">
                                <span class="admin-administradores-rol-nombre">{{ $rol['etiqueta'] }}</span>
                                <span class="admin-administradores-rol-descripcion">{{ $rol['descripcion'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                <div class="admin-sedes-formulario-acciones">
                    @if($modoEdicion && $administrador['activo'])
                        <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="button" data-abrir-baja>Retirar acceso</button>
                    @endif
                    <a class="admin-sedes-boton admin-sedes-boton--secundario" href="{{ route('admin.administradores.index') }}">Cancelar</a>
                    <button class="admin-sedes-boton admin-sedes-boton--primario" type="submit">Guardar</button>
                </div>
            </form>
        </section>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.administradores.index') }}"
                etiqueta="Volver a la bandeja"></back-navigation>
        </div>
    </div>

    @if($modoEdicion && $administrador['activo'])
        <div class="admin-sedes-modal" data-modal-baja hidden>
            <div class="admin-sedes-modal-fondo" data-cerrar-baja></div>
            <section class="admin-sedes-modal-card" role="dialog" aria-modal="true" aria-labelledby="baja-administrador-titulo" aria-describedby="baja-administrador-descripcion">
                <h2 id="baja-administrador-titulo">¿Retirar el acceso de esta persona?</h2>
                <p id="baja-administrador-descripcion">
                    <strong>{{ $administrador['nombre'] }}</strong> dejará de poder entrar al sistema de inmediato,
                    aunque tenga la sesión abierta. Su registro se conserva porque es el rastro de los expedientes
                    que dictaminó. Puedes devolverle el acceso después.
                </p>
                <form method="POST" action="{{ route('admin.administradores.destroy', $administrador['id_usuario']) }}" class="admin-sedes-modal-acciones">
                    @csrf
                    @method('DELETE')
                    <button class="admin-sedes-boton admin-sedes-boton--secundario" type="button" data-cerrar-baja>Cancelar</button>
                    <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="submit">Sí, retirar acceso</button>
                </form>
            </section>
        </div>
    @endif
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-administradores.js') }}"></script>
@endsection
