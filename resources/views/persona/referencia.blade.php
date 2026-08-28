{{--
    persona/referencia.blade.php
    Migrado desde: app/views/persona/referencia.php
    Vista para obtener, consultar y descargar la referencia bancaria de pago.
    Se llega desde el selector; el controlador ya comprobó que la solicitud
    está aprobada.
--}}
@extends('layouts.persona')

@section('title', 'SUIF — Mi Referencia de Pago')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-referencia.css') }}">
@endpush

@section('content')
<section class="referencia-shell">

    @if(session('success'))
        <div class="referencia-alerta">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="referencia-alerta referencia-alerta--error">{{ session('warning') }}</div>
    @endif

    @if(!$referencia)

        <div class="referencia-tarjeta referencia-tarjeta--sola">
            <h1>Obtén tu referencia bancaria</h1>
            <p class="referencia-muted">
                Se te asignará una referencia única por ${{ $cuota }} {{ $moneda }}. Queda ligada a tu trámite y no se
                entrega a nadie más, así que consérvala hasta terminar tu pago.
            </p>

            @if($hayDisponibles)
                <form method="POST" action="{{ route('persona.referencia.generar') }}" class="referencia-form">
                    @csrf
                    <button type="submit" class="referencia-boton">Obtener referencia</button>
                </form>
            @else
                <p class="referencia-muted">
                    Por el momento no hay referencias disponibles. Inténtalo más tarde o comunícate con
                    <a href="mailto:{{ config('suif.soporte_correo') }}">{{ config('suif.soporte_correo') }}</a>.
                </p>
            @endif
        </div>

    @else

        <div class="referencia-tarjetas">
            <div class="referencia-tarjeta">
                <h1>Tu referencia bancaria</h1>
                <p class="referencia-muted">Es única y personal: úsala tal cual aparece al realizar tu pago.</p>

                <div class="referencia-codigo" aria-label="Referencia bancaria asignada">
                    <span id="referencia-numero">{{ $referencia['referencia'] }}</span>
                    <button type="button" class="referencia-copiar" data-copiar-referencia data-copiar-origen="#referencia-numero">
                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                        <span>Copiar</span>
                    </button>
                </div>

                <dl class="referencia-datos">
                    <div>
                        <dt>Monto</dt>
                        <dd>${{ $cuota }} {{ $moneda }}</dd>
                    </div>
                    @if($referencia['vigencia'])
                        <div>
                            <dt>Vigencia</dt>
                            <dd>{{ \Illuminate\Support\Carbon::parse($referencia['vigencia'])->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="referencia-tarjeta">
                <h2 class="referencia-tarjeta__titulo">Pago en ventanilla</h2>
                @if($referencia['ruta_formato'])
                    <p>Descarga el formato en PDF, imprímelo y preséntalo en la ventanilla del banco.</p>
                    <a class="referencia-boton referencia-boton--secundario" href="{{ route('persona.referencia.formato') }}">
                        <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                        <span>Descargar formato PDF</span>
                    </a>
                @else
                    <p>El formato para pagar en ventanilla aún no está disponible.</p>
                    <p class="referencia-muted">
                        Puedes pagar en línea con el número de referencia o escribir a
                        <a href="mailto:{{ config('suif.soporte_correo') }}">{{ config('suif.soporte_correo') }}</a>.
                    </p>
                @endif

                <p class="referencia-muted referencia-siguiente">
                    Cuando termines tu pago,
                    <a href="{{ route('persona.pago.index') }}">sube tu comprobante</a> para que sea validado.
                </p>
            </div>
        </div>

    @endif

</section>
@endsection

@push('scripts')
<script src="{{ asset_versionado('assets/js/pages/persona-referencia.js') }}"></script>
@endpush
