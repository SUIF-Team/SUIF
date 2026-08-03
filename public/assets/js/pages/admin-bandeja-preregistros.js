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

    var es_bandeja_pagos = root.dataset.bandejaAdministrativa === 'pagos';

    window.Vue.createApp({
        components: {
            'back-navigation': window.SUIFComponentes.BackNavigation
        },
        data: function () {
            return {
                participantes: es_bandeja_pagos ? datos_vista.pagos : datos_vista.participantes,
                filtros: es_bandeja_pagos ? {
                    termino: ''
                } : {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos'
                },
                filtros_aplicados: es_bandeja_pagos ? {
                    termino: ''
                } : {
                    campo: 'nombre',
                    termino: '',
                    estado: 'Todos'
                }
            };
        },
        computed: {
            participantesFiltrados: function () {
                var filtros = this.filtros_aplicados;
                var termino = this.normalizar(filtros.termino);

                if (es_bandeja_pagos) {
                    return this.participantes.filter(function (pago) {
                        return !termino || [
                            pago.nombre_completo,
                            pago.curp,
                            pago.folio,
                            pago.estatus
                        ].some(function (valor) {
                            return this.normalizar(valor).includes(termino);
                        }, this);
                    }, this);
                }

                return this.participantes.filter(function (participante) {
                    var coincide_termino = !termino || this.normalizar(participante[filtros.campo]).includes(termino);
                    var coincide_estado = filtros.estado === 'Todos' || participante.estado_bandeja === filtros.estado;

                    return coincide_termino && coincide_estado;
                }, this);
            }
        },
        methods: {
            filtrar: function () {
                if (es_bandeja_pagos) {
                    this.filtros_aplicados = {
                        termino: this.filtros.termino.trim()
                    };

                    return;
                }

                this.filtros_aplicados = {
                    campo: this.filtros.campo,
                    termino: this.filtros.termino.trim(),
                    estado: this.filtros.estado
                };
            },
            limpiar: function () {
                if (es_bandeja_pagos) {
                    this.filtros = {
                        termino: ''
                    };
                    this.filtros_aplicados = {
                        termino: ''
                    };

                    return;
                }

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
            iniciales: function (participante) {
                if (participante.iniciales) {
                    return participante.iniciales;
                }

                return participante.nombre.charAt(0) + participante.primer_apellido.charAt(0);
            },
            fechaRegistro: function (fecha) {
                var fecha_registro = new Date(fecha.replace(' ', 'T'));

                return new Intl.DateTimeFormat('es-MX', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                }).format(fecha_registro);
            },
            claseEstado: function (estado) {
                return {
                    'admin-bandeja-preregistros-estado-revision': estado === 'En revisión' || estado === 'Por revisar',
                    'admin-bandeja-preregistros-estado-aceptado': estado === 'Aceptado' || estado === 'Aprobado',
                    'admin-bandeja-preregistros-estado-rechazado': estado === 'Rechazado'
                };
            }
        }
    }).mount(root);
}());
