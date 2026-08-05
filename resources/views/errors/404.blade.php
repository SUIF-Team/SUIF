@extends('layouts.auth')

@section('title', 'SUIF — Página no encontrada')
@section('body_class', 'pagina-sistema auth-page error-pagina')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/errores.css') }}">
@endsection

@section('content')
@include('partials.error-mensaje', [
    'codigo' => 404,
    'titulo' => 'No encontramos esta página',
    'mensaje' => 'La dirección que abriste no existe o cambió de lugar. Revisa el enlace e inténtalo de nuevo.',
    'rutaAccion' => route('home'),
    'textoAccion' => 'Ir al inicio',
])
@endsection