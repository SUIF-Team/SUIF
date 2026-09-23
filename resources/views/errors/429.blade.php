@extends('layouts.auth')

@section('title', 'SUIF — Demasiados intentos')
@section('body_class', 'pagina-sistema auth-page error-pagina')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/errores.css') }}">
@endsection

@section('content')
@include('partials.error-mensaje', [
    'codigo' => 429,
    'titulo' => 'Demasiados intentos seguidos',
    'mensaje' => 'Por seguridad pausamos tus intentos por un momento. Espera un minuto y vuelve a intentarlo.',
    'rutaAccion' => route('home'),
    'textoAccion' => 'Ir al inicio',
])
@endsection
