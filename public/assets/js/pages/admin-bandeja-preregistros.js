(function () {
    'use strict';

    var root = document.querySelector('[data-bandeja-administrativa]');

    if (!root || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.BackNavigation) {
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

    window.Vue.createApp({
        components: {
            'back-navigation': window.SUIFComponentes.BackNavigation
        },
        data: function () {
            return {
                personas: datos_vista.personas || datos_vista.pagos || [],
                campoFecha: campo_fecha,
                filtros: {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos'
                },
                filtros_aplicados: {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos'
                },
                persona_seleccionada: null,
                foco_restaurar: null
            };
        },
        computed: {
            personasFiltradas: function () {
                var filtros = this.filtros_aplicados;
                var termino = this.normalizar(filtros.termino);

                return this.personas.filter(function (registro) {
                    var coincide_termino = !termino || this.normalizar(registro[filtros.campo]).includes(termino);
                    var coincide_estado = filtros.estado === 'Todos' || registro[campo_estado] === filtros.estado;

                    return coincide_termino && coincide_estado;
                }, this);
            }
        },
        methods: {
            filtrar: function () {
                this.filtros_aplicados = {
                    campo: this.filtros.campo,
                    termino: this.filtros.termino.trim(),
                    estado: this.filtros.estado
                };
            },
            limpiar: function () {
                this.filtros = {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos'
                };
                this.filtros_aplicados = {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos'
                };
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
