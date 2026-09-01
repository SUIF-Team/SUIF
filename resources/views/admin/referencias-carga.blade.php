{{--
    admin/referencias-carga.blade.php
    Carga del paquete ZIP con el catálogo de referencias bancarias (CSV) y sus
    formatos de pago (un PDF por referencia).
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
                <p>Carga en un solo archivo ZIP las referencias disponibles y los formatos con los que se paga en ventanilla.</p>
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
                <strong>Resultado de la carga</strong>
                <ul>
                    <li>{{ $importacion['nuevas'] }} referencias nuevas.</li>
                    <li>{{ $importacion['actualizadas'] }} referencias actualizadas.</li>
                    <li>{{ $importacion['total'] }} referencias en total, todas con su formato PDF.</li>
                </ul>
            </div>
        @endif

        <section class="admin-referencias-estadisticas" aria-label="Estado del catálogo">
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Referencias cargadas</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['total']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Disponibles</h2>
                <p class="admin-referencias-estadistica--azul">{{ number_format($resumen['disponibles']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Listas para entregar</h2>
                <p class="admin-referencias-estadistica--verde">{{ number_format($resumen['entregables']) }}</p>
            </article>
            <article class="admin-referencias-tarjeta admin-referencias-estadistica">
                <h2>Con formato PDF</h2>
                <p class="admin-referencias-estadistica--naranja">{{ number_format($resumen['con_formato']) }}</p>
            </article>
        </section>

        <div class="admin-referencias-cargas">
            <section class="admin-referencias-tarjeta admin-referencias-carga" aria-labelledby="admin-referencias-paquete-titulo">
                <h2 id="admin-referencias-paquete-titulo">Paquete de referencias (ZIP)</h2>
                <p>
                    Un solo comprimido con el catálogo y los formatos que la persona imprime para
                    pagar en ventanilla. Se carga completo o no se carga: así ninguna referencia
                    queda sin su PDF.
                </p>

                <dl class="admin-referencias-formato">
                    <div>
                        <dt>Qué va dentro</dt>
                        <dd>
                            El archivo CSV del catálogo y un PDF por cada referencia. No importa si
                            están en carpetas.
                        </dd>
                    </div>
                    <div>
                        <dt>Nombre de cada PDF</dt>
                        <dd>El número de referencia: <code>1234567890.pdf</code> es el formato de la referencia <code>1234567890</code>.</dd>
                    </div>
                    <div>
                        <dt>Columnas del CSV</dt>
                        <dd>
                            Las cuatro son obligatorias: <code>fecha</code> (de emisión),
                            <code>referencia</code>, <code>importe</code> y <code>vigencia</code>.
                            No recortes el archivo: los renglones del membrete institucional que van
                            arriba de la tabla se ignoran solos, y el separador puede ser coma o
                            punto y coma.
                        </dd>
                    </div>
                    <div>
                        <dt>Si algo no cuadra</dt>
                        <dd>
                            Si a una referencia le falta su PDF, si sobra un PDF que el CSV no
                            menciona o si el catálogo incluye una referencia ya entregada, no se
                            carga nada y el aviso te dice cuáles corregir.
                        </dd>
                    </div>
                    <div>
                        <dt>Tamaño</dt>
                        <dd>Hasta 50 MB por archivo ZIP.</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('admin.referencias.paquete.store') }}" enctype="multipart/form-data" class="admin-referencias-formulario">
                    @csrf
                    <label class="admin-referencias-archivo">
                        <span>Seleccionar ZIP</span>
                        <input type="file" name="paquete" accept=".zip,application/zip" required>
                    </label>
                    <button type="submit" class="admin-referencias-boton admin-referencias-boton--primario">Cargar referencias</button>
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
