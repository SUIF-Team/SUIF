{{--
    partials/preregistro-formatos.blade.php
    Lista de formatos requeridos del pre-registro.
--}}
<h1>Formatos requeridos</h1>

<div class="pr-notice pr-notice--formatos">
    <p><strong>Estos son los documentos que necesitas para tu proceso.</strong></p>
    <ul>
        <li>Los formatos se generan con tus datos ya escritos: solo debes <strong>imprimirlos y firmarlos a mano</strong>.</li>
        <li>Puedes generarlos <strong>las veces que necesites</strong>.</li>
        <li>La CURP y la identificación oficial son documentos tuyos: no hay formato que descargar, únicamente los adjuntas.</li>
    </ul>
</div>

<div class="pr-docs">
    @foreach($documentos as $slug => $nombre)
        <div class="pr-format">
            <strong>{{ $nombre }}</strong>
            @if(in_array($slug, $formatos))
                <a class="pr-btn" href="{{ route('persona.preregistro.formatos.generar', $slug) }}">Generar</a>
            @else
                <span class="pr-format__nota">Documento personal · solo lo adjuntas</span>
            @endif
        </div>
    @endforeach
</div>

<div class="pr-actions">
    <a class="pr-btn" href="{{ route('persona.documentos.index') }}">Volver a mis documentos</a>
</div>
