{{--
    partials/error-mensaje.blade.php
    Cuerpo compartido de las pantallas de error.
    Variables: $codigo, $titulo, $mensaje, $rutaAccion, $textoAccion.
--}}
<section class="error-seccion" aria-labelledby="error-titulo">
    <div class="error-tarjeta">
        <p class="error-codigo">Error {{ $codigo }}</p>
        <h1 id="error-titulo" class="error-titulo">{{ $titulo }}</h1>
        <p class="error-mensaje">{{ $mensaje }}</p>

        <div class="error-acciones">
            <a class="error-boton" href="{{ $rutaAccion }}">{{ $textoAccion }}</a>
        </div>

        <p class="error-soporte">
            ¿Necesitas ayuda? Escríbenos a
            <a href="mailto:{{ config('suif.soporte_correo') }}">{{ config('suif.soporte_correo') }}</a>
        </p>
    </div>
</section>