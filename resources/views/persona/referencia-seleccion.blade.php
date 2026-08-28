{{--
    persona/referencia-seleccion.blade.php
    Selector del paso «Obtener referencia»: explica el pago y encamina a la
    persona al flujo individual o al especial. No guarda la elección: sólo
    navega. El flujo especial todavía no existe, así que su botón va inhabilitado.
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
        <div class="referencia-alerta">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="referencia-alerta referencia-alerta--error">{{ session('warning') }}</div>
    @endif

    @if(!$solicitudAprobada)

        <div class="referencia-tarjeta referencia-tarjeta--sola">
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
                La Referencia generada será <strong>exclusivamente</strong> para realizar <strong>su pago</strong>.
            </p>
            <p>
                Si un tercero va a realizar el pago de la Certificación para usted y otras personas, por favor, lea
                la descripción en la opción de <strong>&ldquo;Referencia Especial&rdquo;</strong>; si este no es el
                caso, continúe en la opción <strong>&ldquo;Referencia Individual&rdquo;</strong>.
            </p>
        </div>

        <h1 class="referencia-seleccion__titulo">Elige cómo se pagará tu certificación</h1>

        <div class="referencia-tarjetas referencia-tarjetas--seleccion">

            <article class="referencia-tarjeta referencia-tarjeta--opcion">
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
                    <a class="referencia-boton" href="{{ route('persona.referencia.individual') }}">
                        Referencia Individual
                    </a>
                </div>
            </article>

            <article class="referencia-tarjeta referencia-tarjeta--opcion">
                <div class="referencia-tarjeta__texto">
                    <p>
                        La <strong>referencia especial</strong> está dirigida a <strong>empresas</strong> que desean
                        realizar el pago de <strong>varios participantes</strong> mediante una sola referencia
                        bancaria. Para generar esta referencia, el responsable deberá indicar únicamente el número
                        total de empleados que participarán en el proceso de certificación.
                    </p>
                    <p>
                        Con base en la cantidad de participantes registrada, el sistema generará
                        <strong>una única referencia bancaria</strong> por el monto total correspondiente. Esta
                        referencia deberá ser compartida con los <strong>empleados de la empresa</strong>, ya que
                        cada uno deberá ingresarla posteriormente para continuar con su
                        <strong>registro individual</strong>.
                    </p>
                    <p>
                        La referencia bancaria solo podrá ser utilizada por el número de participantes indicado al
                        momento de generarla.
                    </p>
                    <p>
                        Si la empresa requiere una referencia bancaria individual para cada empleado, deberá
                        seleccionar la opción &ldquo;Referencia Individual&rdquo;.
                    </p>
                </div>

                <div class="referencia-tarjeta__pie">
                    <button type="button" class="referencia-boton referencia-boton--inhabilitado"
                            disabled aria-describedby="referencia-especial-nota">
                        Referencia Especial
                    </button>
                    <p class="referencia-muted referencia-tarjeta__nota" id="referencia-especial-nota">
                        Disponible próximamente.
                    </p>
                </div>
            </article>

        </div>

    @endif

</section>
@endsection
