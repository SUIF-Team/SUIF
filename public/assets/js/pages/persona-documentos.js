/*
    persona-documentos.js
    Pantalla de documentación de la persona.

    Cada carga era un POST con recarga completa: seis documentos costaban seis
    viajes de ida y vuelta, y en cada uno se perdía el desplazamiento y el
    contexto de lo que se estaba haciendo. Aquí el envío va por fetch y el
    servidor devuelve el estado completo de la pantalla ya recalculado, que se
    sustituye entero en lugar de parchar celda por celda —el mismo criterio del
    catálogo de sedes—: así la tabla no puede contar una historia distinta a la
    de la base de datos.

    Mejora progresiva: la plantilla de abajo espeja la tabla que pinta
    persona/documentos.blade.php, que es la que ve quien navega sin JavaScript y
    sigue enviando cada formulario a pulso. Al tocar una hay que tocar la otra.
*/
(function () {
    'use strict';

    var raiz = document.querySelector('#pr-documentos-app');

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.Alertas) {
        return;
    }

    var vistaInicial;

    try {
        vistaInicial = JSON.parse(raiz.dataset.vista);
    } catch (error) {
        /* Sin datos que montar se deja la tabla del servidor, que funciona. */
        return;
    }

    var LIMITE_BYTES = 1048576;

    var PLANTILLA = `
        <div>
            <alertas
                :mensaje="aviso.mensaje"
                :tipo="aviso.tipo"
                :clase="aviso.tipo === 'success' ? 'pr-alert' : 'pr-alert pr-error'"></alertas>

            <h1>Documentación requerida</h1>
            <p class="pr-muted">Sube los documentos uno por uno. Cada PDF debe pesar máximo 1 MB.</p>
            <p class="pr-volver-formatos">
                <a :href="rutaFormatos">
                    <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                    Ver o descargar los formatos otra vez
                </a>
            </p>

            <div class="pr-tabla-envoltorio">
                <table class="pr-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Documento</th>
                            <th scope="col">Formato</th>
                            <th scope="col">Mi archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="doc in documentos" :key="doc.slug">
                            <tr class="pr-fila" :class="'pr-fila--' + doc.estado">
                                <td data-titulo="Documento">
                                    <strong class="pr-fila__nombre">{{ doc.nombre }}</strong>
                                    <span class="pr-status" :class="'pr-status--' + doc.clase">{{ doc.etiqueta }}</span>
                                </td>

                                <td data-titulo="Formato">
                                    <div class="pr-fila__acciones" v-if="doc.es_formato">
                                        <a class="pr-btn" :href="doc.ruta_formato">
                                            <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                                            <span>Generar</span>
                                        </a>
                                    </div>
                                    <span class="pr-format__nota" v-else>Documento personal</span>
                                </td>

                                <td data-titulo="Mi archivo">
                                    <div class="pr-fila__acciones">
                                        <a
                                            v-if="doc.tiene_archivo"
                                            class="pr-btn pr-btn--secondary"
                                            target="_blank"
                                            :href="doc.ruta_ver">
                                            <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                                            <span>Abrir</span>
                                        </a>

                                        <form
                                            v-if="doc.puede_reemplazar"
                                            method="POST"
                                            :action="doc.ruta_subir"
                                            enctype="multipart/form-data"
                                            class="pr-upload-form"
                                            @submit.prevent="subir(doc, $event)">
                                            <label class="pr-btn pr-file">
                                                <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                                <span>{{ etiquetaCarga(doc) }}</span>
                                                <input
                                                    type="file"
                                                    name="archivo"
                                                    accept="application/pdf"
                                                    required
                                                    :disabled="subiendo === doc.slug"
                                                    @change="elegirArchivo(doc, $event)">
                                            </label>
                                            <div class="pr-preview" :class="{ 'is-visible': !!elegidos[doc.slug] }">
                                                <span>{{ elegidos[doc.slug] ? elegidos[doc.slug].etiqueta : '' }}</span>
                                                <iframe
                                                    v-if="elegidos[doc.slug]"
                                                    title="Previsualización del archivo"
                                                    :src="elegidos[doc.slug].url"></iframe>
                                                <button
                                                    class="pr-btn"
                                                    type="submit"
                                                    :class="{ 'pr-btn--enviando': subiendo === doc.slug }"
                                                    :disabled="subiendo !== null">
                                                    {{ subiendo === doc.slug ? 'Subiendo…' : 'Confirmar carga' }}
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <small class="pr-fila__archivo" v-if="doc.nombre_original">{{ doc.nombre_original }}</small>
                                    <small class="pr-fila__archivo pr-error" v-if="errores[doc.slug]" role="alert">{{ errores[doc.slug] }}</small>
                                </td>
                            </tr>

                            <tr class="pr-fila-observacion" v-if="doc.observacion">
                                <td colspan="3">
                                    <div class="pr-observation">
                                        <strong>Motivo del rechazo</strong>
                                        <p>{{ doc.observacion }}</p>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <p class="pr-notice" v-if="fase === 'aprobado'">
                Tus documentos fueron aprobados. Espera la resolución de tu solicitud.
            </p>

            <p class="pr-notice pr-notice--enviado" role="status" v-else-if="fase === 'revision' && !solicitudCerrada">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>
                    Tus documentos fueron enviados a revisión{{ fechaEnvio ? ' el ' + fechaEnvio : '' }}.
                    Te avisaremos en cuanto el equipo administrativo los revise.
                </span>
            </p>

            <template v-else-if="fase !== 'rechazado' && !solicitudCerrada">
                <form
                    method="POST"
                    :action="rutaEnviar"
                    class="pr-actions"
                    @submit.prevent="abrirModal($event)">
                    <button type="submit" class="pr-btn" :disabled="enviando">Enviar a revisión</button>
                </form>

                <div class="pr-modal" v-if="modalAbierto" @keydown="alTeclearEnModal">
                    <div class="pr-modal__fondo" @click="cerrarModal"></div>
                    <section class="pr-modal__card" role="dialog" aria-modal="true"
                             aria-labelledby="pr-envio-titulo" aria-describedby="pr-envio-texto">
                        <h2 id="pr-envio-titulo">¿Enviar tus documentos a revisión?</h2>
                        <p id="pr-envio-texto">
                            Se {{ porEnviar === 1 ? 'enviará 1 documento' : 'enviarán ' + porEnviar + ' documentos' }}.
                            Después ya no podrás reemplazarlos hasta que el equipo administrativo termine de revisarlos.
                        </p>
                        <div class="pr-modal__acciones">
                            <button type="button" class="pr-btn pr-btn--secondary" ref="cancelar"
                                    :disabled="enviando" @click="cerrarModal">Cancelar</button>
                            <button type="button" class="pr-btn" :class="{ 'pr-btn--enviando': enviando }"
                                    :disabled="enviando" @click="confirmarEnvio">
                                {{ enviando ? 'Enviando…' : 'Sí, enviar' }}
                            </button>
                        </div>
                    </section>
                </div>
            </template>
        </div>
    `;

    /* Fuera del estado reactivo: son URL de objeto y un nodo del DOM, no datos
       que la plantilla tenga que observar. */
    var focoAnterior = null;
    var formularioPendiente = null;

    window.Vue.createApp({
        components: {
            alertas: window.SUIFComponentes.Alertas
        },
        template: PLANTILLA,
        data: function () {
            return {
                documentos: vistaInicial.documentos || [],
                fase: vistaInicial.fase,
                porEnviar: vistaInicial.por_enviar,
                fechaEnvio: vistaInicial.fecha_envio,
                solicitudCerrada: !!vistaInicial.solicitud_cerrada,
                rutaEnviar: vistaInicial.ruta_enviar,
                rutaFormatos: raiz.dataset.rutaFormatos || '',
                /* slug -> { etiqueta, url } del archivo elegido pero no enviado */
                elegidos: {},
                /* slug -> texto del último fallo de ese documento */
                errores: {},
                aviso: { mensaje: '', tipo: 'success' },
                subiendo: null,
                enviando: false,
                modalAbierto: false
            };
        },
        methods: {
            etiquetaCarga: function (doc) {
                if (doc.estado === 'rechazado') {
                    return 'Subsanar';
                }

                return doc.tiene_archivo ? 'Reemplazar' : 'Adjuntar';
            },

            /* ── Elegir el archivo ────────────────────────────────────────── */

            elegirArchivo: function (doc, evento) {
                var entrada = evento.target;
                var archivo = entrada.files && entrada.files[0];

                this.errores[doc.slug] = '';

                if (!archivo) {
                    this.olvidarElegido(doc.slug);

                    return;
                }

                /* El servidor vuelve a comprobar tipo y tamaño; esto sólo evita
                   subir un megabyte para que lo rechacen del otro lado. */
                if (archivo.type !== 'application/pdf' || archivo.size > LIMITE_BYTES) {
                    entrada.value = '';
                    this.olvidarElegido(doc.slug);
                    this.errores[doc.slug] = 'Selecciona un PDF de máximo 1 MB.';

                    return;
                }

                this.olvidarElegido(doc.slug);
                this.elegidos[doc.slug] = {
                    etiqueta: archivo.name + ' · ' + Math.ceil(archivo.size / 1024) + ' KB',
                    url: URL.createObjectURL(archivo)
                };
            },

            /* Sin revocar, cada previsualización dejaba su blob retenido hasta
               recargar; ahora que la pantalla ya no recarga, se acumularían. */
            olvidarElegido: function (slug) {
                if (this.elegidos[slug]) {
                    URL.revokeObjectURL(this.elegidos[slug].url);
                    delete this.elegidos[slug];
                }
            },

            /* ── Envíos ───────────────────────────────────────────────────── */

            subir: function (doc, evento) {
                if (this.subiendo !== null) {
                    return;
                }

                var formulario = evento.target;

                this.subiendo = doc.slug;
                this.errores[doc.slug] = '';

                window.SUIF.enviar(formulario).then(function (respuesta) {
                    this.subiendo = null;

                    if (!respuesta.ok) {
                        var porCampo = window.SUIF.errores(respuesta.datos);

                        this.errores[doc.slug] = porCampo.archivo
                            || porCampo.documentos
                            || window.SUIF.mensajeError(respuesta);

                        return;
                    }

                    this.olvidarElegido(doc.slug);
                    formulario.reset();
                    this.aplicar(respuesta.datos);
                }.bind(this));
            },

            abrirModal: function (evento) {
                formularioPendiente = evento.target;
                focoAnterior = document.activeElement;
                this.modalAbierto = true;

                /* Arranca el foco en Cancelar: enviar cierra la etapa. */
                this.$nextTick(function () {
                    if (this.$refs.cancelar) {
                        this.$refs.cancelar.focus();
                    }
                }.bind(this));
            },

            cerrarModal: function () {
                if (this.enviando) {
                    return;
                }

                this.modalAbierto = false;

                if (focoAnterior) {
                    focoAnterior.focus();
                    focoAnterior = null;
                }
            },

            confirmarEnvio: function () {
                if (this.enviando) {
                    return;
                }

                if (!formularioPendiente) {
                    return;
                }

                var formulario = formularioPendiente;

                this.enviando = true;

                window.SUIF.enviar(formulario).then(function (respuesta) {
                    this.enviando = false;
                    this.modalAbierto = false;

                    if (!respuesta.ok) {
                        this.aviso = { mensaje: window.SUIF.mensajeError(respuesta), tipo: 'error' };

                        return;
                    }

                    this.aplicar(respuesta.datos);
                }.bind(this));
            },

            /* ── Estado ───────────────────────────────────────────────────── */

            aplicar: function (datos) {
                var vista = datos.vista;

                this.aviso = { mensaje: datos.mensaje || '', tipo: datos.tipo || 'success' };

                if (!vista) {
                    return;
                }

                this.documentos = vista.documentos || [];
                this.fase = vista.fase;
                this.porEnviar = vista.por_enviar;
                this.fechaEnvio = vista.fecha_envio;
                this.solicitudCerrada = !!vista.solicitud_cerrada;
                this.errores = {};

                /* El aviso que trajo el servidor con la página pertenece a la
                   visita anterior: se retira para no dejar dos mensajes. */
                Array.prototype.forEach.call(
                    document.querySelectorAll('.pr-card > .pr-alert'),
                    function (nodo) { nodo.remove(); }
                );
            },

            alTeclearEnModal: function (evento) {
                if (evento.key === 'Escape') {
                    this.cerrarModal();

                    return;
                }

                if (evento.key !== 'Tab') {
                    return;
                }

                var enfocables = Array.prototype.slice.call(
                    evento.currentTarget.querySelectorAll('button:not([disabled])')
                );

                if (!enfocables.length) {
                    return;
                }

                var primero = enfocables[0];
                var ultimo = enfocables[enfocables.length - 1];

                if (evento.shiftKey && document.activeElement === primero) {
                    evento.preventDefault();
                    ultimo.focus();
                } else if (!evento.shiftKey && document.activeElement === ultimo) {
                    evento.preventDefault();
                    primero.focus();
                }
            }
        },
        mounted: function () {
            document.body.classList.remove('pr-modal-abierto');
        },
        watch: {
            /* La clase la lleva el <body>, que está fuera de la app. */
            modalAbierto: function (abierto) {
                document.body.classList.toggle('pr-modal-abierto', abierto);
            }
        }
    }).mount(raiz);
}());
