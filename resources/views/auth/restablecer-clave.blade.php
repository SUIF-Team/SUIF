@extends('layouts.auth')

@section('title', 'SUIF — Crear clave de acceso')
@section('body_class', 'pagina-sistema auth-page login-pagina')

@section('styles')
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/login.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/recuperar-clave.css') }}">
@endsection

{{-- Tres estados de la misma pantalla: el enlace ya no sirve, el botón que
     confirma y la clave recién generada. Es un POST normal, sin Vue. --}}
@section('content')
<section class="recuperar-clave-seccion" aria-labelledby="restablecer-clave-titulo">
    <div class="login-tarjeta">
        @if(!empty($invalido))
            <h1 id="restablecer-clave-titulo" class="login-titulo-formulario">El enlace ya no es válido</h1>

            <p class="recuperar-clave-descripcion">
                Caducó o ya se usó. Cada enlace sirve una sola vez y durante una hora.
                Tu clave actual no cambió.
            </p>

            <div class="login-acciones">
                <a href="{{ route('clave.recuperar') }}" class="boton boton--primario">Solicitar otro enlace</a>
            </div>
        @elseif(isset($clave))
            <h1 id="restablecer-clave-titulo" class="login-titulo-formulario">Tu clave de acceso nueva</h1>

            <div class="notificacion notificacion--advertencia" role="status">
                <span>Cópiala y guárdala ahora: no se volverá a mostrar ni se envía por correo. Tu clave anterior dejó de funcionar.</span>
            </div>

            <div class="codigo" aria-label="Clave de acceso generada">
                <span class="codigo__valor">{{ $clave }}</span>
            </div>

            <div class="login-acciones">
                <a href="{{ route('login') }}" class="boton boton--primario">Iniciar sesión</a>
            </div>
        @else
            <h1 id="restablecer-clave-titulo" class="login-titulo-formulario">Crear clave de acceso nueva</h1>

            <p class="recuperar-clave-descripcion">
                Al confirmar generaremos una clave nueva y la verás en esta pantalla.
                Tu clave actual dejará de funcionar.
            </p>

            {{-- La acción es la misma URL firmada: el POST vuelve a comprobar
                 la firma y la huella antes de cambiar la clave. --}}
            <form method="POST" action="{{ $accion }}" class="login-formulario">
                @csrf
                <div class="login-acciones">
                    <button type="submit" class="boton boton--primario">Generar clave nueva</button>
                </div>
            </form>
        @endif

        <p class="login-preregistro recuperar-clave-regreso">
            <a href="{{ route('login') }}">Volver a iniciar sesión.</a>
        </p>
    </div>
</section>
@endsection
