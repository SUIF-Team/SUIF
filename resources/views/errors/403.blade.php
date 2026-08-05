@extends('layouts.auth')

@section('title', 'SUIF — Acceso no permitido')
@section('body_class', 'pagina-sistema auth-page error-pagina')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/errores.css') }}">
@endsection

@section('content')
@include('partials.error-mensaje', [
    'codigo' => 403,
    'titulo' => 'No tienes acceso a esta página',
    'mensaje' => 'Tu cuenta no cuenta con los permisos necesarios para ver esta sección. Si crees que se trata de un error, comunícate con soporte.',
    'rutaAccion' => route('home'),
    'textoAccion' => 'Ir al inicio',
])
@endsection