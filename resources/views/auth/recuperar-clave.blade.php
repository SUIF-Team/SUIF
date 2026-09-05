@extends('layouts.auth')

@section('title', 'SUIF — Recuperar clave de acceso')
@section('body_class', 'pagina-sistema auth-page login-pagina')

@section('styles')
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/login.css') }}">
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/recuperar-clave.css') }}">
@endsection

@section('content')
<section class="recuperar-clave-seccion" aria-labelledby="recuperar-clave-titulo">
    <div class="login-tarjeta">
        <h1 id="recuperar-clave-titulo" class="login-titulo-formulario">Recuperar clave de acceso</h1>

        <p class="recuperar-clave-descripcion">
            Escribe tu CURP y enviaremos una clave de acceso nueva al correo principal que
            registraste. La clave anterior dejará de funcionar.
        </p>

        {{-- La raíz de Vue envuelve el formulario y no es el formulario: Vue
             compila los hijos del elemento montado. Sin JavaScript se ve el
             mensaje que ya sirvió el servidor y el envío es el de siempre. --}}
        <div id="recuperar-clave-app" data-formulario-ajax data-exito="{{ session('success') }}">
        {{-- Pedir clave nueva termina en esta misma pantalla, asi que la
             confirmacion se pinta aqui. Sin Vue manda el <noscript>. --}}
        @if(session('success'))
            <noscript>
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            </noscript>
        @endif

        <alertas :mensaje="avisoExito" tipo="success" clase="alert alert-success"></alertas>
        <alertas :mensaje="avisoError" tipo="error" clase="alert alert-danger"></alertas>

        <form
            method="POST"
            action="{{ route('clave.recuperar.post') }}"
            class="login-formulario"
            @submit.prevent="enviar($event)">
            @csrf

            <div class="login-grupo">
                <label for="curp" class="login-etiqueta">CURP</label>
                <input
                    type="text"
                    id="curp"
                    name="curp"
                    value="{{ old('curp') }}"
                    class="form-control login-campo{{ $errors->has('curp') ? ' is-invalid' : '' }}"
                    maxlength="18"
                    autocomplete="username"
                    autocapitalize="characters"
                    aria-describedby="curp-ayuda{{ $errors->has('curp') ? ' curp-error' : '' }}"
                    placeholder="Ingresa tu CURP"
                    required>
                <small id="curp-ayuda" class="login-ayuda">Escribe los 18 caracteres de tu CURP.</small>
                @if($errors->has('curp'))
                    <span id="curp-error" class="invalid-feedback" role="alert">{{ $errors->first('curp') }}</span>
                @endif
            </div>

            <div class="login-acciones">
                <button type="submit" class="btn login-boton" :disabled="enviando">
                    <span v-if="enviando" v-cloak>Enviando…</span>
                    <span v-else>Enviar clave nueva</span>
                </button>
            </div>
        </form>
        </div>

        <p class="login-preregistro recuperar-clave-regreso">
            <a href="{{ route('login') }}">Volver a iniciar sesión.</a>
        </p>
    </div>
</section>
@endsection
