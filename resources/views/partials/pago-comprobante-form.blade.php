{{--
    partials/pago-comprobante-form.blade.php
    Formulario del paso de pago: los datos que la persona declara y el
    comprobante que los respalda. Lo usan tanto la carga inicial como la
    subsanación; sólo cambia la etiqueta del botón.

    Es una app Vue, pero aditiva: el formulario sigue siendo HTML real con
    required, min, max y step, así que sin JavaScript se envía igual y el
    servidor valida lo mismo. Por eso v-cloak va sólo en los avisos que no
    deben verse antes de montar, nunca en el formulario completo.

    Parámetros:
    - $etiquetaBoton: texto del botón de envío.
    - $vistaFormulario: estado inicial que arma PagoController (monto
      prellenado, lo capturado antes de un rechazo y la fecha máxima).
    - $cuota y $moneda: la cuota de recuperación, para el texto de ayuda.
--}}
<div
    id="pago-form-app"
    data-vista='@json($vistaFormulario)'>
    <form method="POST" action="{{ route('persona.pago.comprobante') }}" enctype="multipart/form-data" class="pago-form">
        @csrf

        <fieldset class="pago-datos">
            <legend class="pago-datos__titulo">Datos de tu pago</legend>

            <div class="pago-datos__grid">
                <div class="pago-campo">
                    <label for="monto_pagado">Monto pagado *</label>
                    <input
                        id="monto_pagado"
                        name="monto_pagado"
                        type="number"
                        step="0.01"
                        min="0.01"
                        max="999999"
                        inputmode="decimal"
                        required
                        v-model="montoPagado"
                        value="{{ $vistaFormulario['montoPagado'] }}"
                        aria-describedby="monto_pagado-ayuda">
                    <p id="monto_pagado-ayuda" class="pago-campo__ayuda">
                        Cuota de recuperación: ${{ $cuota }} {{ $moneda }}
                    </p>
                    @error('monto_pagado')
                        <p class="pago-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="pago-mensaje-validacion" role="alert" v-if="avisoMonto" v-cloak>@{{ avisoMonto }}</p>
                </div>

                <div class="pago-campo">
                    <label for="fecha_pago">Fecha de pago *</label>
                    <input
                        id="fecha_pago"
                        name="fecha_pago"
                        type="date"
                        max="{{ $vistaFormulario['maxFecha'] }}"
                        required
                        v-model="fechaPago"
                        value="{{ $vistaFormulario['fechaPago'] }}">
                    @error('fecha_pago')
                        <p class="pago-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="pago-mensaje-validacion" role="alert" v-if="avisoFecha" v-cloak>@{{ avisoFecha }}</p>
                </div>

                <div class="pago-campo">
                    <label for="hora_pago">Hora de pago *</label>
                    <input
                        id="hora_pago"
                        name="hora_pago"
                        type="time"
                        required
                        v-model="horaPago"
                        value="{{ $vistaFormulario['horaPago'] }}">
                    @error('hora_pago')
                        <p class="pago-mensaje-validacion" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </fieldset>

        {{-- Los pares v-if/v-else llevan v-cloak en la rama que no debe verse
             antes de montar: sin JavaScript se queda la variante inicial. --}}
        <label class="pago-archivo" :class="{ 'pago-archivo--cargado': archivo }">
            <i class="fa-solid fa-paperclip" aria-hidden="true" v-if="!archivo"></i>
            <i class="fa-solid fa-circle-check" aria-hidden="true" v-else v-cloak></i>
            <span v-if="!archivo">Seleccionar PDF</span>
            <span v-else v-cloak>Cambiar PDF</span>
            <input
                type="file"
                name="comprobante"
                accept="application/pdf"
                required
                ref="entradaArchivo"
                @change="elegirArchivo">
        </label>

        @error('comprobante')
            <p class="pago-mensaje-validacion" role="alert">{{ $message }}</p>
        @enderror

        {{-- La confirmación de lo adjuntado: el input va oculto y sin esto la
             pantalla no cambia al elegir un archivo. --}}
        <p class="pago-adjunto" :class="{ 'pago-adjunto--error': error }" v-if="archivo || error" v-cloak role="status" aria-live="polite">
            <i class="fa-solid" :class="error ? 'fa-circle-exclamation' : 'fa-circle-check'" aria-hidden="true"></i>
            <span class="pago-adjunto__detalle">
                <span v-if="error">@{{ error }}</span>
                <template v-else>
                    <span>Archivo adjunto:</span>
                    <strong>@{{ archivo.nombre }}</strong>
                    <span>· @{{ archivo.peso }}</span>
                </template>
            </span>
            <button type="button" class="pago-adjunto__quitar" v-if="archivo" @click="quitarArchivo">Quitar</button>
        </p>

        {{-- El botón nace habilitado en el HTML y se apaga desde aquí: si Vue no
             llega a cargar, el formulario se envía como siempre. --}}
        <button type="submit" class="pago-boton" :disabled="!puedeEnviar">{{ $etiquetaBoton }}</button>
    </form>
</div>
