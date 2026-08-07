(function () {
    'use strict';

    var root = document.querySelector('[data-sedes-participante]');
    if (!root) {
        return;
    }

    var intervalo;

    function actualizarSede(sede) {
        var tarjeta = root.querySelector('[data-evaluacion-id="' + sede.evaluacion_id + '"]');
        if (!tarjeta) {
            return;
        }

        var disponible = tarjeta.querySelector('[data-cupo-disponible]');
        var estado = tarjeta.querySelector('[data-cupo-estado]');
        var etiqueta = tarjeta.querySelector('[data-cupo-etiqueta]');
        var boton = tarjeta.querySelector('[data-seleccionar-sede]');

        disponible.textContent = sede.disponibles;
        estado.classList.remove('sede-cupo--libre', 'sede-cupo--bajo', 'sede-cupo--lleno');
        estado.classList.add(!sede.con_cupo ? 'sede-cupo--lleno' : (sede.disponibles <= 15 ? 'sede-cupo--bajo' : 'sede-cupo--libre'));
        etiqueta.textContent = sede.con_cupo ? 'Lugares disponibles' : 'Sin cupo';
        boton.disabled = !sede.con_cupo;
        boton.textContent = sede.con_cupo ? 'Seleccionar' : 'Sin cupo';
        boton.classList.toggle('sede-boton--deshabilitado', !sede.con_cupo);
    }

    function consultar() {
        window.fetch(root.dataset.disponibilidadUrl, {
            headers: {
                Accept: 'application/json'
            },
            credentials: 'same-origin'
        }).then(function (respuesta) {
            if (!respuesta.ok) {
                throw new Error('No fue posible actualizar los cupos.');
            }
            return respuesta.json();
        }).then(function (datos) {
            var sedes = datos.sedes || [];
            var vigentes = sedes.map(function (sede) {
                return String(sede.evaluacion_id);
            });

            root.querySelectorAll('[data-evaluacion-id]').forEach(function (tarjeta) {
                if (!vigentes.includes(tarjeta.dataset.evaluacionId)) {
                    actualizarSede({
                        evaluacion_id: tarjeta.dataset.evaluacionId,
                        disponibles: 0,
                        con_cupo: false
                    });
                }
            });

            sedes.forEach(actualizarSede);
        }).catch(function () {
            // Se conserva el último estado visible; el POST vuelve a validar el cupo.
        });
    }

    function iniciar() {
        window.clearInterval(intervalo);
        consultar();
        intervalo = window.setInterval(consultar, 15000);
    }

    function detener() {
        window.clearInterval(intervalo);
        intervalo = null;
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            detener();
        } else {
            iniciar();
        }
    });

    iniciar();
}());
