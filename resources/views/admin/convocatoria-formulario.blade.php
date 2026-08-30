{{--
    Alta y edición de una convocatoria.

    El formulario captura los datos y nunca el estado: una convocatoria nace
    vigente y se cierra o se interrumpe desde la bandeja, donde el cambio queda
    fechado en la bitácora.
--}}
@extends('layouts.admin')

@section('title', $modoEdicion ? 'SUIF — Editar convocatoria' : 'SUIF — Nueva convocatoria')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-convocatorias.css') }}">
@endsection

@section('content')
<section class="admin-sedes admin-convocatorias admin-sedes--formulario" data-admin-convocatoria-formulario aria-labelledby="admin-convocatoria-formulario-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-convocatoria-formulario-titulo">{{ $modoEdicion ? 'Editar convocatoria' : 'Crear convocatoria' }}</h1>
                <p>
                    @if($modoEdicion)
                        Corrige el calendario y la cuota. Para cerrarla o interrumpirla, usa la bandeja.
                    @else
                        La convocatoria queda vigente en cuanto se guarda, y sólo puede haber una vigente a la vez.
                    @endif
                </p>
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
            <h2>Datos de la convocatoria</h2>
            <form
                method="POST"
                action="{{ $modoEdicion ? route('admin.convocatorias.update', $convocatoria['id']) : route('admin.convocatorias.store') }}"
                class="admin-sedes-formulario">
                @csrf
                @if($modoEdicion)
                    @method('PUT')
                @endif

                <div class="admin-sedes-campo admin-sedes-campo--completo">
                    <label for="nombre">Nombre de la convocatoria *</label>
                    <input
                        id="nombre"
                        name="nombre"
                        type="text"
                        maxlength="300"
                        required
                        value="{{ old('nombre', $convocatoria['nombre'] ?? '') }}"
                        placeholder="Ej. Certificación 2027 en materia de prevención de operaciones con recursos de procedencia ilícita">
                </div>

                <div class="admin-sedes-formulario-grid">
                    <div class="admin-sedes-campo">
                        <label for="monto">Cuota de recuperación *</label>
                        <input
                            id="monto"
                            name="monto"
                            type="number"
                            step="0.01"
                            min="0.01"
                            max="99999999.99"
                            required
                            value="{{ old('monto', $convocatoria['monto'] ?? '') }}"
                            aria-describedby="monto-ayuda">
                        <p id="monto-ayuda" class="admin-sedes-ayuda">
                            Es lo que la persona deberá pagar para continuar su trámite, en {{ config('suif.moneda') }}.
                        </p>
                    </div>
                </div>

                <h3 class="admin-convocatorias-subtitulo">Vigencia de la convocatoria</h3>
                <div class="admin-sedes-formulario-grid">
                    <div class="admin-sedes-campo">
                        <label for="fecha_inicio">Inicio *</label>
                        <input id="fecha_inicio" name="fecha_inicio" type="date" required
                            value="{{ old('fecha_inicio', $convocatoria['fecha_inicio'] ?? '') }}"
                            aria-describedby="fecha-inicio-ayuda">
                        <p id="fecha-inicio-ayuda" class="admin-sedes-ayuda">Día en que se publica la convocatoria.</p>
                    </div>
                    <div class="admin-sedes-campo">
                        <label for="fecha_fin">Término *</label>
                        <input id="fecha_fin" name="fecha_fin" type="date" required
                            value="{{ old('fecha_fin', $convocatoria['fecha_fin'] ?? '') }}"
                            aria-describedby="fecha-fin-ayuda">
                        <p id="fecha-fin-ayuda" class="admin-sedes-ayuda">Día en que concluye por completo.</p>
                    </div>
                </div>

                <h3 class="admin-convocatorias-subtitulo">Ventana de registro</h3>
                <div class="admin-sedes-formulario-grid">
                    <div class="admin-sedes-campo">
                        <label for="fecha_inicio_registro">Apertura del registro *</label>
                        <input id="fecha_inicio_registro" name="fecha_inicio_registro" type="date" required
                            value="{{ old('fecha_inicio_registro', $convocatoria['fecha_inicio_registro'] ?? '') }}"
                            aria-describedby="registro-ayuda">
                        <p id="registro-ayuda" class="admin-sedes-ayuda">Desde este día se habilita el pre-registro.</p>
                    </div>
                    <div class="admin-sedes-campo">
                        <label for="fecha_fin_registro">Cierre del registro *</label>
                        <input id="fecha_fin_registro" name="fecha_fin_registro" type="date" required
                            value="{{ old('fecha_fin_registro', $convocatoria['fecha_fin_registro'] ?? '') }}">
                    </div>
                    <div class="admin-sedes-campo">
                        <label for="fin_fecha_entrega_docs">Límite para entregar documentos *</label>
                        <input id="fin_fecha_entrega_docs" name="fin_fecha_entrega_docs" type="date" required
                            value="{{ old('fin_fecha_entrega_docs', $convocatoria['fin_fecha_entrega_docs'] ?? '') }}"
                            aria-describedby="docs-ayuda">
                        <p id="docs-ayuda" class="admin-sedes-ayuda">No puede vencer antes de que cierre el registro.</p>
                    </div>
                </div>

                <div class="admin-sedes-formulario-acciones">
                    @if($modoEdicion)
                        <button class="admin-sedes-boton admin-sedes-boton--eliminar" type="button" data-abrir-eliminacion>Eliminar</button>
                    @endif
                    <a class="admin-sedes-boton admin-sedes-boton--secundario" href="{{ route('admin.convocatorias.index') }}">Cancelar</a>
                    <button class="admin-sedes-boton admin-sedes-boton--primario" type="submit">Guardar</button>
                </div>
            </form>
        </section>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.convocatorias.index') }}"
                etiqueta="Volver a la bandeja"
                etiqueta-accesible="Volver a la bandeja de convocatorias"></back-navigation>
        </div>
    </div>

    @if($modoEdicion)
        <div class="admin-sedes-modal" data-modal-eliminacion hidden>
            <div class="admin-sedes-modal-fondo" data-cerrar-eliminacion></div>
            <section class="admin-sedes-modal-card" role="dialog" aria-modal="true" aria-labelledby="eliminar-convocatoria-titulo" aria-describedby="eliminar-convocatoria-descripcion">
                <h2 id="eliminar-convocatoria-titulo">¿Eliminar esta convocatoria?</h2>
                <p id="eliminar-convocatoria-descripcion">
                    Se eliminará <strong>{{ $convocatoria['nombre'] }}</strong> y su historial de estados.
                    Esta acción no se puede deshacer.
                    @if($convocatoria['solicitudes'] > 0)
                        Tiene {{ number_format($convocatoria['solicitudes']) }} solicitudes registradas, así que
                        no podrá eliminarse: ciérrala o interrúmpela desde la bandeja.
                    @endif
                </p>
                <form method="POST" action="{{ route('admin.convocatorias.destroy', $convocatoria['id']) }}" class="admin-sedes-modal-acciones">
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
<script src="{{ asset_versionado('assets/js/pages/admin-convocatorias.js') }}"></script>
@endsection
