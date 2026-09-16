@php
    $prefijo_filtros = $prefijo_filtros ?? 'bandeja';
    $estados_filtro = $estados_filtro ?? [];
@endphp

{{-- La bandeja se acota mientras se escribe, así que no hay botón de filtrar:
     no quedaba nada que pulsar. El submit se sigue interceptando porque el
     formulario tiene un campo de texto y Enter lo enviaría igual. --}}
<section class="tarjeta" aria-label="Filtros de búsqueda">
    <form class="filtros" v-on:submit.prevent>
        <div class="campo admin-bandeja-preregistros-campo-tipo">
            <label class="etiqueta" for="{{ $prefijo_filtros }}-campo">Filtrar por</label>
            <select class="control" id="{{ $prefijo_filtros }}-campo" v-model="filtros.campo">
                <option value="nombre">Nombre(s)</option>
                <option value="primer_apellido">Apellido paterno</option>
                <option value="segundo_apellido">Apellido materno</option>
                <option value="curp">CURP</option>
            </select>
        </div>

        <div class="campo admin-bandeja-preregistros-campo-termino">
            <label class="etiqueta" for="{{ $prefijo_filtros }}-termino">Término de búsqueda</label>
            <input class="control" id="{{ $prefijo_filtros }}-termino" v-model="filtros.termino" type="search" placeholder="Escribe aquí tu búsqueda…" autocomplete="off">
        </div>

        <div class="campo admin-bandeja-preregistros-campo-estado">
            <label class="etiqueta" for="{{ $prefijo_filtros }}-estado">Estado</label>
            <select class="control" id="{{ $prefijo_filtros }}-estado" v-model="filtros.estado">
                @foreach ($estados_filtro as $estado_filtro)
                    <option value="{{ $estado_filtro }}">{{ $estado_filtro }}</option>
                @endforeach
            </select>
        </div>

        {{-- La bandeja sigue llegando con lo más reciente arriba, que es lo que
             sirve para atender lo que acaba de entrar. El alfabético es para
             buscar a una persona concreta, así que se elige, no se impone. --}}
        <div class="campo admin-bandeja-preregistros-campo-orden">
            <label class="etiqueta" for="{{ $prefijo_filtros }}-orden">Ordenar por</label>
            <select class="control" id="{{ $prefijo_filtros }}-orden" v-model="filtros.orden">
                <option value="reciente">Más reciente primero</option>
                <option value="az">Nombre (A-Z)</option>
                <option value="za">Nombre (Z-A)</option>
            </select>
        </div>

        <div class="admin-bandeja-preregistros-acciones-filtro">
            <button class="boton boton--peligro" type="button" v-on:click="limpiar">Limpiar</button>
        </div>
    </form>
</section>
