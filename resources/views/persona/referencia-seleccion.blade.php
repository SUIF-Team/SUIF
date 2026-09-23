{{--
    persona/referencia-seleccion.blade.php
    Selector del paso «Obtener referencia»: explica el pago y encamina a la
    persona al flujo individual o al especial. No guarda la elección: sólo
    navega. El individual entrega una referencia del catálogo; el especial lleva
    a la captura del tercero que paga y de las personas que cubre.
--}}
@extends('layouts.persona')

@section('title', 'SUIF — Obtener referencia')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-referencia.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-referencia-seleccion.css') }}">
@endpush

@section('content')
<section class="referencia-shell">

    @if(session('success'))
        <div class="notificacion notificacion--exito" role="status">
            <i class="fa-solid fa-circle-check notificacion__icono" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('warning'))
        <div class="notificacion notificacion--advertencia" role="alert">
            <i class="fa-solid fa-circle-exclamation notificacion__icono" aria-hidden="true"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(!$solicitudAprobada)

        <div class="tarjeta referencia-tarjeta referencia-tarjeta--sola">
            <h1>Referencia bancaria</h1>
            <p class="referencia-muted">
                Tu referencia estará disponible cuando el equipo administrativo apruebe tu solicitud y tu documentación.
            </p>
        </div>

    @else

        <div class="referencia-aviso">
            <p>
                Para <strong>continuar con tu proceso</strong>, deberás realizar el
                <strong>pago correspondiente</strong> antes de iniciar el registro. Este pago es un
                <strong>requisito previo e indispensable</strong> para habilitar tu participación, por lo que no
                podrás avanzar a las siguientes etapas hasta haberlo efectuado. Al momento de pagar, utiliza
                exactamente la referencia que te fue asignada, ya que esta permite identificar y vincular tu pago
                con tu solicitud.
            </p>
            <p>
                La referencia que se genere servirá <strong>exclusivamente</strong> para <strong>tu pago</strong>.
            </p>
            <p>
                Si un tercero va a pagar la certificación por ti y por otras personas, lee la descripción de
                <strong>«Referencia especial»</strong>; si no es tu caso, continúa en
                <strong>«Referencia individual»</strong>.
            </p>
        </div>

        <h1 class="referencia-seleccion__titulo">Elige cómo se pagará tu certificación</h1>

        <div class="referencia-tarjetas referencia-tarjetas--seleccion">

            <article class="tarjeta referencia-tarjeta referencia-tarjeta--opcion">
                <div class="referencia-tarjeta__texto">
                    <p>
                        La <strong>referencia individual</strong> está dirigida a participantes que realizarán el
                        pago de su certificación de manera <strong>independiente</strong>. También deberá
                        seleccionarse esta opción cuando una empresa requiera que cada empleado cuente con una
                        <strong>referencia bancaria propia</strong>, con el fin de generar un <strong>CFDI</strong>
                        por participante.
                    </p>
                    <p>
                        Al seleccionar esta opción, el sistema generará una referencia bancaria <strong>única</strong>,
                        <strong>personal</strong> e <strong>intransferible</strong>, correspondiente al monto de un
                        solo participante. Esta referencia deberá ser utilizada únicamente por la persona a la que
                        fue asignada, ya que permitirá <strong>identificar</strong> y <strong>vincular</strong> el
                        pago con su <strong>solicitud de registro</strong>.
                    </p>
                    <p>
                        Si el pago será realizado por una empresa mediante una sola referencia bancaria para varios
                        empleados, deberá seleccionar la opción &ldquo;Referencia Especial&rdquo;.
                    </p>
                </div>

                <div class="referencia-tarjeta__pie">
                    <a class="boton boton--primario" href="{{ route('persona.referencia.individual') }}">
                        Referencia Individual
                    </a>
                </div>
            </article>

            <article class="tarjeta referencia-tarjeta referencia-tarjeta--opcion">
                <div class="referencia-tarjeta__texto">
                    <p>
                        La <strong>referencia especial</strong> está dirigida a <strong>empresas</strong> que desean
                        realizar el pago de <strong>varios participantes</strong> mediante una sola referencia
                        bancaria. Para solicitarla, el responsable captura el <strong>nombre o razón social</strong>
                        de quien pagará y los datos de <strong>cada participante</strong>: CURP, nombre y apellidos.
                    </p>
                    <p>
                        La Dirección emitirá <strong>una única referencia bancaria</strong> por el monto total
                        correspondiente y te avisaremos por correo en cuanto esté lista. Como el pago queda ligado
                        a todos los participantes, basta con que <strong>una sola persona</strong> suba el
                        comprobante.
                    </p>
                    <p>
                        Cada participante debe tener su <strong>solicitud aprobada</strong> y todavía sin referencia
                        asignada. El pago deberá provenir de la <strong>cuenta de la empresa</strong>: el
                        comprobante fiscal se emite a nombre de quien paga y no puede facturarse a terceros.
                    </p>
                    <p>
                        Si la empresa requiere una referencia bancaria individual para cada empleado, deberá
                        seleccionar la opción &ldquo;Referencia Individual&rdquo;.
                    </p>
                </div>

                <div class="referencia-tarjeta__pie">
                    <a class="boton boton--primario" href="{{ route('persona.referencia.especial') }}"
                       aria-describedby="referencia-especial-nota">
                        Referencia Especial
                    </a>
                    <p class="referencia-muted referencia-tarjeta__nota" id="referencia-especial-nota">
                        Todos los participantes deben tener su solicitud aprobada y aún sin referencia.
                    </p>
                </div>
            </article>

        </div>

    @endif

</section>
@endsection
