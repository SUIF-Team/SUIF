{{--
    persona/referencia-especial.blade.php
    Camino especial del paso «Obtener referencia»: los datos del tercero que
    pagará y la lista de participantes que cubrirá una sola referencia.

    A diferencia del resto de los formularios del trámite, la lista de
    participantes se arma y se deshace en pantalla, así que esta captura sí
    necesita JavaScript; sin él se ofrece el camino individual. El servidor
    valida exactamente lo mismo que Vue: aquí sólo se adelantan los avisos y se
    apaga el botón.

    La confirmación es un diálogo porque el envío no tiene vuelta atrás: liga a
    todos los participantes al mismo pago.
--}}
@extends('layouts.persona')

@section('title', 'SUIF — Referencia especial')

@push('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-referencia.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/persona-referencia-especial.css') }}">
@endpush

@section('content')
<section class="referencia-shell">

    @if($errors->any())
        <div class="referencia-alerta referencia-alerta--error">
            <strong>Revisa los datos de la referencia especial:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <noscript>
        <div class="referencia-alerta referencia-alerta--error">
            La captura de participantes necesita JavaScript. Habilítalo o solicita una
            <a href="{{ route('persona.referencia.individual') }}">referencia individual</a>.
        </div>
    </noscript>

    {{-- La raíz de Vue es el contenedor y no el formulario: Vue compila los
         hijos del elemento montado, así que una directiva puesta en él mismo
         —@submit.prevent, por ejemplo— nunca llegaría a aplicarse. --}}
    <div id="referencia-especial-app" data-vista='@json($vista)'>
    <form
        method="POST"
        action="{{ route('persona.referencia.especial.store') }}"
        class="refesp-form"
        @submit.prevent="abrirConfirmacion">
        @csrf

        <div class="referencia-tarjeta">
            <h1>Datos para la expedición de la referencia especial</h1>
            <p class="referencia-muted">
                El pago debe provenir de la cuenta de quien aquí se registra: el comprobante fiscal se
                emite a su nombre y no puede facturarse a terceros.
            </p>

            <div class="refesp-grid">
                <div class="refesp-campo refesp-campo--ancho">
                    <label for="razon_social">Nombre / razón social del tercero que realizará el pago *</label>
                    <input
                        id="razon_social"
                        name="razon_social"
                        type="text"
                        maxlength="35"
                        required
                        placeholder="Ingrese nombre o razón social"
                        v-model="pagador.razonSocial">
                </div>

                <div class="refesp-campo">
                    <label for="persona_moral">Tipo de persona *</label>
                    <select id="persona_moral" name="persona_moral" required v-model="pagador.personaMoral">
                        <option value="1">Persona moral</option>
                        <option value="0">Persona física</option>
                    </select>
                </div>

                <div class="refesp-campo">
                    <label for="rfc">RFC *</label>
                    {{-- text y no number: la homoclave lleva letras. --}}
                    <input
                        id="rfc"
                        name="rfc"
                        type="text"
                        minlength="12"
                        maxlength="13"
                        required
                        v-model="pagador.rfc"
                        @input="normalizarRfc"
                        aria-describedby="rfc-ayuda">
                    <p id="rfc-ayuda" class="refesp-ayuda">12 caracteres si es persona moral, 13 si es física.</p>
                    <p class="refesp-error" role="alert" v-if="avisoRfc" v-cloak>@{{ avisoRfc }}</p>
                </div>

                <div class="refesp-campo">
                    <label for="regimen_fiscal">Régimen fiscal *</label>
                    <select id="regimen_fiscal" name="regimen_fiscal" required v-model="pagador.regimenFiscal">
                        <option value="">Selecciona una opción</option>
                        @foreach($regimenes as $regimen)
                            <option value="{{ $regimen['id'] }}">{{ $regimen['nombre'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="refesp-campo">
                    <label for="codigo_postal">Código postal *</label>
                    {{-- text y no number: un código como 01000 perdería el cero. --}}
                    <input
                        id="codigo_postal"
                        name="codigo_postal"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]{5}"
                        maxlength="5"
                        required
                        v-model="pagador.codigoPostal"
                        @input="normalizarCodigoPostal">
                    <p class="refesp-error" role="alert" v-if="avisoCodigoPostal" v-cloak>@{{ avisoCodigoPostal }}</p>
                </div>
            </div>

            <h2 class="refesp-subtitulo" id="refesp-participantes-titulo">
                ¿A cuántas personas se les pagará la certificación?
            </h2>

            <div class="refesp-contador" v-cloak>
                <button
                    type="button"
                    class="refesp-contador__boton"
                    :disabled="participantes.length <= 1"
                    @click="quitarUltimo"
                    aria-label="Quitar el último participante">−</button>
                <output class="refesp-contador__valor" aria-live="polite">@{{ participantes.length }}</output>
                <button
                    type="button"
                    class="refesp-contador__boton"
                    :disabled="participantes.length >= maximo"
                    @click="agregar()"
                    aria-label="Agregar un participante">+</button>
            </div>

            <p class="refesp-error" role="alert" v-if="avisoDuplicados" v-cloak>@{{ avisoDuplicados }}</p>

            <div class="refesp-personas" v-cloak aria-labelledby="refesp-participantes-titulo">
                <fieldset class="refesp-persona" v-for="(persona, indice) in participantes" :key="indice">
                    <legend class="refesp-persona__legend">
                        Participante @{{ indice + 1 }}<span v-if="indice === 0"> (tú)</span>
                    </legend>

                    <div class="refesp-campo">
                        <label :for="'curp-' + indice">CURP *</label>
                        <input
                            :id="'curp-' + indice"
                            :name="'participantes[' + indice + '][curp]'"
                            type="text"
                            maxlength="18"
                            required
                            :readonly="indice === 0"
                            v-model="persona.curp"
                            @input="normalizarCurp(persona)">
                    </div>

                    <div class="refesp-campo">
                        <label :for="'nombre-' + indice">Nombre *</label>
                        <input
                            :id="'nombre-' + indice"
                            :name="'participantes[' + indice + '][nombre]'"
                            type="text"
                            maxlength="45"
                            required
                            :readonly="indice === 0"
                            v-model="persona.nombre">
                    </div>

                    <div class="refesp-campo">
                        <label :for="'paterno-' + indice">Primer apellido *</label>
                        <input
                            :id="'paterno-' + indice"
                            :name="'participantes[' + indice + '][primer_apellido]'"
                            type="text"
                            maxlength="45"
                            required
                            :readonly="indice === 0"
                            v-model="persona.primer_apellido">
                    </div>

                    <div class="refesp-campo">
                        <label :for="'materno-' + indice">Segundo apellido *</label>
                        <input
                            :id="'materno-' + indice"
                            :name="'participantes[' + indice + '][segundo_apellido]'"
                            type="text"
                            maxlength="45"
                            required
                            :readonly="indice === 0"
                            v-model="persona.segundo_apellido">
                    </div>

                    <button
                        type="button"
                        class="refesp-persona__quitar"
                        v-if="indice > 0"
                        @click="quitar(indice)"
                        :aria-label="'Quitar al participante ' + (indice + 1)">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>

                    <p class="refesp-error" role="alert" v-if="avisoPersona(persona)">@{{ avisoPersona(persona) }}</p>
                </fieldset>

                <div class="refesp-agregar">
                    <label class="refesp-agregar__label" for="refesp-nueva-curp">Agregar persona…</label>
                    <input
                        id="refesp-nueva-curp"
                        type="text"
                        maxlength="18"
                        placeholder="CURP del participante (opcional)"
                        v-model="nuevaCurp"
                        :disabled="participantes.length >= maximo"
                        @keydown.enter.prevent="agregar(nuevaCurp)">
                    <button
                        type="button"
                        class="refesp-agregar__boton"
                        :disabled="participantes.length >= maximo"
                        @click="agregar(nuevaCurp)"
                        aria-label="Agregar participante">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <p class="referencia-muted refesp-total" v-cloak>
                Total a pagar: <strong>$@{{ totalFormateado }} @{{ moneda }}</strong>
                (@{{ participantes.length }} × $@{{ cuotaFormateada }}).
                La Dirección emitirá la referencia por ese importe.
            </p>
        </div>

        <div class="refesp-barra">
            <a href="{{ route('persona.referencia.index') }}" class="referencia-boton referencia-boton--secundario">
                Volver
            </a>
            {{-- El botón nace habilitado y Vue lo apaga: si el script no carga,
                 el envío llega al servidor y ahí se rechaza con el mismo motivo. --}}
            <button type="submit" class="referencia-boton" :disabled="!puedeEnviar">Continuar</button>
        </div>

        <div class="refesp-modal" v-if="confirmando" v-cloak @keydown.esc="cerrarConfirmacion">
            <div class="refesp-modal__fondo" @click="cerrarConfirmacion"></div>
            <section
                class="refesp-modal__card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="refesp-modal-titulo"
                @keydown.tab="atraparFoco">
                <h2 id="refesp-modal-titulo">¿Está seguro de que los datos son correctos?</h2>
                <p>Una vez enviados los datos <strong>no se podrán corregir</strong>.</p>

                <dl class="refesp-resumen">
                    <div>
                        <dt>Empresa</dt>
                        <dd>@{{ pagador.razonSocial }}</dd>
                    </div>
                    <div>
                        <dt>Cantidad de participantes</dt>
                        <dd>@{{ participantes.length }}</dd>
                    </div>
                </dl>

                <ul class="refesp-resumen__curps">
                    <li v-for="(persona, indice) in participantes" :key="indice">@{{ persona.curp }}</li>
                </ul>

                <div class="refesp-modal__acciones">
                    <button
                        type="button"
                        class="referencia-boton referencia-boton--secundario"
                        ref="cancelar"
                        @click="cerrarConfirmacion">
                        Volver
                    </button>
                    <button type="button" class="referencia-boton" :disabled="enviando" @click="confirmar">
                        @{{ enviando ? 'Enviando…' : 'Confirmar' }}
                    </button>
                </div>
            </section>
        </div>
    </form>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset_versionado('assets/js/pages/persona-referencia-especial.js') }}"></script>
@endpush
