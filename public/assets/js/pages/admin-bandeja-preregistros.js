(function () {
    'use strict';

    var root = document.querySelector('[data-bandeja-administrativa]');

    if (!root || !window.Vue || !window.SUIFComponentes
        || !window.SUIFComponentes.BackNavigation || !window.SUIFComponentes.Alertas) {
        return;
    }

    var datos_vista;

    try {
        datos_vista = JSON.parse(root.dataset.vista);
    } catch (error) {
        return;
    }

    var campo_estado = root.dataset.campoEstado || 'estado_bandeja';
    var campo_fecha = root.dataset.campoFecha || 'fecha_registro';
    var temporizador;

    window.Vue.createApp({
        components: {
            'back-navigation': window.SUIFComponentes.BackNavigation,
            alertas: window.SUIFComponentes.Alertas
        },
        data: function () {
            return {
                personas: datos_vista.personas || datos_vista.pagos || [],
                campoFecha: campo_fecha,
                filtros: {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos',
                    orden: 'reciente'
                },
                termino_aplicado: '',
                persona_seleccionada: null,
                foco_restaurar: null,
                aviso: { mensaje: '', tipo: 'success' },
                restaurando: false
            };
        },
        computed: {
            personasFiltradas: function () {
                var filtros = this.filtros;
                var termino = this.normalizar(this.termino_aplicado);

                var visibles = this.personas.filter(function (registro) {
                    var coincide_termino = !termino || this.normalizar(registro[filtros.campo]).includes(termino);
                    var coincide_estado = filtros.estado === 'Todos' || registro[campo_estado] === filtros.estado;

                    return coincide_termino && coincide_estado;
                }, this);

                return this.ordenar(visibles, filtros.orden);
            },
            /* Lo único que oye quien usa lector de pantalla cuando la lista se
               acota. La lista entera era la región viva y se releía completa;
               con el filtro aplicándose al escribir eso sería insoportable. */
            resumenResultados: function () {
                var total = this.personasFiltradas.length;

                return total === 1 ? '1 resultado' : total + ' resultados';
            }
        },
        /* Los select se aplican de golpe, pero el término no: repintar la lista
           en cada tecla se nota cuando la bandeja es larga, y ninguna de las
           tres está paginada.

           El temporizador lee el valor vigente al dispararse y no el que
           capturó el watch. Si se pulsa Limpiar dentro de esos 120 ms, un
           disparo tardío asigna la cadena vacía en lugar de reponer el término
           que se acaba de borrar. */
        watch: {
            'filtros.termino': function () {
                window.clearTimeout(temporizador);

                temporizador = window.setTimeout(function () {
                    this.termino_aplicado = this.filtros.termino;
                }.bind(this), 120);
            }
        },
        methods: {
            limpiar: function () {
                this.filtros = {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos',
                    orden: 'reciente'
                };
                this.termino_aplicado = '';
            },
            /* 'reciente' es el orden con el que llega la bandeja desde el
               servidor, así que devolver la lista tal cual ya es esa opción.
               El alfabético usa localeCompare con la configuración regional:
               ordenar con < dejaría a Ñ después de Z y a los acentuados al
               final. La lista que se ordena es la que devolvió filter(), un
               arreglo nuevo, así que ordenarla no altera el original. */
            ordenar: function (registros, orden) {
                if (orden !== 'az' && orden !== 'za') {
                    return registros;
                }

                var direccion = orden === 'az' ? 1 : -1;

                return registros.sort(function (uno, otro) {
                    return direccion * String(uno.nombre_completo || '').localeCompare(
                        String(otro.nombre_completo || ''),
                        'es-MX',
                        { sensitivity: 'base', numeric: true }
                    );
                });
            },
            normalizar: function (valor) {
                return String(valor || '').trim().toLocaleLowerCase('es-MX');
            },
            iniciales: function (persona) {
                if (persona.iniciales) {
                    return persona.iniciales;
                }

                return persona.nombre.charAt(0) + persona.primer_apellido.charAt(0);
            },
            fechaRegistro: function (fecha) {
                var fecha_registro = new Date(fecha.replace(' ', 'T'));

                return new Intl.DateTimeFormat('es-MX', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                }).format(fecha_registro);
            },
            claseEstado: function (registro) {
                return registro.clase_estado || '';
            },
            /* Confirmación para restaurar la clave. Solo la bandeja de
               personas registradas renderiza los botones que llaman aquí;
               en las demás bandejas estos métodos quedan sin uso. */
            abrirRestaurar: function (persona, evento) {
                this.persona_seleccionada = persona;
                this.foco_restaurar = evento ? evento.currentTarget : null;
                document.body.classList.add('admin-reversion-modal-abierto');

                this.$nextTick(function () {
                    if (this.$refs.cancelar_restaurar) {
                        this.$refs.cancelar_restaurar.focus();
                    }
                }.bind(this));
            },
            /*
             * Restaurar una clave se hace desde la bandeja y termina en la
             * bandeja: recargarla no aportaba nada y, cuando el correo no sale,
             * el aviso trae la única copia de la clave generada. Ahora se queda
             * a la vista y la lista no se mueve.
             */
            restaurar: function (evento) {
                if (this.restaurando) {
                    return;
                }

                this.restaurando = true;

                window.SUIF.enviar(evento.target).then(function (respuesta) {
                    this.restaurando = false;
                    this.cerrarRestaurar();

                    this.aviso = respuesta.ok
                        ? { mensaje: respuesta.datos.mensaje || '', tipo: respuesta.datos.tipo || 'success' }
                        : { mensaje: window.SUIF.mensajeError(respuesta), tipo: 'error' };
                }.bind(this));
            },

            cerrarRestaurar: function () {
                this.persona_seleccionada = null;
                document.body.classList.remove('admin-reversion-modal-abierto');

                if (this.foco_restaurar) {
                    this.foco_restaurar.focus();
                    this.foco_restaurar = null;
                }
            }
        }
    }).mount(root);
}());
