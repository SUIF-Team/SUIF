@php
    $prefijo_filtros = $prefijo_filtros ?? 'bandeja';
    $estados_filtro = $estados_filtro ?? [];
@endphp

<section class="admin-bandeja-preregistros-tarjeta" aria-label="Filtros de búsqueda">
    <form class="admin-bandeja-preregistros-filtros" v-on:submit.prevent="filtrar">
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

        <div class="admin-bandeja-preregistros-acciones-filtro">
            <button class="admin-bandeja-preregistros-boton admin-bandeja-preregistros-boton-filtrar" type="submit">Filtrar</button>
            <button class="admin-bandeja-preregistros-boton admin-bandeja-preregistros-boton-limpiar" type="button" v-on:click="limpiar">Limpiar</button>
        </div>
    </form>
</section>
