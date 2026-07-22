{{--
    participante/preregistro.blade.php
    Migrado desde: app/views/participante/preregistro.php
    Formulario de pre-registro con datos personales y documentación.
    NOTA: usar csrf_field() en todos los formularios POST.
--}}
@extends('layouts.participante')

@section('title', 'SUIF — Pre-registro')

@section('content')
{{-- TODO: formulario multi-paso de pre-registro --}}
<form method="POST" action="{{ route('participante.preregistro.store') }}">
    {{ csrf_field() }}
    {{-- campos del pre-registro --}}
</form>
@endsection
