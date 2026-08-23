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

    /*
     * Confirmación antes de apartar el lugar. La selección no se puede
     * deshacer, así que el envío se detiene hasta que la persona acepta.
     * Si el modal no está en la página, el formulario se envía como siempre.
     */
    var modal = root.querySelector('[data-modal-confirmacion]');
    var formularioPendiente = null;
    var focoAnterior = null;

    function textoDe(elemento) {
        return elemento ? elemento.textContent.trim().replace(/\s+/g, ' ') : '';
    }

    function cerrarModal() {
        modal.hidden = true;
        document.body.classList.remove('sede-modal-abierto');
        formularioPendiente = null;

        if (focoAnterior) {
            focoAnterior.focus();
            focoAnterior = null;
        }

        iniciar();
    }

    function abrirModal(formulario, opcion) {
        var tarjeta = formulario.closest('[data-sede-tarjeta]');

        modal.querySelector('[data-confirmacion-sede]').textContent =
            textoDe(tarjeta.querySelector('.sede-tarjeta__nombre'));
        modal.querySelector('[data-confirmacion-fecha]').textContent =
            textoDe(opcion.querySelector('.sede-chip'));
        modal.querySelector('[data-confirmacion-horario]').textContent =
            textoDe(opcion.querySelector('.sede-fecha'));

        formularioPendiente = formulario;
        focoAnterior = document.activeElement;

        /* Con el diálogo abierto el sondeo dejaría de cuadrar con lo que la
           persona está leyendo; se reanuda al cerrarlo. */
        detener();

        modal.hidden = false;
        document.body.classList.add('sede-modal-abierto');

        /* El fondo también cierra, así que se pide el botón explícitamente.
           Arranca en Cancelar: la acción de al lado no tiene vuelta atrás. */
        modal.querySelector('button[data-cerrar-confirmacion]').focus();
    }

    if (modal) {
        root.addEventListener('submit', function (evento) {
            var formulario = evento.target;

            if (!formulario.matches('.sede-tarjeta__seleccion')) {
                return;
            }

            var opcion = formulario.querySelector('[data-horario-opcion]:checked');

            if (!opcion || opcion.disabled) {
                return;
            }

            evento.preventDefault();
            abrirModal(formulario, opcion.closest('[data-evaluacion-id]'));
        });

        modal.querySelectorAll('[data-cerrar-confirmacion]').forEach(function (boton) {
            boton.addEventListener('click', cerrarModal);
        });

        modal.querySelector('[data-confirmar-sede]').addEventListener('click', function (evento) {
            if (!formularioPendiente) {
                return;
            }

            var formulario = formularioPendiente;

            detener();
            formularioPendiente = null;

            /* El envío navega; se bloquea el botón por si alguien insiste. */
            evento.currentTarget.disabled = true;
            evento.currentTarget.textContent = 'Confirmando…';

            /* submit() no vuelve a disparar el evento, así que no se reabre. */
            formulario.submit();
        });

        modal.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrarModal();
                return;
            }

            if (evento.key !== 'Tab') {
                return;
            }

            var enfocables = Array.from(modal.querySelectorAll('button:not([disabled])'));
            var primero = enfocables[0];
            var ultimo = enfocables[enfocables.length - 1];

            if (evento.shiftKey && document.activeElement === primero) {
                evento.preventDefault();
                ultimo.focus();
            } else if (!evento.shiftKey && document.activeElement === ultimo) {
                evento.preventDefault();
                primero.focus();
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            detener();
            return;
        }

        /* Con el diálogo abierto el sondeo cambiaría la tarjeta que la persona
           está confirmando: se reanuda al cerrarlo. */
        if (!formularioPendiente) {
            iniciar();
        }
    });

    actualizarTarjetas();
    iniciar();
}());
