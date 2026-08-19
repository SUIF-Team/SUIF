{{--
    partials/pago-comprobante-form.blade.php
    Formulario para adjuntar el comprobante de pago. Lo usan tanto la carga
    inicial como la subsanación; sólo cambia la etiqueta del botón.

    Parámetros:
    - $etiquetaBoton: texto del botón de envío.
--}}
<form method="POST" action="{{ route('persona.pago.comprobante') }}" enctype="multipart/form-data" class="pago-form">
    @csrf

    <label class="pago-archivo" data-pago-archivo>
        <i class="fa-solid fa-paperclip" aria-hidden="true" data-pago-archivo-icono></i>
        <span data-pago-archivo-texto>Seleccionar PDF</span>
        <input type="file" name="comprobante" accept="application/pdf" required data-pago-archivo-input>
    </label>

    <button type="submit" class="pago-boton" data-pago-enviar>{{ $etiquetaBoton }}</button>

    {{-- La confirmación de lo adjuntado: el input va oculto y sin esto la
         pantalla no cambia al elegir un archivo. La llena persona-pago.js. --}}
    <p class="pago-adjunto" data-pago-adjunto hidden role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check" aria-hidden="true" data-pago-adjunto-icono></i>
        <span class="pago-adjunto__detalle">
            <span data-pago-adjunto-titulo>Archivo adjunto:</span>
            <strong data-pago-adjunto-nombre></strong>
            <span data-pago-adjunto-peso></span>
        </span>
        <button type="button" class="pago-adjunto__quitar" data-pago-adjunto-quitar>Quitar</button>
    </p>
</form>
