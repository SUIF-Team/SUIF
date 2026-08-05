@extends('layouts.auth')

@section('title', 'SUIF — Tu sesión expiró')
@section('body_class', 'pagina-sistema auth-page error-pagina')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/errores.css') }}">
@endsection

@section('content')
@include('partials.error-mensaje', [
    'codigo' => 419,
    'titulo' => 'Tu sesión expiró',
    'mensaje' => 'Por seguridad, la sesión se cierra después de un tiempo sin actividad. Vuelve a iniciar sesión y continúa tu trámite desde donde lo dejaste: la información que ya habías guardado no se perdió.',
    'rutaAccion' => route('login'),
    'textoAccion' => 'Iniciar sesión',
])
@endsection