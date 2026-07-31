{{--
    participante/pago.blade.php
    Migrado desde: app/views/participante/pago.php
    Vista para subir comprobante de pago y ver estado del pago.
--}}
@extends('layouts.participante')

@section('title', 'SUIF — Mi Pago')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/participante-pago.css') }}">
@endpush


@section('content')
<section class="pago-shell">

    @if(session('success'))
        <div class="pago-alerta">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="pago-alerta pago-alerta--error">
            <strong>Revisa tu archivo:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(config('app.debug'))
    <div class="pago-demo">
        Simular:
        <a href="{{ route('participante.pago.demo', 'sin_cargar') }}">Sin cargar</a>
        <a href="{{ route('participante.pago.demo', 'revision') }}">Pendiente</a>
        <a href="{{ route('participante.pago.demo', 'validado') }}">Aprobado</a>
        <a href="{{ route('participante.pago.demo', 'rechazado') }}">Rechazado</a>
    </div>
    @endif

    @if($pagoEstado === 'sin_cargar')

        <div class="pago-tarjeta pago-tarjeta--sola">
            <h1>Sube tu comprobante de pago</h1>
            <p class="pago-muted">Adjunta el comprobante de tu pago por ${{ $cuota }} {{ $moneda }}. Solo se acepta un archivo PDF de máximo 1 MB.</p>
            <form method="POST" action="{{ route('participante.pago.comprobante') }}" enctype="multipart/form-data" class="pago-form">
                @csrf
                <label class="pago-archivo">
                    Seleccionar PDF
                    <input type="file" name="comprobante" accept="application/pdf" required>
                </label>
                <button type="submit" class="pago-boton">Enviar comprobante</button>
            </form>
        </div>

    @else

        <div class="pago-tarjetas">
            <div class="pago-tarjeta">
                <h1>Pago</h1>
                <p class="pago-estatus-linea">
                    El estatus de tu pago es el siguiente: 
                    <span class="pago-chip pago-chip--{{ $pagoEstado }}">
                        {{ $pagoEstado === 'revision' ? 'En revisión' : ($pagoEstado === 'validado' ? 'Aprobado' : 'Rechazado') }}
                    </span>
                </p>
            </div>

            <div class="pago-tarjeta">
                @if($pagoEstado === 'revision')
                    <h2 class="pago-tarjeta__titulo">Recordatorio</h2>
                    <p>Has adjuntado correctamente el comprobante. El proceso de revisión puede tardar hasta 24 horas.</p>
                    <button type="button" class="pago-boton pago-boton--bloqueado" disabled>Registro bloqueado</button>
                @elseif($pagoEstado === 'validado')
                    <h2 class="pago-tarjeta__titulo">Pago validado</h2>
                    <p>Tu comprobante fue aprobado por el equipo administrativo. Ya puedes continuar con la selección de sede.</p>
                @elseif($pagoEstado === 'rechazado')
                    <h2 class="pago-tarjeta__titulo pago-tarjeta__titulo--error">Comentarios</h2>
                    <p>Su comprobante de pago fue rechazado porque no cumple con los requisitos necesarios. Para subsanar esta situación, le solicitamos cargar nuevamente el comprobante correcto y legible.</p>
                    <form method="POST" action="{{ route('participante.pago.comprobante') }}" enctype="multipart/form-data" class="pago-form">
                        @csrf
                        <label class="pago-archivo">
                            Seleccionar PDF
                            <input type="file" name="comprobante" accept="application/pdf" required>
                        </label>
                        <button type="submit" class="pago-boton">Subsanar</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="pago-tracker">
            <p class="pago-tracker__titulo">Estatus de validación</p>
            <div class="pago-tracker__linea">
                @foreach(['Comprobante enviado', 'En revisión', 'Decisión'] as $indice => $etiqueta)
                    @if($indice > 0)
                        <div class="pago-tracker__conector pago-tracker__conector--{{ $tracker['conectores'][$indice - 1] }}"></div>
                    @endif
                    <div class="pago-tracker__paso pago-tracker__paso--{{ $tracker['pasos'][$indice] }}">
                        <span>{{ $indice + 1 }}</span>
                        <small>{{ $etiqueta }}</small>
                    </div>
                @endforeach
            </div>
        </div>

    @endif

</section>
@endsection