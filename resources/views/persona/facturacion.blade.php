{{--
    persona/facturacion.blade.php
    Datos con los que se emite el CFDI de la certificación. Se llega aquí sólo
    desde el paso de pago, con el pago validado y con CFDI ya elegido; el
    controlador rechaza cualquier otro camino, incluida la segunda visita
    cuando los datos ya quedaron registrados.

    El formulario es HTML real —required, maxlength, pattern—, así que sin
    JavaScript se envía igual y el servidor valida lo mismo. Vue sólo adelanta
    los avisos y apaga el botón, por eso v-cloak va en los avisos y nunca en
    el formulario completo.
--}}
@extends('layouts.persona')

@section('title', 'SUIF — Datos de facturación')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-facturacion.css') }}">
@endpush

@section('content')
<section class="facturacion-shell">

    @if($errors->any())
        <div class="facturacion-alerta facturacion-alerta--error">
            <strong>Revisa tus datos de facturación:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="facturacion-tarjeta" id="facturacion-app" data-vista='@json($formulario)'>
        <h1>Datos para tu CFDI</h1>
        <p class="facturacion-muted">
            Captúralos tal como aparecen en tu constancia de situación fiscal. El CFDI se
            emitirá con uso «gastos en general» y se enviará al correo que indiques.
        </p>

        <form method="POST" action="{{ route('persona.facturacion.store') }}" class="facturacion-form">
            @csrf

            <div class="facturacion-grid">
                <div class="facturacion-campo">
                    <label for="razon_social">Nombre o razón social *</label>
                    <input
                        id="razon_social"
                        name="razon_social"
                        type="text"
                        maxlength="35"
                        required
                        v-model="razonSocial"
                        value="{{ $formulario['razonSocial'] }}">
                    @error('razon_social')
                        <p class="facturacion-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="facturacion-mensaje-validacion" role="alert" v-if="avisoRazonSocial" v-cloak>@{{ avisoRazonSocial }}</p>
                </div>

                <div class="facturacion-campo">
                    <label for="persona_moral">Tipo de persona *</label>
                    <select id="persona_moral" name="persona_moral" required v-model="personaMoral">
                        <option value="0" @selected($formulario['personaMoral'] === '0')>Persona física</option>
                        <option value="1" @selected($formulario['personaMoral'] === '1')>Persona moral</option>
                    </select>
                    @error('persona_moral')
                        <p class="facturacion-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="facturacion-campo">
                    <label for="rfc">RFC *</label>
                    {{-- text y no number: la homoclave lleva letras. --}}
                    <input
                        id="rfc"
                        name="rfc"
                        type="text"
                        class="facturacion-campo__rfc"
                        minlength="12"
                        maxlength="13"
                        required
                        v-model="rfc"
                        @input="normalizarRfc"
                        value="{{ $formulario['rfc'] }}"
                        aria-describedby="rfc-ayuda">
                    <p id="rfc-ayuda" class="facturacion-campo__ayuda">
                        12 caracteres si es persona moral, 13 si es persona física.
                    </p>
                    @error('rfc')
                        <p class="facturacion-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="facturacion-mensaje-validacion" role="alert" v-if="avisoRfc" v-cloak>@{{ avisoRfc }}</p>
                </div>

                <div class="facturacion-campo">
                    <label for="codigo_postal">Código postal *</label>
                    {{-- text y no number: un código como 01000 perdería el
                         cero de la izquierda. --}}
                    <input
                        id="codigo_postal"
                        name="codigo_postal"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]{5}"
                        maxlength="5"
                        required
                        v-model="codigoPostal"
                        @input="normalizarCodigoPostal"
                        value="{{ $formulario['codigoPostal'] }}">
                    @error('codigo_postal')
                        <p class="facturacion-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="facturacion-mensaje-validacion" role="alert" v-if="avisoCodigoPostal" v-cloak>@{{ avisoCodigoPostal }}</p>
                </div>

                <div class="facturacion-campo">
                    <label for="regimen_fiscal">Régimen fiscal *</label>
                    <select id="regimen_fiscal" name="regimen_fiscal" required v-model="regimenFiscal">
                        <option value="">Selecciona una opción</option>
                        @foreach($regimenes as $regimen)
                            <option
                                value="{{ $regimen['id'] }}"
                                @selected($formulario['regimenFiscal'] === (string) $regimen['id'])>
                                {{ $regimen['nombre'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('regimen_fiscal')
                        <p class="facturacion-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="facturacion-campo">
                    <label for="correo_cfdi">Correo para enviar el CFDI *</label>
                    <input
                        id="correo_cfdi"
                        name="correo_cfdi"
                        type="email"
                        maxlength="65"
                        required
                        v-model="correoCfdi"
                        value="{{ $formulario['correoCfdi'] }}"
                        aria-describedby="correo_cfdi-ayuda">
                    <p id="correo_cfdi-ayuda" class="facturacion-campo__ayuda">
                        Puede ser distinto del correo con el que entras al sistema.
                    </p>
                    @error('correo_cfdi')
                        <p class="facturacion-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="facturacion-mensaje-validacion" role="alert" v-if="avisoCorreo" v-cloak>@{{ avisoCorreo }}</p>
                </div>
            </div>

            <p class="facturacion-aviso">
                Revisa tus datos antes de enviarlos: <strong>una vez registrados no podrán
                modificarse</strong>, porque con ellos se emite tu factura.
            </p>

            {{-- El botón nace habilitado en el HTML y se apaga desde Vue: si el
                 script no llega a cargar, el formulario se envía como siempre. --}}
            <div class="facturacion-acciones">
                <a href="{{ route('persona.pago.index') }}" class="facturacion-boton facturacion-boton--secundario">
                    Volver a mi pago
                </a>
                <button type="submit" class="facturacion-boton" :disabled="!puedeEnviar">
                    Registrar datos fiscales
                </button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset_versionado('assets/js/pages/persona-facturacion.js') }}"></script>
@endpush
