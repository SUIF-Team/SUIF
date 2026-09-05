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
            'back-navigation': window.SUIFComponentes.BackNavigation,
            alertas: window.SUIFComponentes.Alertas
        },
        data: function () {
            return {
                persona: datos_vista.persona,
                estados: datos_vista.estados,
                enviando: false,
                avisoError: '',
                /* {campo: mensaje} de lo que rechazo el servidor */
                erroresServidor: {},
                estados_documentos: datos_vista.decisiones || {},
                comentarios: Object.assign({}, datos_vista.comentarios || {}),
                erroresComentarios: datos_vista.errores_comentarios || {},
                fechaLimite: datos_vista.fecha_limite || '',
                motivoInterrupcion: datos_vista.motivo_interrupcion || '',
                interrupcionAbierta: Boolean(datos_vista.interrupcion_abierta),
                modoSoloLectura: datos_vista.modo_solo_lectura || false,
                documentoPrevisualizado: null,
                activadorDocumento: null
            };
        },
        computed: {
            iniciales: function () {
                return this.persona.nombre.charAt(0) + this.persona.primer_apellido.charAt(0);
            },
            /* Apellido paterno, materno y nombre(s): el mismo orden con el que
               las bandejas listan a la persona, para que el expediente se lea
               igual que el renglón desde el que se abrió. El filtro evita el
               espacio de más cuando no hay apellido materno. */
            nombreCompleto: function () {
                return [
                    this.persona.primer_apellido,
                    this.persona.segundo_apellido,
                    this.persona.nombre
                ].filter(function (parte) {
                    return String(parte || '').trim() !== '';
                }).join(' ');
            },
            camposPersona: function () {
                return [
                    { etiqueta: 'Nombre(s)', valor: this.persona.nombre },
                    { etiqueta: 'Primer Apellido', valor: this.persona.primer_apellido },
                    { etiqueta: 'Segundo Apellido', valor: this.persona.segundo_apellido },
                    { etiqueta: 'CURP', valor: this.persona.curp },
                    { etiqueta: 'RFC', valor: this.persona.rfc },
                    { etiqueta: 'Correo principal', valor: this.persona.correo_principal },
                    { etiqueta: 'Correo alterno', valor: this.persona.correo_alterno },
                    { etiqueta: 'Teléfono (celular)', valor: this.persona.telefono },
                    { etiqueta: 'Entidad federativa', valor: this.persona.entidad_federativa },
                    { etiqueta: 'Último grado de estudios', valor: this.persona.ultimo_grado_estudios },
                    { etiqueta: 'Actividad vulnerable', valor: this.persona.actividad_vulnerable },
                    { etiqueta: 'Persona responsable de su cumplimiento', valor: this.persona.responsable_cumplimiento }
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
            /**
             * Sólo se resuelven los documentos que esperan revisión; los
             * aprobados en una revisión anterior ya no se vuelven a decidir.
             */
            documentosPendientes: function () {
                return this.persona.documentos.filter(function (documento) {
                    return documento.pendiente;
                });
            },
            todosDocumentosResueltos: function () {
                var estados_documentos = this.estados_documentos;

                return this.documentosPendientes.length > 0
                    && this.documentosPendientes.every(function (documento) {
                        return ['aprobado', 'rechazado'].includes(estados_documentos[documento.id]);
                    });
            },
            comentariosCompletos: function () {
                var estados_documentos = this.estados_documentos;
                var comentarios = this.comentarios;

                return this.documentosPendientes.every(function (documento) {
                    if (estados_documentos[documento.id] !== 'rechazado') {
                        return true;
                    }

                    return String(comentarios[documento.id] || '').trim() !== '';
                });
            },
            motivoInterrupcionValido: function () {
                return this.motivoInterrupcion.trim() !== '';
            }
        },
        methods: {
            /*
             * El dictamen se guarda sin recargar.
             *
             * La pantalla lleva una decision y, en los rechazos, un comentario
             * escrito por cada documento, mas la fecha limite. Que el servidor
             * rechazara algo costaba recargar y volver a marcarlo todo: con
             * withInput() los campos regresaban, pero desde arriba de la
             * pantalla y sin el visor abierto donde estaba.
             *
             * "Guardar" e "Interrumpir" comparten formulario y se distinguen
             * por el formaction del boton, asi que el destino se lee de ahi.
             */
            enviar: function (evento) {
                if (this.enviando) {
                    return;
                }

                this.enviando = true;
                this.avisoError = '';
                this.erroresServidor = {};

                window.SUIF.enviarYSeguir(evento.target, {
                    url: window.SUIF.destinoDeEnvio(evento.target, evento)
                }).then(function (resultado) {
                    /* Resolver el expediente lleva a la pantalla de resultado,
                       que es otra: ahi si se navega. */
                    if (resultado.navegando) {
                        return;
                    }

                    this.enviando = false;
                    this.avisoError = resultado.mensaje;
                    this.erroresServidor = resultado.errores;
                }.bind(this));
            },

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
            errorComentario: function (id) {
                return this.erroresComentarios[id] || null;
            },
            /**
             * El motivo se captura al rechazar y se conserva visible, en sólo
             * lectura, mientras el documento siga rechazado.
             */
            mostrarComentario: function (documento) {
                return documento.pendiente
                    ? this.estadoDocumento(documento.id) === 'rechazado'
                    : documento.estado === 'Rechazado';
            },
            claseEstadoDocumento: function (estado) {
                if (estado === 'Aprobado') {
                    return 'admin-preregistro-documento-resuelto--aprobado';
                }

                if (estado === 'Rechazado') {
                    return 'admin-preregistro-documento-resuelto--rechazado';
                }

                return '';
            },
            actualizarDocumento: function (id, estado) {
                if (this.modoSoloLectura) {
                    return;
                }

                var siguiente = this.estadoDocumento(id) === estado ? null : estado;

                this.estados_documentos = Object.assign({}, this.estados_documentos, {
                    [id]: siguiente
                });

                if (siguiente !== 'rechazado') {
                    delete this.comentarios[id];
                    delete this.erroresComentarios[id];
                }
            },
            abrirInterrupcion: function () {
                this.interrupcionAbierta = true;

                this.$nextTick(function () {
                    if (this.$refs.motivoInterrupcion) {
                        this.$refs.motivoInterrupcion.focus();
                    }
                });
            },
            cerrarInterrupcion: function () {
                this.interrupcionAbierta = false;
                this.motivoInterrupcion = '';
            },
            abrirDocumento: function (documento, evento) {
                this.activadorDocumento = evento.currentTarget;
                this.documentoPrevisualizado = documento;
                document.body.classList.add('admin-preregistro-modal-abierto');

                this.$nextTick(function () {
                    this.$refs.botonCerrarVisor.focus();
                });
            },
            cerrarDocumento: function () {
                var activador = this.activadorDocumento;

                this.documentoPrevisualizado = null;
                this.activadorDocumento = null;
                document.body.classList.remove('admin-preregistro-modal-abierto');

                this.$nextTick(function () {
                    if (activador) {
                        activador.focus();
                    }
                });
            }
        }
    }).mount(root);
}());
