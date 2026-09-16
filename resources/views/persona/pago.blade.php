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
        <div class="notificacion notificacion--exito" role="status">
            <i class="fa-solid fa-circle-check notificacion__icono" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    {{-- Una advertencia no es un error: hasta ahora las dos usaban la caja roja. --}}
    @if(session('warning'))
        <div class="notificacion notificacion--advertencia" role="alert">
            <i class="fa-solid fa-circle-exclamation notificacion__icono" aria-hidden="true"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="notificacion notificacion--error" role="alert">
            <i class="fa-solid fa-circle-exclamation notificacion__icono" aria-hidden="true"></i>
            <div>
                <strong>Corrige estos datos:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    @if($pagoEstado === 'sin_cargar')

        <div class="tarjeta pago-tarjeta pago-tarjeta--sola">
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
            <div class="tarjeta pago-tarjeta">
                <h1>Pago</h1>
                <?php
                    /* El papel del chip, no su color: el mismo mapa que usan las
                       bandejas del administrador para este pago. */
                    $papelEstado = [
                        'revision' => 'revision',
                        'validado' => 'exito',
                        'rechazado' => 'peligro',
                    ];
                ?>
                <p class="pago-estatus-linea">
                    El estatus de tu pago es el siguiente:
                    <span class="estado estado--{{ $papelEstado[$pagoEstado] ?? 'neutro' }}">
                        {{ $pagoEstado === 'revision' ? 'En revisión' : ($pagoEstado === 'validado' ? 'Aprobado' : 'Rechazado') }}
                    </span>
                </p>

                {{-- El motivo acompaña al estatus, no al formulario: es el
                     porqué de la decisión, igual que en el expediente. --}}
                @if($pagoEstado === 'rechazado')
                    <div class="aviso-motivo">
                        <strong class="aviso-motivo__titulo">Motivo del rechazo</strong>
                        <p>{{ $motivoRechazo ?: 'Tu comprobante fue rechazado porque no cumple con los requisitos necesarios.' }}</p>
                    </div>
                @endif
            </div>

            <div class="tarjeta pago-tarjeta">
                @if($pagoEstado === 'revision')
                    <h2 class="pago-tarjeta__titulo">Recordatorio</h2>
                    <p>Has adjuntado correctamente el comprobante. El proceso de revisión puede tardar hasta 24 horas.</p>
                    <button type="button" class="boton boton--primario" disabled>Registro bloqueado</button>
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

        <?php
            /* El avance del pago usa el mismo componente de pasos que el resto del
               trámite; sólo cambia que aquí van en línea. */
            $papelPaso = ['completo' => 'exito', 'activo' => 'revision', 'error' => 'peligro'];
        ?>
        <div class="tarjeta pago-tracker">
            <h3 class="pago-tracker__titulo">Estatus de validación</h3>
            <div class="pasos pasos--linea">
                @foreach(['Comprobante enviado', 'En revisión', 'Decisión'] as $indice => $etiqueta)
                    @if($indice > 0)
                        <?php $papelConector = $papelPaso[$tracker['conectores'][$indice - 1]] ?? null; ?>
                        <div class="pasos__conector @if($papelConector) pasos__conector--{{ $papelConector }} @endif"></div>
                    @endif
                    <?php $papel = $papelPaso[$tracker['pasos'][$indice]] ?? null; ?>
                    <div class="paso @if($papel) paso--{{ $papel }} @endif">
                        <span class="paso__numero">{{ $indice + 1 }}</span>
                        <small class="paso__estado">{{ $etiqueta }}</small>
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
