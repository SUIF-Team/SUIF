{{--
    admin/reportes.blade.php
    Descarga de los reportes administrativos en Excel.

    Las tarjetas no son fijas: el controlador ya entregó sólo las que quien
    mira tiene permiso de descargar, así que aquí se recorren tal cual. Un
    Admin UIF y un Admin DEC ven pantallas distintas.

    Cada tarjeta es un formulario GET con su selector: no hay estado que
    guardar ni nada que validar en el navegador. Lo único que necesita Vue es
    el botón de regreso, que es un componente compartido.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Reportes')

@section('styles')
{{-- admin-preregistro.css es la hoja base de la zona administrativa: de ahí sale
     la barra que envuelve al componente <back-navigation>. --}}
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-reportes.css') }}">
@endsection

@section('content')
<section class="admin-reportes" aria-labelledby="admin-reportes-titulo">
    <div class="admin-reportes-contenedor">
        <header class="admin-reportes-encabezado">
            <div>
                <h1 id="admin-reportes-titulo">Reportes</h1>
                <p>Descarga la información del trámite en una hoja de cálculo para trabajarla fuera del sistema.</p>
            </div>
        </header>

        {{-- El error no se pinta aquí: layouts/admin ya incluye partials/alertas
             para toda la zona administrativa, y salía dos veces. --}}

        <div class="admin-reportes-lista">
            @foreach ($tarjetas as $tarjeta)
                <section class="tarjeta tarjeta--compacta admin-reportes-reporte"
                         aria-labelledby="admin-reportes-{{ $tarjeta['clave'] }}-titulo">
                    <div>
                        <h2 id="admin-reportes-{{ $tarjeta['clave'] }}-titulo">{{ $tarjeta['titulo'] }}</h2>
                        <p>{{ $tarjeta['descripcion'] }}</p>
                    </div>

                    @if (! in_array('grupo', $tarjeta['filtros'], true))
                        <form method="GET" action="{{ route($tarjeta['ruta']) }}" class="filtros admin-reportes-formulario">
                            @if (in_array('convocatoria', $tarjeta['filtros'], true))
                                <label class="campo admin-reportes-campo" for="convocatoria-{{ $tarjeta['clave'] }}">
                                    <span class="etiqueta">Convocatoria</span>
                                    <select class="control" id="convocatoria-{{ $tarjeta['clave'] }}" name="convocatoria">
                                        <option value="">Todas</option>
                                        @foreach ($convocatorias as $convocatoria)
                                            <option value="{{ $convocatoria['id'] }}">{{ $convocatoria['nombre'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            @if (in_array('estado', $tarjeta['filtros'], true))
                                <label class="campo admin-reportes-campo" for="estado-{{ $tarjeta['clave'] }}">
                                    <span class="etiqueta">Estado</span>
                                    <select class="control" id="estado-{{ $tarjeta['clave'] }}" name="estado">
                                        @foreach ($estados as $clave => $etiqueta)
                                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            @if (in_array('mes', $tarjeta['filtros'], true))
                                {{-- Un control nativo del navegador: entrega 'YYYY-MM' sin
                                     JavaScript ni catálogo de meses que mantener. Vacío trae
                                     todos los meses. --}}
                                <label class="campo admin-reportes-campo" for="mes-{{ $tarjeta['clave'] }}">
                                    <span class="etiqueta">Mes del pago</span>
                                    <input class="control" id="mes-{{ $tarjeta['clave'] }}" name="mes" type="month">
                                </label>
                            @endif

                            <button type="submit" class="boton boton--primario">
                                Descargar Excel
                            </button>
                        </form>
                    @else
                        {{-- La lista se entrega en dos formatos desde un mismo
                             formulario: el Excel para trabajarla y el PDF para
                             imprimirlo y recoger las firmas en la sede. Cada botón
                             lleva su propio formaction, así que no hace falta nada
                             de JavaScript para elegir el destino. --}}
                        <form method="GET" action="{{ route('admin.reportes.grupos') }}"
                              class="filtros admin-reportes-formulario">
                            <label class="campo admin-reportes-campo" for="grupo-{{ $tarjeta['clave'] }}">
                                <span class="etiqueta">Grupo</span>
                                <select class="control" id="grupo-{{ $tarjeta['clave'] }}" name="grupo" required>
                                    <option value="">Selecciona una aplicación</option>
                                    @foreach ($grupos as $grupo)
                                        <option value="{{ $grupo['id'] }}">
                                            {{ $grupo['sede_nombre'] }} · {{ $grupo['fecha_inicio'] }} · {{ $grupo['hora_inicio'] }}–{{ $grupo['hora_fin'] }} h ({{ $grupo['ocupados'] }} citadas)
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="admin-reportes-acciones">
                                <button type="submit" class="boton boton--primario"
                                        formaction="{{ route('admin.reportes.grupos') }}">
                                    Descargar Excel
                                </button>
                                <button type="submit" class="boton boton--secundario"
                                        formaction="{{ route('admin.reportes.grupos.lista') }}">
                                    Imprimir lista (PDF)
                                </button>
                            </div>
                        </form>
                    @endif
                </section>
            @endforeach
        </div>

        <div id="admin-reportes-navegacion">
            <back-navigation
                destino="{{ route('admin.dashboard') }}"
                etiqueta="Volver al dashboard"
                etiqueta-accesible="Volver al dashboard"></back-navigation>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-reportes.js') }}"></script>
@endsection
