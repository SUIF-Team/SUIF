@extends('layouts.admin')

@section('title', 'SUIF — Documentación de Pre-registro')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
@endsection

@section('content')
@php
    $datos_vista = [
        'persona' => $persona,
        'estados' => $estados,
        'decisiones' => old('documentos', $decisiones_documentos ?? []),
        'motivo_rechazo' => old('motivo_rechazo', $observaciones_rechazo['motivo_rechazo'] ?? ''),
        'fecha_limite' => old('fecha_limite', $observaciones_rechazo['fecha_limite'] ?? ''),
        'modo_solo_lectura' => $modo_solo_lectura ?? false,
    ];
@endphp
<section id="preregistro-admin-app" class="admin-preregistro-flujo" data-preregistro-admin data-vista='@json($datos_vista)' aria-labelledby="documentacion-preregistro-titulo" v-cloak>
    <header class="admin-preregistro-tarjeta admin-preregistro-perfil">
        <div class="admin-preregistro-usuario">
            <span class="admin-preregistro-avatar" aria-hidden="true">@{{ iniciales }}</span>
            <div>
                <h1 id="documentacion-preregistro-titulo">@{{ nombreCompleto }}</h1>
                <p>CURP: @{{ persona.curp }} · @{{ persona.entidad_federativa }}</p>
            </div>
        </div>
        <span class="admin-preregistro-estado admin-preregistro-estado--revision" role="status">@{{ estados.general }}</span>
    </header>

    <nav class="admin-preregistro-progreso" aria-label="Progreso del trámite">
        <div class="admin-preregistro-paso" :class="clasePaso('preregistro')">
            <span class="admin-preregistro-paso-titulo">Pre-registro</span>
            <span class="admin-preregistro-paso-estado">@{{ estados.preregistro }}</span>
        </div>
        <div class="admin-preregistro-paso" :class="clasePaso('documentacion')" aria-current="step">
            <span class="admin-preregistro-paso-titulo">Documentación</span>
            <span class="admin-preregistro-paso-estado">@{{ estados.documentacion }}</span>
        </div>
    </nav>

    <form method="POST" action="{{ route('admin.documentos.validar', ['id' => $persona['id'], 'origen' => $contexto_bandeja['origen']]) }}" class="admin-preregistro-contenido-principal" v-on:submit="enviando = true">
        @csrf
        <input type="hidden" name="origen" value="{{ $contexto_bandeja['origen'] }}">
        <input v-for="documento in persona.documentos" :key="`estado-${documento.id}`" type="hidden" :name="`documentos[${documento.id}]`" :value="estadoDocumento(documento.id) || ''">
        <main class="admin-preregistro-tarjeta admin-preregistro-documentos" aria-labelledby="lista-documentos-titulo">
            <h2 id="lista-documentos-titulo">Documentación</h2>
            <p v-if="!persona.documentos.length" class="admin-preregistro-documentos-vacio">
                No hay documentos cargados para esta solicitud.
            </p>
            <ul v-else class="admin-preregistro-lista-documentos">
                <li v-for="documento in persona.documentos" :key="documento.id" class="admin-preregistro-documento">
                    <div>
                        <h3 class="admin-preregistro-documento-titulo">@{{ documento.titulo }}</h3>
                        <p class="admin-preregistro-documento-meta">@{{ documento.nombre }}</p>
                        <p class="admin-preregistro-documento-meta">@{{ documento.fecha_carga }}</p>
                    </div>
                    <div class="admin-preregistro-documento-acciones">
                        <button type="button" class="admin-preregistro-icono admin-preregistro-icono--aprobar" :class="{ 'esta-seleccionado': estadoDocumento(documento.id) === 'aprobado' }" :aria-pressed="estadoDocumento(documento.id) === 'aprobado'" :aria-label="'Aprobar ' + documento.titulo" :disabled="modoSoloLectura" v-on:click="actualizarDocumento(documento.id, 'aprobado')">✓</button>
                        <button type="button" class="admin-preregistro-icono admin-preregistro-icono--rechazar" :class="{ 'esta-seleccionado': estadoDocumento(documento.id) === 'rechazado' }" :aria-pressed="estadoDocumento(documento.id) === 'rechazado'" :aria-label="'Rechazar ' + documento.titulo" :disabled="modoSoloLectura" v-on:click="actualizarDocumento(documento.id, 'rechazado')">×</button>
                        <button type="button" class="admin-preregistro-previsualizar" v-on:click="abrirDocumento(documento, $event)">Previsualizar</button>
                    </div>
                </li>
            </ul>
        </main>

        <aside class="admin-preregistro-columna-lateral" aria-label="Acciones y observaciones">
            <section v-if="!modoSoloLectura" class="admin-preregistro-tarjeta admin-preregistro-acciones-generales" aria-labelledby="acciones-generales-titulo">
                <h2 id="acciones-generales-titulo">Acciones generales</h2>
                <div>
                    <button
                        class="admin-preregistro-boton admin-preregistro-boton--rechazar"
                        type="submit"
                        formaction="{{ route('admin.documentos.interrumpir', ['id' => $persona['id'], 'origen' => $contexto_bandeja['origen']]) }}"
                        formmethod="POST"
                        :disabled="enviando">
                        @{{ enviando ? 'Procesando...' : 'Interrumpir trámite' }}
                    </button>
                    <button class="admin-preregistro-boton admin-preregistro-boton--aceptar" type="submit" :disabled="enviando || !todosDocumentosResueltos">
                        @{{ enviando ? 'Guardando...' : 'Guardar' }}
                    </button>
                </div>
                <p v-if="!todosDocumentosResueltos" id="estado-documentos-pendientes" class="admin-preregistro-mensaje-validacion" role="status">
                    Resuelve todos los documentos para guardar la revisi&oacute;n.
                </p>
                @if ($errors->has('documentos'))
                    <p class="admin-preregistro-mensaje-validacion" role="alert">{{ $errors->first('documentos') }}</p>
                @endif
            </section>

            <section v-if="hayDocumentosRechazados" class="admin-preregistro-tarjeta admin-preregistro-observaciones" aria-labelledby="observaciones-titulo">
                <h2 id="observaciones-titulo">Observaciones</h2>
                <div class="admin-preregistro-campo-observacion">
                    <label for="motivo-rechazo">Motivo del rechazo</label>
                    <textarea
                        id="motivo-rechazo"
                        name="motivo_rechazo"
                        v-model="motivoRechazo"
                        rows="5"
                        maxlength="255"
                        required
                        :readonly="modoSoloLectura"
                        aria-describedby="motivo-rechazo-ayuda"
                        placeholder="Describe el motivo del rechazo."></textarea>
                    <p id="motivo-rechazo-ayuda" class="admin-preregistro-ayuda">
                        Es obligatorio al rechazar uno o más documentos.
                    </p>
                    @error('motivo_rechazo')
                        <p class="admin-preregistro-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="admin-preregistro-campo-observacion">
                    <label for="fecha-limite">Fecha límite</label>
                    <input
                        id="fecha-limite"
                        name="fecha_limite"
                        v-model="fechaLimite"
                        type="date"
                        required
                        :disabled="modoSoloLectura"
                        aria-describedby="fecha-limite-ayuda">
                    <p id="fecha-limite-ayuda" class="admin-preregistro-ayuda">
                        Es obligatoria al rechazar uno o más documentos.
                    </p>
                    @error('fecha_limite')
                        <p class="admin-preregistro-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </section>
        </aside>
    </form>

    <div
        v-if="documentoPrevisualizado"
        class="admin-preregistro-modal"
        v-on:click.self="cerrarDocumento"
        v-on:keydown.esc.window="cerrarDocumento">
        <section class="admin-preregistro-modal-contenido" role="dialog" aria-modal="true" aria-labelledby="visor-documento-titulo" aria-describedby="visor-documento-nombre">
            <header class="admin-preregistro-modal-encabezado">
                <div>
                    <h2 id="visor-documento-titulo">@{{ documentoPrevisualizado.titulo }}</h2>
                    <p id="visor-documento-nombre">@{{ documentoPrevisualizado.nombre }}</p>
                </div>
                <button ref="botonCerrarVisor" class="admin-preregistro-modal-cerrar" type="button" v-on:click="cerrarDocumento">
                    Cerrar
                </button>
            </header>
            <iframe
                class="admin-preregistro-visor-pdf"
                :src="documentoPrevisualizado.ruta_visor"
                :title="'Vista previa de ' + documentoPrevisualizado.nombre">
            </iframe>
        </section>
    </div>

    <back-navigation
        destino="{{ $contexto_bandeja['ruta'] }}"
        etiqueta="{{ $contexto_bandeja['etiqueta'] }}"
        etiqueta-accesible="{{ $contexto_bandeja['etiqueta_accesible'] }}"></back-navigation>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-preregistro.js') }}"></script>
@endsection
