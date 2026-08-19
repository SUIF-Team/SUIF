(function () {
    'use strict';

    var root = document.querySelector('[data-sedes-participante]');
    if (!root) {
        return;
    }

    var intervalo;

    /**
     * Una sede aplica el examen en uno o más horarios. El cupo se controla por
     * horario, así que cada uno se actualiza por separado.
     */
    function actualizarHorario(horario) {
        var opcion = root.querySelector('[data-evaluacion-id="' + horario.evaluacion_id + '"]');
        if (!opcion) {
            return;
        }

        var disponible = opcion.querySelector('[data-cupo-disponible]');
        var estado = opcion.querySelector('[data-cupo-estado]');
        var etiqueta = opcion.querySelector('[data-cupo-etiqueta]');
        var radio = opcion.querySelector('[data-horario-opcion]');

        disponible.textContent = horario.disponibles;
        estado.classList.remove('sede-cupo--libre', 'sede-cupo--bajo', 'sede-cupo--lleno');
        estado.classList.add(!horario.con_cupo ? 'sede-cupo--lleno' : (horario.disponibles <= 15 ? 'sede-cupo--bajo' : 'sede-cupo--libre'));
        etiqueta.textContent = horario.con_cupo ? 'Lugares disponibles' : 'Sin cupo';
        radio.disabled = !horario.con_cupo;
        opcion.classList.toggle('sede-horario--lleno', !horario.con_cupo);

        if (radio.disabled && radio.checked) {
            radio.checked = false;
        }
    }

    function actualizarTarjeta(tarjeta) {
        var boton = tarjeta.querySelector('[data-seleccionar-sede]');
        if (!boton) {
            return;
        }

        var opciones = Array.from(tarjeta.querySelectorAll('[data-horario-opcion]'));
        var hayCupo = opciones.some(function (radio) {
            return !radio.disabled;
        });
        var elegido = opciones.some(function (radio) {
            return radio.checked && !radio.disabled;
        });

        boton.disabled = !elegido;
        boton.classList.toggle('sede-boton--deshabilitado', !elegido);
        boton.textContent = hayCupo ? 'Seleccionar horario' : 'Sin cupo';
    }

    function actualizarTarjetas() {
        root.querySelectorAll('[data-sede-tarjeta]').forEach(actualizarTarjeta);
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
            var horarios = datos.sedes || [];
            var vigentes = horarios.map(function (horario) {
                return String(horario.evaluacion_id);
            });

            root.querySelectorAll('[data-evaluacion-id]').forEach(function (opcion) {
                if (!vigentes.includes(opcion.dataset.evaluacionId)) {
                    actualizarHorario({
                        evaluacion_id: opcion.dataset.evaluacionId,
                        disponibles: 0,
                        con_cupo: false
                    });
                }
            });

            horarios.forEach(actualizarHorario);
            actualizarTarjetas();
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

    root.addEventListener('change', function (evento) {
        if (evento.target.matches('[data-horario-opcion]')) {
            actualizarTarjetas();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            detener();
        } else {
            iniciar();
        }
    });

    actualizarTarjetas();
    iniciar();
}());
