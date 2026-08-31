{{--
    Página del aviso de privacidad simplificado. El texto vive en el parcial
    porque el formulario de pre-registro muestra exactamente el mismo.
--}}
@extends('layouts.landing')

@section('title', 'SUIF — Aviso de privacidad simplificado')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/aviso-privacidad.css') }}">
@endsection

@section('content')
<article class="aviso">
    <div class="container aviso__contenedor aviso__contenedor--breve">
        <header class="aviso__encabezado">
            <p class="aviso__institucion">Sistema Integral de Certificaciones · SUIF</p>
            <h1>Aviso de privacidad simplificado</h1>
            <p class="aviso__version">Última actualización: 30 de agosto de 2026 · Versión 1.0</p>
        </header>

        @include('partials.aviso-privacidad-simplificado')
    </div>
</article>
@endsection
