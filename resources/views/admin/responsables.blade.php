{{--
    Personal de la DEC que atiende los pagos. Su nombre va en «Atendido por»
    del formato de pago y se elige al generarlo, desde el expediente del pago.

    Reutiliza la bandeja de sedes, como administradores. No lleva filtros ni
    estadísticas: son unas cuantas personas.
--}}
@extends('layouts.admin')

@section('title', 'SUIF — Responsables de pago')

@section('styles')
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-preregistro.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-sedes.css') }}">
<link rel="stylesheet" href="{{ asset_versionado('assets/css/pages/admin-administradores.css') }}">
@endsection

@section('content')
<section class="admin-sedes" aria-labelledby="admin-responsables-titulo">
    <div class="admin-sedes-contenedor">
        <header class="admin-sedes-encabezado">
            <div>
                <h1 id="admin-responsables-titulo">Responsables de pago</h1>
                <p>Quienes atienden los pagos en la DEC. Su nombre aparece en «Atendido por» del formato de pago.</p>
            </div>
            <a class="admin-sedes-boton admin-sedes-boton--primario" href="{{ route('admin.responsables.create') }}">
                <span aria-hidden="true">+</span> Nuevo responsable
            </a>
        </header>

        <section class="admin-sedes-tarjeta admin-sedes-tabla-contenedor" aria-label="Lista de responsables">
            <div class="admin-sedes-tabla-responsive admin-tabla-bandeja">
                <table class="admin-sedes-tabla admin-sedes-tabla--centrada">
                    <thead>
                        <tr>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($responsables as $responsable)
                            <tr>
                                <td class="admin-sedes-tabla-nombre">{{ $responsable['nombre_completo'] }}</td>
                                <td>
                                    <span class="admin-sedes-estado admin-sedes-estado--{{ $responsable['activo'] ? 'con-cupo' : 'sin-cupo' }}">
                                        {{ $responsable['activo'] ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="admin-administradores-acciones">
                                        <a class="admin-sedes-editar" href="{{ route('admin.responsables.edit', $responsable['id']) }}">Editar</a>
                                        @unless($responsable['activo'])
                                            {{-- Reactivar no destruye nada: no pide confirmación. --}}
                                            <form method="POST" action="{{ route('admin.responsables.reactivar', $responsable['id']) }}">
                                                @csrf
                                                <button class="admin-administradores-reactivar" type="submit">Reactivar</button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="admin-sedes-vacio" role="status">
                                    Todavía no hay responsables. Da de alta a quien atiende los pagos para poder generar formatos.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="admin-sedes-navegacion">
            <back-navigation
                destino="{{ route('admin.dashboard') }}"
                etiqueta="Volver al dashboard"
                etiqueta-accesible="Volver al dashboard"></back-navigation>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset_versionado('assets/js/pages/admin-sedes.js') }}"></script>
@endsection
