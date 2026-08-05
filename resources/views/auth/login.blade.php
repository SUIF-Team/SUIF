@extends('layouts.auth')

@section('title', 'SUIF — Iniciar Sesión')
@section('body_class', 'pagina-sistema auth-page login-pagina')

@section('styles')
    <link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/login.css') }}">
@endsection

@section('content')
<section class="login-seccion" aria-labelledby="login-titulo">
    <div class="login-panel login-panel-informacion">
        <div class="login-informacion-contenido">
            <p class="login-bienvenida">Bienvenido al</p>
            <h1 id="login-titulo" class="login-titulo-proceso">Proceso de Certificación UIF</h1>

            <span class="login-divisor" aria-hidden="true"></span>

            <p class="login-descripcion">
                Desde la plataforma podrás gestionar tu proceso de registro ante la UIF. Aquí encontrarás tres opciones principales:
            </p>

            <ul class="login-lista">
                <li>
                    <strong>Iniciar pre-registro:</strong>
                    Si es tu primera vez, completa el formulario de pre-registro y obtén una clave de acceso en tu correo electrónico.
                </li>
                <li>
                    <strong>Consultar referencia bancaria:</strong>
                    Una vez pre-registrado, podrás consultar la referencia asignada para realizar tu pago correspondiente.
                </li>
                <li>
                    <strong>Completar tu proceso:</strong>
                    Si ya cuentas con la clave enviada a tu correo, ingrésala para retomar o completar tu trámite desde donde lo dejaste.
                </li>
            </ul>

            <p class="login-indicacion">Si aún no has iniciado, comienza con tu pre-registro.</p>
        </div>
    </div>

    <div class="login-panel login-panel-formulario">
        <div class="login-tarjeta">
            <h2 class="login-titulo-formulario">Inicio de sesión</h2>

            @if(session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="login-formulario">
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

                <div class="login-grupo">
                    <label for="clave" class="login-etiqueta">Clave de acceso</label>
                    <input
                        type="password"
                        id="clave"
                        name="clave"
                        class="form-control login-campo{{ $errors->has('clave') ? ' is-invalid' : '' }}"
                        autocomplete="current-password"
                        aria-describedby="{{ $errors->has('clave') ? 'clave-error' : 'clave-ayuda' }}"
                        placeholder="Ingresa tu clave de acceso"
                        required>
                    <small id="clave-ayuda" class="login-ayuda">Usa la clave enviada a tu correo electrónico.</small>
                    @if($errors->has('clave'))
                        <span id="clave-error" class="invalid-feedback" role="alert">{{ $errors->first('clave') }}</span>
                    @endif
                </div>

                <div class="login-acciones">
                    <button type="submit" class="btn login-boton">Acceder</button>
                </div>

                <p class="login-preregistro">
                <a href="{{ route('participante.preregistro.index') }}">¿Aún no tienes clave? Realiza tu pre-registro.</a>                  
            </p>
            </form>
        </div>
    </div>
</section>
@endsection
