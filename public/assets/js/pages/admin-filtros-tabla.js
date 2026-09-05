/*
 * Filtrado en vivo de las bandejas administrativas que se pintan como tabla:
 * referencias, sedes, grupos, convocatorias y gestión de usuarios.
 *
 * Antes cada filtro era un submit del <form method="GET"> y la pantalla entera
 * se recargaba. Los datos ya estaban todos ahí —el servidor arma la colección
 * completa y la acota en memoria—, así que el viaje no traía nada nuevo: sólo
 * hacía perder el sitio, el foco y el desplazamiento.
 *
 * Aquí no se pinta ninguna fila: se ocultan y se muestran las que Blade ya
 * escribió, con su formato y sus rutas resueltas en PHP. El formulario sigue
 * intacto para quien no tenga JavaScript.
 *
 * Todo lo que este archivo sabe de cada pantalla se lo dicen los atributos:
 *
 *   <form data-filtros-tabla="id-de-la-tabla">   activa la mejora
 *   <input name="buscar">                        el name es la clave del filtro
 *   data-filtro-modo="contiene|igual|token"      opcional; por defecto contiene
 *                                                en los input e igual en los select
 *   data-filtro-orden                            ese control ordena, no filtra
 *   <a data-filtros-limpiar>                     el enlace de limpiar
 *   <tr data-filtro-buscar="...">                el valor que se compara
 *   <tr data-tabla-vacia>                        el renglón de «no se encontraron»
 */
(function () {
    'use strict';

    var formulario = document.querySelector('[data-filtros-tabla]');

    if (!formulario) {
        return;
    }

    /* La página pudo llegar ya filtrada por el servidor (un enlace compartido,
       o un envío sin JavaScript). En ese caso la tabla sólo trae una parte de
       la bandeja y filtrar aquí mentiría: se deja el formulario nativo, que es
       exactamente como se comportaba antes. */
    if (window.location.search) {
        return;
    }

    var tabla = document.getElementById(formulario.dataset.filtrosTabla);

    if (!tabla || !tabla.tBodies.length) {
        return;
    }

    var cuerpo = tabla.tBodies[0];
    var vacia = cuerpo.querySelector('[data-tabla-vacia]');
    var orden = formulario.querySelector('[data-filtro-orden]');

    /* El orden original es el que trajo el servidor: se guarda para poder
       volver a él sin recargar. */
    var filas = Array.prototype.slice.call(cuerpo.rows).filter(function (fila) {
        return fila !== vacia;
    });

    var filtros = Array.prototype.slice.call(formulario.querySelectorAll('[name]'))
        .filter(function (control) {
            return control !== orden;
        })
        .map(function (control) {
            return {
                control: control,
                clave: 'filtro' + control.name.charAt(0).toUpperCase() + control.name.slice(1),
                modo: control.dataset.filtroModo
                    || (control.tagName === 'SELECT' ? 'igual' : 'contiene')
            };
        });

    /* Mismo criterio que mb_stripos en el servidor: sin distinguir mayúsculas y
       sin quitar acentos, para que buscar aquí devuelva lo mismo que buscaba
       antes de que esto existiera. */
    function normalizar(valor) {
        return String(valor || '').trim().toLocaleLowerCase('es-MX');
    }

    function coincide(fila, filtro) {
        var buscado = filtro.control.value.trim();

        if (buscado === '') {
            return true;
        }

        var valor = fila.dataset[filtro.clave] || '';

        if (filtro.modo === 'igual') {
            return valor === buscado;
        }

        /* Una fila puede estar en varios estados a la vez —una referencia
           asignada y sin formato PDF—, así que ahí el atributo trae varias
           etiquetas separadas por espacio y se comparan enteras. */
        if (filtro.modo === 'token') {
            return valor.split(' ').indexOf(buscado) !== -1;
        }

        return normalizar(valor).indexOf(normalizar(buscado)) !== -1;
    }

    function aplicar() {
        var visibles = 0;

        filas.forEach(function (fila) {
            var pasa = filtros.every(function (filtro) {
                return coincide(fila, filtro);
            });

            fila.hidden = !pasa;

            if (pasa) {
                visibles += 1;
            }
        });

        if (vacia) {
            vacia.hidden = visibles > 0;
        }
    }

    /* appendChild mueve el renglón que ya está en el árbol, así que reinsertar
       en el orden deseado basta para reordenar la tabla. */
    function ordenar() {
        var ordenadas = orden.value === 'za' ? filas.slice().reverse() : filas;

        ordenadas.forEach(function (fila) {
            cuerpo.appendChild(fila);
        });

        if (vacia) {
            cuerpo.appendChild(vacia);
        }
    }

    var temporizador;

    filtros.forEach(function (filtro) {
        if (filtro.control.tagName === 'SELECT') {
            filtro.control.addEventListener('change', aplicar);
            return;
        }

        /* El select cambia de golpe; el texto se teclea. Repintar en cada tecla
           se nota cuando la tabla es larga, y el catálogo de referencias lo es. */
        filtro.control.addEventListener('input', function () {
            window.clearTimeout(temporizador);
            temporizador = window.setTimeout(aplicar, 120);
        });
    });

    if (orden) {
        orden.addEventListener('change', ordenar);
    }

    /* El botón de filtrar es el submit del respaldo sin JavaScript. Con la
       tabla acotándose al escribir ya no tiene nada que hacer, así que se
       oculta en lugar de dejar un botón que no cambia nada. */
    var enviar = formulario.querySelector('[type="submit"]');

    if (enviar) {
        enviar.hidden = true;
    }

    formulario.addEventListener('submit', function (evento) {
        evento.preventDefault();
        aplicar();
    });

    var limpiar = formulario.querySelector('[data-filtros-limpiar]');

    if (limpiar) {
        limpiar.addEventListener('click', function (evento) {
            evento.preventDefault();
            formulario.reset();

            if (orden) {
                ordenar();
            }

            aplicar();
        });
    }
}());
