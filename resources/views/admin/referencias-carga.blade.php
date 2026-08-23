{{--
    admin/referencias-carga.blade.php
    Carga del catálogo de referencias bancarias (CSV) y de sus formatos PDF (ZIP).
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Subir referencias bancarias')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-referencias.css') }}">
@endsection

@section('content')
<section class="admin-referencias" aria-labelledby="admin-referencias-carga-titulo">
    <div class="admin-referencias-contenedor">
        <header class="admin-referencias-encabezado">
            <div>
                <h1 id="admin-referencias-carga-titulo">Subir referencias bancarias</h1>
                <p>Carga el catálogo de referencias disponibles y los formatos con los que se paga en ventanilla.</p>
            </div>
            <a class="admin-referencias-boton admin-referencias-boton--secundario" href="{{ route('admin.referencias.index') }}">
                Ver catálogo
            </a>
        </header>

        @if($errors->any())
            <div class="admin-referencias-tarjeta admin-referencias-aviso admin-referencias-aviso--error">
                <strong>Revisa el archivo:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if($importacion)
            <div class="admin-referencias-tarjeta admin-referencias-aviso">
                @if($importacion['tipo'] === 'catalogo')
                    <strong>Resultado de la carga del catálogo</strong>
                    <ul>
                        <li>{{ $importacion['nuevas'] }} referencias nuevas.</li>
                        <li>{{ $importacion['actualizadas'] }} referencias actualizadas.</li>
                        <li>{{ $importacion['omitidas'] }} referencias omitidas.</li>
                    </ul>
                @else
                    <strong>Resultado de la extracción del ZIP</strong>
                    <ul>
                        <li>{{ $importacion['extraidos'] }} PDF extraídos.</li>
                        <li>{{ $importacion['ligados'] }} formatos ligados a una referencia.</li>
                        <li>{{ $importacion['sin_referencia'] }} archivos sin referencia en el catálogo.</li>
                    </ul>
                @endif

                @if(!empty($importacion['errores']))
                    <p class="admin-referencias-aviso-detalle">Observaciones:</p>
                    <ul>@foreach($importacion['errores'] as $detalle)<li>{{ $detalle }}</li>@endforeach</ul>
                @endif
            </div>
        @endif

        <section class="admin-referencias-estadisticas" aria-label="Estado del catálogo">
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Referencias cargadas</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['total']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Disponibles</h2>
                <p class="admin-referencias-estadistica--verde">{{ number_format($resumen['disponibles']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Con formato PDF</h2>
                <p class="admin-referencias-estadistica--naranja">{{ number_format($resumen['con_formato']) }}</p>
            </article>
        </section>

        <div class="admin-referencias-cargas">
            <section class="admin-referencias-tarjeta admin-referencias-carga" aria-labelledby="admin-referencias-csv-titulo">
                <h2 id="admin-referencias-csv-titulo">1. Catálogo de referencias (CSV)</h2>
                <p>Lista de las referencias que el sistema podrá asignar. Cada renglón se entrega a una sola persona.</p>

                <dl class="admin-referencias-formato">
                    <div>
                        <dt>Columnas</dt>
                        <dd>
                            Las cuatro son obligatorias: <code>fecha</code> (de emisión),
                            <code>referencia</code>, <code>importe</code> y <code>vigencia</code>.
                            Si falta alguna no se carga ninguna referencia, y el aviso te dice cuál.
                        </dd>
                    </div>
                    <div>
                        <dt>Membrete</dt>
                        <dd>
                            No recortes el archivo: los renglones del membrete institucional que van
                            arriba de la tabla se ignoran solos.
                        </dd>
                    </div>
                    <div>
                        <dt>Separador</dt>
                        <dd>Coma o punto y coma. Las fechas se aceptan como <code>AAAA-MM-DD</code> o <code>DD/MM/AAAA</code>.</dd>
                    </div>
                    <div>
                        <dt>Repeticiones</dt>
                        <dd>Volver a subir el mismo archivo no duplica: las referencias ya asignadas no se modifican.</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('admin.referencias.catalogo.store') }}" enctype="multipart/form-data" class="admin-referencias-formulario">
                    @csrf
                    <label class="admin-referencias-archivo">
                        <span>Seleccionar CSV</span>
                        <input type="file" name="catalogo" accept=".csv,text/csv" required>
                    </label>
                    <button type="submit" class="admin-referencias-boton admin-referencias-boton--primario">Cargar catálogo</button>
                </form>
            </section>

            <section class="admin-referencias-tarjeta admin-referencias-carga" aria-labelledby="admin-referencias-zip-titulo">
                <h2 id="admin-referencias-zip-titulo">2. Formatos para ventanilla (ZIP)</h2>
                <p>Comprimido con los PDF que la persona imprime para pagar de manera presencial. Los archivos se extraen automáticamente.</p>

                <dl class="admin-referencias-formato">
                    <div>
                        <dt>Nombre de cada PDF</dt>
                        <dd>El número de referencia: <code>1234567890.pdf</code> se liga a la referencia <code>1234567890</code>.</dd>
                    </div>
                    <div>
                        <dt>Orden</dt>
                        <dd>Sube primero el CSV: un PDF sin referencia en el catálogo no se liga a nadie.</dd>
                    </div>
                    <div>
                        <dt>Tamaño</dt>
                        <dd>Hasta 50 MB por archivo ZIP.</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('admin.referencias.formatos.store') }}" enctype="multipart/form-data" class="admin-referencias-formulario">
                    @csrf
                    <label class="admin-referencias-archivo">
                        <span>Seleccionar ZIP</span>
                        <input type="file" name="formatos" accept=".zip,application/zip" required>
                    </label>
                    <button type="submit" class="admin-referencias-boton admin-referencias-boton--primario">Cargar formatos</button>
                </form>
            </section>
        </div>

        <div id="admin-referencias-navegacion">
            <back-navigation
                destino="{{ route('admin.dashboard') }}"
                etiqueta="Volver al dashboard"
                etiqueta-accesible="Volver al dashboard"></back-navigation>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.41/dist/vue.global.prod.js"></script>
<script src="{{ asset_versionado('assets/js/components/BackNavigation.js') }}"></script>
<script src="{{ asset_versionado('assets/js/pages/admin-referencias.js') }}"></script>
@endsection
