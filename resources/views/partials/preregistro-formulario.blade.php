{{--
    partials/preregistro-formulario.blade.php
    Formulario de datos personales. Se usa para el alta y para la edición.
    Variables: $accion, $textoBoton, $mostrarCancelar, $avisoClave, $botonDeshabilitado,
    $mostrarAviso (aviso de privacidad y su casilla: solo en el alta).
--}}
<form method="POST" action="{{ $accion }}" id="pr-data-form">
    @csrf

    <p class="pr-notice pr-notice--bloqueo" role="note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>
            Verifica que tus datos sean correctos antes de continuar. Podrás
            editarlos mientras tu documentación no haya entrado a revisión;
            una vez que la envíes, tus datos quedan fijos y ya no se podrán
            modificar.
        </span>
    </p>

    <div class="pr-grid">
        <div class="pr-field"><label>Nombre *</label><input name="nombre" value="{{ old('nombre', $estado['datos']['nombre'] ?? '') }}" required></div>
        <div class="pr-field"><label>Primer apellido *</label><input name="primer_apellido" value="{{ old('primer_apellido', $estado['datos']['primer_apellido'] ?? '') }}" required></div>
        <div class="pr-field"><label>Segundo apellido *</label><input name="segundo_apellido" value="{{ old('segundo_apellido', $estado['datos']['segundo_apellido'] ?? '') }}" required></div>
        <div class="pr-field"><label>CURP *</label><input name="curp" maxlength="18" value="{{ old('curp', $estado['datos']['curp'] ?? '') }}" required></div>
        <div class="pr-field"><label>RFC *</label><input name="rfc" maxlength="13" pattern="[A-Za-zÑñ]{4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Za-z0-9]{3}" value="{{ old('rfc', $estado['datos']['rfc'] ?? '') }}" required></div>
        <div class="pr-field"><label>Correo principal *</label><input type="email" name="correo_principal" value="{{ old('correo_principal', $estado['datos']['correo_principal'] ?? '') }}" required></div>
        <div class="pr-field"><label>Teléfono celular *</label><input name="telefono" maxlength="10" pattern="[0-9]{10}" value="{{ old('telefono', $estado['datos']['telefono'] ?? '') }}" required></div>
        <div class="pr-field"><label>Entidad federativa *</label><select name="entidad_federativa" required><option value="">Selecciona una opción</option>@foreach($entidades as $entidad)<option value="{{ $entidad }}" {{ old('entidad_federativa', $estado['datos']['entidad_federativa'] ?? '') === $entidad ? 'selected' : '' }}>{{ $entidad }}</option>@endforeach</select></div>
        <div class="pr-field"><label>Correo alterno *</label><input type="email" name="correo_alterno" value="{{ old('correo_alterno', $estado['datos']['correo_alterno'] ?? '') }}" required></div>
        <div class="pr-field"><label>Último grado de estudios *</label><select name="grado_estudios" required><option value="">Selecciona una opción</option>@foreach($grados as $valor => $texto)<option value="{{ $valor }}" {{ old('grado_estudios', $estado['datos']['grado_estudios'] ?? '') === $valor ? 'selected' : '' }}>{{ $texto }}</option>@endforeach</select></div>
        <div class="pr-field"><label>¿Realiza actividades vulnerables? *</label><select name="actividad_vulnerable" required><option value="">Selecciona una opción</option><option value="si" {{ old('actividad_vulnerable', $estado['datos']['actividad_vulnerable'] ?? '') === 'si' ? 'selected' : '' }}>Sí</option><option value="no" {{ old('actividad_vulnerable', $estado['datos']['actividad_vulnerable'] ?? '') === 'no' ? 'selected' : '' }}>No</option></select></div>
        <div class="pr-field"><label>¿Es responsable de cumplimiento? *</label><select name="responsable_cumplimiento" required><option value="">Selecciona una opción</option><option value="si" {{ old('responsable_cumplimiento', $estado['datos']['responsable_cumplimiento'] ?? '') === 'si' ? 'selected' : '' }}>Sí</option><option value="no" {{ old('responsable_cumplimiento', $estado['datos']['responsable_cumplimiento'] ?? '') === 'no' ? 'selected' : '' }}>No</option></select></div>
    </div>

    @if($avisoClave)
        <p class="pr-notice">Al continuar, la clave de acceso se enviará al correo principal y podrás continuar el proceso desde la plataforma.</p>
    @endif

    @if($mostrarAviso)
        {{-- Artículo 20 de la LGPDPPSO: el aviso se pone a disposición antes
             de recabar los datos, no después. --}}
        <div class="pr-aviso">
            @include('partials.aviso-privacidad-simplificado')

            <label class="pr-aviso__casilla">
                <input type="checkbox" name="aviso_privacidad" value="1" required
                       {{ old('aviso_privacidad') ? 'checked' : '' }}>
                <span>He leído el aviso de privacidad, por cual, autorizo y acepto.</span>
            </label>
        </div>
    @endif

    <div class="pr-actions">
        @if($mostrarCancelar)
            <a class="pr-btn pr-btn--secondary" href="{{ route('persona.preregistro.index') }}">Cancelar</a>
        @endif
        <button class="pr-btn" type="submit" {{ $botonDeshabilitado ? 'disabled' : '' }}>{{ $textoBoton }}</button>
    </div>
</form>