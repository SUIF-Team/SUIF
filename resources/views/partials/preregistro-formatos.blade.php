{{--
    partials/preregistro-formatos.blade.php
    Lista de formatos requeridos del pre-registro.
    $soloConsulta = true cuando se abre desde la carga de documentos.
--}}
<h1>Formatos requeridos</h1>

<div class="pr-notice pr-notice--formatos">
    <p><strong>Estos son los documentos que necesitas para tu proceso.</strong></p>
    <ul>
        <li>Los formatos debes llenarlos <strong>a mano, con pluma y con letra legible</strong>.</li>
        <li>Puedes descargarlos <strong>las veces que necesites</strong>.</li>
        <li>La CURP y la identificación oficial son documentos tuyos: no hay formato que descargar, únicamente los adjuntas.</li>
    </ul>
</div>

<div class="pr-docs">
    @foreach($documentos as $slug => $nombre)
        <div class="pr-format">
            <strong>{{ $nombre }}</strong>
            @if(in_array($slug, $formatos))
                <a class="pr-btn pr-btn--secondary" target="_blank" href="{{ route('participante.preregistro.formatos.ver', $slug) }}">Previsualizar</a>
                <a class="pr-btn" href="{{ route('participante.preregistro.formatos.descargar', $slug) }}">Descargar</a>
            @else
                <span class="pr-format__nota">Documento personal · solo lo adjuntas</span>
            @endif
        </div>
    @endforeach
</div>

@if($soloConsulta)
    <div class="pr-actions">
        <a class="pr-btn" href="{{ route('participante.preregistro.index') }}">Volver a mis documentos</a>
    </div>
@else
    <form method="POST" action="{{ route('participante.preregistro.avanzar') }}" class="pr-actions">
        @csrf
        <button class="pr-btn">Continuar</button>
    </form>
@endif