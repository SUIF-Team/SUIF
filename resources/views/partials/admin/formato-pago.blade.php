{{--
    Tarjeta «Generar comprobante»: el formato de pago con que la DEC emite el
    CFDI o el ticket. Va en el detalle del pago y en la pantalla de resultado,
    entre el desenlace y «Corregir la resolución».

    Recibe $formato, armado por Admin\PagoController::opcionesFormato(), con
    ['ruta', 'motivo', 'responsables', 'sugerido']. Sin él la tarjeta no se
    pinta: la pantalla de resultado también la usa la documentación.

    Es un POST normal que Vue no intercepta: la respuesta es el archivo, así
    que el navegador lo descarga sin salir de la pantalla.
--}}
@if(!empty($formato))
    <section class="tarjeta admin-preregistro-detalle admin-pago-formato" aria-labelledby="formato-pago-titulo">
        <h2 id="formato-pago-titulo">Generar comprobante</h2>

        @if($formato['motivo'])
            <p class="atenuado">{{ $formato['motivo'] }}</p>
        @else
            @php
                $responsableElegido = (int) old('responsable', $formato['sugerido'] ?? 0);
            @endphp
            <form method="POST" action="{{ $formato['ruta'] }}" class="admin-pago-formato-formulario">
                @csrf
                <div class="campo admin-pago-formato-campo">
                    <label class="etiqueta" for="formato-responsable">Atendido por</label>
                    <select class="control" id="formato-responsable" name="responsable" required>
                        <option value="" disabled @selected($responsableElegido === 0)>Selecciona a quien atendió el pago</option>
                        @foreach($formato['responsables'] as $responsable)
                            <option value="{{ $responsable['id'] }}" @selected($responsable['id'] === $responsableElegido)>{{ $responsable['nombre_completo'] }}</option>
                        @endforeach
                    </select>
                    @error('responsable')
                        <p class="campo__error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <button class="boton boton--exito" type="submit">Descargar formato</button>
            </form>
        @endif
    </section>
@endif
