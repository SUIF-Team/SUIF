@php
    $prefijo_filtros = $prefijo_filtros ?? 'bandeja';
    $estados_filtro = $estados_filtro ?? [];
@endphp

{{-- La bandeja se acota mientras se escribe, así que no hay botón de filtrar:
     no quedaba nada que pulsar. El submit se sigue interceptando porque el
     formulario tiene un campo de texto y Enter lo enviaría igual. --}}
<section class="admin-bandeja-preregistros-tarjeta" aria-label="Filtros de búsqueda">
    <form class="admin-bandeja-preregistros-filtros" v-on:submit.prevent>
        <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-tipo">
            <label for="{{ $prefijo_filtros }}-campo">Filtrar por</label>
            <select id="{{ $prefijo_filtros }}-campo" v-model="filtros.campo">
                <option value="nombre">Nombre(s)</option>
                <option value="primer_apellido">Apellido paterno</option>
                <option value="segundo_apellido">Apellido materno</option>
                <option value="curp">CURP</option>
            </select>
        </div>

        <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-termino">
            <label for="{{ $prefijo_filtros }}-termino">Término de búsqueda</label>
            <input id="{{ $prefijo_filtros }}-termino" v-model="filtros.termino" type="search" placeholder="Escribe aquí tu búsqueda..." autocomplete="off">
        </div>

        <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-estado">
            <label for="{{ $prefijo_filtros }}-estado">Estado</label>
            <select id="{{ $prefijo_filtros }}-estado" v-model="filtros.estado">
                @foreach ($estados_filtro as $estado_filtro)
                    <option value="{{ $estado_filtro }}">{{ $estado_filtro }}</option>
                @endforeach
            </select>
        </div>

        {{-- La bandeja sigue llegando con lo más reciente arriba, que es lo que
             sirve para atender lo que acaba de entrar. El alfabético es para
             buscar a una persona concreta, así que se elige, no se impone. --}}
        <div class="admin-bandeja-preregistros-campo admin-bandeja-preregistros-campo-orden">
            <label for="{{ $prefijo_filtros }}-orden">Ordenar por</label>
            <select id="{{ $prefijo_filtros }}-orden" v-model="filtros.orden">
                <option value="reciente">Más reciente primero</option>
                <option value="az">Nombre (A-Z)</option>
                <option value="za">Nombre (Z-A)</option>
            </select>
        </div>

        <div class="admin-bandeja-preregistros-acciones-filtro">
            <button class="admin-bandeja-preregistros-boton admin-bandeja-preregistros-boton-limpiar" type="button" v-on:click="limpiar">Limpiar</button>
        </div>
    </form>
</section>
