@extends('layouts.auth')

@section('title', 'SUIF — Error del sistema')
@section('body_class', 'pagina-sistema auth-page error-pagina')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/errores.css') }}">
@endsection

@section('content')
@include('partials.error-mensaje', [
    'codigo' => 500,
    'titulo' => 'Algo salió mal de nuestro lado',
    'mensaje' => 'Ocurrió un problema en el sistema y ya quedó registrado para revisarlo. Intenta de nuevo en unos minutos; si el problema continúa, escríbenos a soporte.',
    'rutaAccion' => route('home'),
    'textoAccion' => 'Ir al inicio',
])
@endsection