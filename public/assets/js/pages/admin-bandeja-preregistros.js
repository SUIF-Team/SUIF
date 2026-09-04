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
                    estado: 'Todos',
                    orden: 'reciente'
                },
                filtros_aplicados: {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos',
                    orden: 'reciente'
                },
                persona_seleccionada: null,
                foco_restaurar: null
            };
        },
        computed: {
            personasFiltradas: function () {
                var filtros = this.filtros_aplicados;
                var termino = this.normalizar(filtros.termino);

                var visibles = this.personas.filter(function (registro) {
                    var coincide_termino = !termino || this.normalizar(registro[filtros.campo]).includes(termino);
                    var coincide_estado = filtros.estado === 'Todos' || registro[campo_estado] === filtros.estado;

                    return coincide_termino && coincide_estado;
                }, this);

                return this.ordenar(visibles, filtros.orden);
            }
        },
        methods: {
            filtrar: function () {
                this.filtros_aplicados = {
                    campo: this.filtros.campo,
                    termino: this.filtros.termino.trim(),
                    estado: this.filtros.estado,
                    orden: this.filtros.orden
                };
            },
            limpiar: function () {
                this.filtros = {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos',
                    orden: 'reciente'
                };
                this.filtros_aplicados = {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos',
                    orden: 'reciente'
                };
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
