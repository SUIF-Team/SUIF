{{--
    persona/pago.blade.php
    Migrado desde: app/views/persona/pago.php
    Vista para subir comprobante de pago y ver estado del pago.
--}}
@extends('layouts.persona')

@section('title', 'SUIF — Mi Pago')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-pago.css') }}">
@endpush


@section('content')
<section class="pago-shell">

    @if(session('success'))
        <div class="pago-alerta">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="pago-alerta pago-alerta--error">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
        <div class="pago-alerta pago-alerta--error">
            <strong>Revisa los datos de tu pago:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($pagoEstado === 'sin_cargar')

        <div class="pago-tarjeta pago-tarjeta--sola">
            <h1>Sube tu comprobante de pago</h1>
            <p class="pago-muted">Adjunta el comprobante de tu pago por ${{ $cuota }} {{ $moneda }}. Solo se acepta un archivo PDF de máximo 1 MB.</p>
            @if($puedeCargar)
                @include('partials.pago-comprobante-form', [
    'etiquetaBoton' => 'Enviar comprobante',
    'vistaFormulario' => $vistaFormulario,
])
            @elseif($mensajeBloqueo)
                <p class="pago-muted">{{ $mensajeBloqueo }}</p>
            @endif
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

                {{-- El motivo acompaña al estatus, no al formulario: es el
                     porqué de la decisión, igual que en el expediente. --}}
                @if($pagoEstado === 'rechazado')
                    <div class="pago-observacion">
                        <strong>Motivo del rechazo</strong>
                        <p>{{ $motivoRechazo ?: 'Tu comprobante fue rechazado porque no cumple con los requisitos necesarios.' }}</p>
                    </div>
                @endif
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
                    <h2 class="pago-tarjeta__titulo pago-tarjeta__titulo--error">Subsana tu comprobante</h2>
                    <p>Revisa el motivo del rechazo y carga nuevamente un comprobante correcto y legible.</p>
                    @if($puedeCargar)
                        @include('partials.pago-comprobante-form', [
    'etiquetaBoton' => 'Subsanar',
    'vistaFormulario' => $vistaFormulario,
])
                    @elseif($mensajeBloqueo)
                        <p class="pago-muted">{{ $mensajeBloqueo }}</p>
                    @endif
                @endif
            </div>
        </div>

        @if($comprobanteFiscal['visible'])
            @include('partials.pago-comprobante-fiscal', ['comprobanteFiscal' => $comprobanteFiscal])
        @endif

        <div class="pago-tracker">
            <h3 class="pago-tracker__titulo">Estatus de validación</h3>
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

{{-- El selector de comprobante vive en el estado «validado», donde
     $puedeCargar es falso: sin esta condición Vue no llegaría a cargarse ahí
     y la confirmación no aparecería. --}}
@if($puedeCargar || $comprobanteFiscal['puedeElegir'])
    @push('scripts')
    @if($puedeCargar)
        <script src="{{ asset_versionado('assets/js/pages/persona-pago.js') }}"></script>
    @endif
    @if($comprobanteFiscal['puedeElegir'])
        <script src="{{ asset_versionado('assets/js/pages/persona-comprobante-fiscal.js') }}"></script>
    @endif
    @endpush
@endif
