@extends('layouts.admin')

@section('title', 'SUIF — Documentación de Pre-registro')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-preregistro.css') }}">
@endsection

@section('content')
@php
    $datos_vista = ['participante' => $participante, 'estados' => $estados];
@endphp
<section id="preregistro-admin-app" class="admin-preregistro-flujo" data-preregistro-admin data-vista='@json($datos_vista)' aria-labelledby="documentacion-preregistro-titulo" v-cloak>
    <header class="admin-preregistro-tarjeta admin-preregistro-perfil">
        <div class="admin-preregistro-usuario">
            <span class="admin-preregistro-avatar" aria-hidden="true">@{{ iniciales }}</span>
            <div>
                <h1 id="documentacion-preregistro-titulo">@{{ nombreCompleto }}</h1>
                <p>CURP: @{{ participante.curp }} · Folio @{{ participante.folio }} · @{{ participante.entidad_federativa }}</p>
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

    <div class="admin-preregistro-contenido-principal">
        <main class="admin-preregistro-tarjeta admin-preregistro-documentos" aria-labelledby="lista-documentos-titulo">
            <h2 id="lista-documentos-titulo">Documentación</h2>
            <ul class="admin-preregistro-lista-documentos">
                <li v-for="documento in participante.documentos" :key="documento.id" class="admin-preregistro-documento">
                    <span class="admin-preregistro-documento-titulo">@{{ documento.titulo }}</span>
                    <div class="admin-preregistro-documento-acciones">
                        <button type="button" class="admin-preregistro-icono admin-preregistro-icono--aprobar" :class="{ 'esta-seleccionado': estadoDocumento(documento.id) === 'aprobado' }" :aria-pressed="estadoDocumento(documento.id) === 'aprobado'" aria-label="Aprobar documento" v-on:click="actualizarDocumento(documento.id, 'aprobado')">✓</button>
                        <button type="button" class="admin-preregistro-icono admin-preregistro-icono--rechazar" :class="{ 'esta-seleccionado': estadoDocumento(documento.id) === 'rechazado' }" :aria-pressed="estadoDocumento(documento.id) === 'rechazado'" aria-label="Rechazar documento" v-on:click="actualizarDocumento(documento.id, 'rechazado')">×</button>
                        <button type="button" class="admin-preregistro-previsualizar" :aria-pressed="documentoPrevisualizado === documento.id" v-on:click="documentoPrevisualizado = documento.id">Previsualizar</button>
                    </div>
                </li>
            </ul>
        </main>

        <aside class="admin-preregistro-tarjeta admin-preregistro-acciones-generales" aria-labelledby="acciones-generales-titulo">
            <h2 id="acciones-generales-titulo">Acciones generales</h2>
            <div>
                <button class="admin-preregistro-boton admin-preregistro-boton--rechazar" type="button">Interrumpir trámite</button>
                <button class="admin-preregistro-boton admin-preregistro-boton--aceptar" type="button">Guardar</button>
            </div>
        </aside>
    </div>

    <back-navigation destino="{{ route('admin.participantes.show', ['id' => $participante['id']]) }}"></back-navigation>
</section>
@endsection

@section('scripts')
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="{{ asset('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset('assets/js/pages/admin-preregistro.js') }}"></script>
@endsection
