@extends('layouts.admin')

@section('title', 'SUIF — Pre-registro')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/pages/admin-preregistro.css') }}">
@endsection

@section('content')
<section class="admin-preregistro-bandeja" aria-labelledby="bandeja-preregistro-titulo">
    <div class="admin-preregistro-contenedor">
        <header class="admin-preregistro-encabezado">
            <div>
                <p class="admin-preregistro-sobretitulo">Panel administrativo</p>
                <h1 id="bandeja-preregistro-titulo">Pre-registro</h1>
                <p>Participantes pendientes de revisión.</p>
            </div>
        </header>

        <section class="admin-preregistro-tarjeta" aria-label="Bandeja de participantes">
            <div class="admin-preregistro-lista">
                @foreach ($participantes as $participante)
                    <article class="admin-preregistro-participante">
                        <div class="admin-preregistro-participante-datos">
                            <span class="admin-preregistro-avatar" aria-hidden="true">{{ mb_substr($participante['nombre'], 0, 1) }}{{ mb_substr($participante['primer_apellido'], 0, 1) }}</span>
                            <div>
                                <h2>{{ $participante['nombre'] }} {{ $participante['primer_apellido'] }} {{ $participante['segundo_apellido'] }}</h2>
                                <p>Folio {{ $participante['folio'] }} · {{ $participante['entidad_federativa'] }}</p>
                            </div>
                        </div>
                        <div class="admin-preregistro-participante-acciones">
                            <span class="admin-preregistro-estado admin-preregistro-estado--revision">{{ $estados_iniciales['preregistro'] }}</span>
                            <a class="admin-preregistro-ver" href="{{ route('admin.participantes.show', ['id' => $participante['id']]) }}">Revisar</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</section>
@endsection
