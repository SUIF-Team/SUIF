(function () {
    'use strict';

    var root = document.querySelector('[data-preregistro-admin]');

    if (!root || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.BackNavigation) {
        return;
    }

    var datos_vista;

    try {
        datos_vista = JSON.parse(root.dataset.vista);
    } catch (error) {
        return;
    }

    window.Vue.createApp({
        components: {
            'back-navigation': window.SUIFComponentes.BackNavigation
        },
        data: function () {
            return {
                participante: datos_vista.participante,
                estados: datos_vista.estados,
                enviando: false,
                estados_documentos: datos_vista.decisiones || {},
                motivoRechazo: datos_vista.motivo_rechazo || '',
                interrumpiendo: false,
                documentoPrevisualizado: null,
                activadorDocumento: null
            };
        },
        computed: {
            iniciales: function () {
                return this.participante.nombre.charAt(0) + this.participante.primer_apellido.charAt(0);
            },
            nombreCompleto: function () {
                return [
                    this.participante.nombre,
                    this.participante.primer_apellido,
                    this.participante.segundo_apellido
                ].join(' ');
            },
            camposParticipante: function () {
                return [
                    { etiqueta: 'Nombre(s)', valor: this.participante.nombre },
                    { etiqueta: 'Primer Apellido', valor: this.participante.primer_apellido },
                    { etiqueta: 'Segundo Apellido', valor: this.participante.segundo_apellido },
                    { etiqueta: 'CURP', valor: this.participante.curp },
                    { etiqueta: 'Correo principal', valor: this.participante.correo_principal },
                    { etiqueta: 'Correo alterno', valor: this.participante.correo_alterno },
                    { etiqueta: 'Teléfono (celular)', valor: this.participante.telefono },
                    { etiqueta: 'Entidad federativa', valor: this.participante.entidad_federativa },
                    { etiqueta: 'Último grado de estudios', valor: this.participante.ultimo_grado_estudios },
                    { etiqueta: 'Actividad vulnerable', valor: this.participante.actividad_vulnerable },
                    { etiqueta: 'Persona responsable de su cumplimiento', valor: this.participante.responsable_cumplimiento }
                ];
            },
            claseEstadoGeneral: function () {
                if (this.estados.general === 'Aprobada') {
                    return 'admin-preregistro-estado--completado';
                }

                if (this.estados.general === 'Rechazada') {
                    return 'admin-preregistro-estado--rechazado';
                }

                return 'admin-preregistro-estado--revision';
            },
            pasoActual: function () {
                if (this.estados.preregistro === 'En revisión') {
                    return 'preregistro';
                }

                return this.estados.documentacion === 'En revisión'
                    ? 'documentacion'
                    : null;
            },
            hayDocumentosRechazados: function () {
                return Object.values(this.estados_documentos).includes('rechazado');
            },
            todosDocumentosResueltos: function () {
                var estados_documentos = this.estados_documentos;

                return this.participante.documentos.length > 0
                    && this.participante.documentos.every(function (documento) {
                        return ['aprobado', 'rechazado'].includes(estados_documentos[documento.id]);
                    });
            }
        },
        methods: {
            clasePaso: function (paso) {
                var estado = this.estados[paso];

                if (estado === 'Completado') {
                    return 'admin-preregistro-paso--completado';
                }

                if (estado === 'En revisión') {
                    return 'admin-preregistro-paso--actual';
                }

                if (estado === 'Rechazado') {
                    return 'admin-preregistro-paso--rechazado';
                }

                return 'admin-preregistro-paso--pendiente';
            },
            estadoDocumento: function (id) {
                return this.estados_documentos[id] || null;
            },
            actualizarDocumento: function (id, estado) {
                this.estados_documentos = Object.assign({}, this.estados_documentos, {
                    [id]: this.estadoDocumento(id) === estado ? null : estado
                });
            },
            abrirDocumento: function (documento, evento) {
                this.activadorDocumento = evento.currentTarget;
                this.documentoPrevisualizado = documento;

                this.$nextTick(function () {
                    this.$refs.botonCerrarVisor.focus();
                });
            },
            cerrarDocumento: function () {
                var activador = this.activadorDocumento;

                this.documentoPrevisualizado = null;
                this.activadorDocumento = null;

                this.$nextTick(function () {
                    if (activador) {
                        activador.focus();
                    }
                });
            }
        }
    }).mount(root);
}());
