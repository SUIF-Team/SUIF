{{--
    admin/referencia-especial-detalle.blade.php
    Una solicitud de referencia especial: quién paga, a quiénes cubre y con qué
    referencia del catálogo se le entrega.

    Sólo se ofrecen las referencias libres, con formato PDF y por el importe
    exacto del grupo: entregar una por otro monto dejaría a los participantes
    con un número con el que el banco no cobra lo que debe.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Emitir referencia especial')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-referencias.css') }}">
@endsection

@section('content')
<section class="admin-referencias" aria-labelledby="admin-referencia-especial-titulo">
    <div class="admin-referencias-contenedor">
        <header class="admin-referencias-encabezado">
            <div>
                <h1 id="admin-referencia-especial-titulo">Solicitud #{{ $solicitud['id_pago'] }}</h1>
                <p>{{ $solicitud['participantes'] }} participantes · ${{ number_format($solicitud['monto'], 2) }} {{ config('suif.moneda', 'MXN') }}</p>
            </div>
        </header>

        @error('referencia')
            <div class="admin-referencias-tarjeta admin-referencias-aviso admin-referencias-aviso--error">
                {{ $message }}
            </div>
        @enderror

        <section class="admin-referencias-tarjeta admin-referencias-seccion" aria-label="Datos de quien paga">
            <h2>Quien realizará el pago</h2>
            {{-- Cada par va envuelto: en rejilla, un dt y un dd sueltos caen en
                 celdas distintas y la etiqueta se separa de su valor. --}}
            <dl class="admin-referencias-formato admin-referencias-formato--columnas">
                <div>
                    <dt>Nombre o razón social</dt>
                    <dd>{{ $solicitud['razon_social'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt>RFC</dt>
                    <dd>{{ $solicitud['rfc'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Tipo de persona</dt>
                    <dd>{{ $solicitud['persona_moral'] ? 'Persona moral' : 'Persona física' }}</dd>
                </div>
                <div>
                    <dt>Régimen fiscal</dt>
                    <dd>{{ $solicitud['regimen_fiscal'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Código postal</dt>
                    <dd>{{ $solicitud['codigo_postal'] ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="admin-referencias-tarjeta admin-referencias-seccion" aria-label="Participantes">
            <h2>Participantes que cubre</h2>
            <div class="admin-referencias-tabla-responsive">
                <table class="admin-referencias-tabla">
                    <thead>
                        <tr>
                            <th>CURP</th>
                            <th>Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($solicitud['personas'] as $persona)
                            <tr>
                                <td class="admin-referencias-tabla-numero">{{ $persona['curp'] }}</td>
                                <td>{{ $persona['nombre'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- data-formulario-ajax monta la app compartida de envio. Lo habitual
             aquí es que la referencia elegida ya se haya entregado a otra
             solicitud: se avisa sin recargar para elegir otra de la misma
             lista. Sin JavaScript el formulario se envía como siempre. --}}
        <section class="admin-referencias-tarjeta admin-referencias-seccion" data-formulario-ajax aria-label="Emisión de la referencia">
            <h2>Referencia por entregar</h2>

            <alertas
                :mensaje="avisoError"
                tipo="error"
                :errores="erroresServidor"
                clase="admin-referencias-aviso admin-referencias-aviso--error"></alertas>

            @if($solicitud['candidatas'])
                <form method="POST"
                      action="{{ route('admin.referencias.especiales.emitir', ['id' => $solicitud['id_pago']]) }}"
                      class="admin-referencias-filtros-formulario"
                      @submit.prevent="enviar($event)">
                    @csrf
                    <div class="admin-referencias-campo">
                        <label for="referencia">
                            Referencias libres por ${{ number_format($solicitud['monto'], 2) }}
                        </label>
                        <select id="referencia" name="referencia" required>
                            @foreach($solicitud['candidatas'] as $candidata)
                                <option value="{{ $candidata['id'] }}">
                                    {{ $candidata['referencia'] }}
                                    @if($candidata['vigencia'])
                                        · vence el {{ \Illuminate\Support\Carbon::parse($candidata['vigencia'])->format('d/m/Y') }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="admin-referencias-filtros-acciones">
                        <button class="admin-referencias-boton admin-referencias-boton--primario" type="submit" :disabled="enviando">
                            @{{ enviando ? 'Emitiendo…' : 'Emitir y avisar a los participantes' }}
                        </button>
                    </div>
                </form>
            @else
                <div class="admin-referencias-aviso admin-referencias-aviso--error">
                    <p>
                        No hay ninguna referencia libre por ${{ number_format($solicitud['monto'], 2) }}
                        {{ config('suif.moneda', 'MXN') }} con su formato PDF cargado. Pide al banco una por ese
                        importe y súbela al catálogo para poder emitirla.
                    </p>
                </div>
                <a class="admin-referencias-boton admin-referencias-boton--primario" href="{{ route('admin.referencias.carga') }}">
                    Subir referencias
                </a>
            @endif
        </section>

        <div id="admin-referencias-navegacion">
            <back-navigation
                destino="{{ route('admin.referencias.especiales.index') }}"
                etiqueta="Volver a la bandeja"></back-navigation>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-referencias.js') }}"></script>
@endsection
