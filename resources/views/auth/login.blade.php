{{--
    auth/login.blade.php
    Migrado desde: app/views/auth/login.php
    Formulario de inicio de sesión.
    NOTA: usar @csrf (directiva Blade) en lugar del helper de core/Csrf.php.
--}}
@extends('layouts.auth')

@section('title', 'SUIF — Iniciar Sesión')

@section('content')
{{-- TODO: implementar formulario de login --}}
<form method="POST" action="{{ route('login.post') }}">
    {{ csrf_field() }}
    {{-- campos: email, password, botón submit --}}
</form>
@endsection
