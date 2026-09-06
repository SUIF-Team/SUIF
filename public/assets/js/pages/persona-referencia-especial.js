/*
 * Captura de la referencia especial: el tercero que paga y la lista de
 * participantes que cubre.
 *
 * La lista se arma y se deshace en pantalla, así que esta pantalla sí depende
 * de Vue —el resto de los formularios del trámite no—. Lo que se valida aquí
 * es un espejo de las reglas del servidor, que es quien decide: adelantar los
 * avisos evita que la persona capture veinte renglones para que se los
 * rechacen todos al enviar.
 *
 * De cada persona sólo se teclea la CURP: el nombre y los apellidos los trae el
 * servidor y los campos quedan de sólo lectura. Antes se capturaban a mano y se
 * comparaban contra la base al enviar, así que un acento de más echaba abajo
 * veinte renglones al final.
 *
 * El envío no tiene vuelta atrás: liga a todos los participantes al mismo
 * pago, así que media un diálogo de confirmación. Mismo patrón accesible que
 * la elección de comprobante fiscal.
 */
(function () {
    'use strict';

    var raiz = document.querySelector('#referencia-especial-app');

    if (!raiz || !window.Vue || !window.SUIFComponentes || !window.SUIFComponentes.Alertas) {
        return;
    }

    var vista;

    try {
        vista = JSON.parse(raiz.dataset.vista);
    } catch (error) {
        return;
    }

    var buscarUrl = raiz.dataset.buscarUrl;

    /* Espejo de las reglas del servidor. */
    var PATRON_RFC = /^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
    var PATRON_CURP = /^[A-Z0-9]{18}$/;
    /* Deliberadamente laxo: quien decide es la regla `email` del servidor. Aquí
       sólo se atrapa la errata evidente para no apagar el botón sin decir por
       qué. */
    var PATRON_CORREO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var MAXIMO_RAZON_SOCIAL = 35;

    /* Fuera del estado reactivo: son nodos del DOM, no datos que la plantilla
       tenga que observar. */
    var formularioPendiente = null;
    var focoAnterior = null;

    /* Teclear rápido dispara varias búsquedas y no vuelven en orden. Cada
       renglón se queda con el folio de la suya y descarta las respuestas
       viejas, para no pintar el nombre de una CURP que ya se corrigió. */
    var folioBusqueda = 0;

    function personaVacia() {
        return {
            curp: '',
            nombre: '',
            primer_apellido: '',
            segundo_apellido: '',
            /* '' | 'buscando' | 'encontrada' | 'sin-coincidencia' */
            estado: '',
            folio: 0
        };
    }

    window.Vue.createApp({
        components: {
            alertas: window.SUIFComponentes.Alertas
        },
        data: function () {
            return {
                pagador: {
                    razonSocial: vista.pagador.razonSocial || '',
                    personaMoral: vista.pagador.personaMoral || '1',
                    regimenFiscal: vista.pagador.regimenFiscal || '',
                    codigoPostal: vista.pagador.codigoPostal || '',
                    rfc: vista.pagador.rfc || '',
                    correoCfdi: vista.pagador.correoCfdi || ''
                },
                participantes: (vista.participantes || []).map(function (persona) {
                    return {
                        curp: (persona.curp || '').toUpperCase(),
                        nombre: persona.nombre || '',
                        primer_apellido: persona.primer_apellido || '',
                        segundo_apellido: persona.segundo_apellido || '',
                        /* Lo que llega del servidor ya está resuelto: el
                           renglón de quien solicita y, si el envío rebotó, lo
                           que se había capturado. */
                        estado: persona.nombre ? 'encontrada' : '',
                        folio: 0
                    };
                }),
                minimo: vista.minimo || 2,
                maximo: vista.maximo || 50,
                cuota: vista.cuota || 0,
                moneda: vista.moneda || 'MXN',
                confirmando: false,
                enviando: false,
                avisoError: '',
                /* {campo: mensaje} de lo que rechazó el servidor */
                erroresServidor: {}
            };
        },

        computed: {
            /* El RFC de una persona moral mide 12 y el de una física 13. */
            longitudRfc: function () {
                return this.pagador.personaMoral === '1' ? 12 : 13;
            },

            avisoRfc: function () {
                if (!this.pagador.rfc) {
                    return null;
                }

                if (this.pagador.rfc.length !== this.longitudRfc) {
                    return 'El RFC de una persona moral tiene 12 caracteres y el de una persona física 13.';
                }

                if (!PATRON_RFC.test(this.pagador.rfc)) {
                    return 'Escribe el RFC con homoclave, como aparece en la constancia de situación fiscal.';
                }

                return null;
            },

            avisoCodigoPostal: function () {
                if (!this.pagador.codigoPostal) {
                    return null;
                }

                return /^[0-9]{5}$/.test(this.pagador.codigoPostal)
                    ? null
                    : 'El código postal debe tener exactamente 5 dígitos.';
            },

            avisoCorreo: function () {
                if (!this.pagador.correoCfdi) {
                    return null;
                }

                return PATRON_CORREO.test(this.pagador.correoCfdi)
                    ? null
                    : 'Escribe un correo válido.';
            },

            /* La CURP repetida se avisa aquí y no por renglón: el error es del
               par, no de uno de los dos. */
            avisoDuplicados: function () {
                var vistas = {};
                var repetida = null;

                this.participantes.forEach(function (persona) {
                    if (!persona.curp) {
                        return;
                    }

                    if (vistas[persona.curp]) {
                        repetida = persona.curp;
                    }

                    vistas[persona.curp] = true;
                });

                return repetida ? 'La CURP ' + repetida + ' está repetida en la lista.' : null;
            },

            total: function () {
                return this.participantes.length * this.cuota;
            },

            totalFormateado: function () {
                return this.formatear(this.total);
            },

            cuotaFormateada: function () {
                return this.formatear(this.cuota);
            },

            puedeEnviar: function () {
                var pagadorCompleto = this.pagador.razonSocial
                    && this.pagador.razonSocial.length <= MAXIMO_RAZON_SOCIAL
                    && this.pagador.regimenFiscal
                    && this.pagador.codigoPostal
                    && this.pagador.rfc
                    && this.pagador.correoCfdi;

                if (!pagadorCompleto || this.avisoRfc || this.avisoCodigoPostal || this.avisoCorreo) {
                    return false;
                }

                if (this.participantes.length < this.minimo || this.participantes.length > this.maximo) {
                    return false;
                }

                if (this.avisoDuplicados) {
                    return false;
                }

                /* Un renglón con la consulta en vuelo no tiene aviso todavía
                   —no se le acusa un error mientras se busca—, así que sin este
                   corte el botón se encendería con los nombres aún vacíos. */
                if (this.participantes.some(function (persona) {
                    return persona.estado === 'buscando';
                })) {
                    return false;
                }

                return this.participantes.every(function (persona) {
                    return !this.avisoPersona(persona);
                }, this);
            }
        },

        methods: {
            formatear: function (cantidad) {
                return new Intl.NumberFormat('es-MX', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(cantidad);
            },

            /* El servidor los guarda en mayúsculas; verlos así mientras se
               escribe evita la sorpresa de que cambien al enviar. */
            normalizarRfc: function () {
                this.pagador.rfc = this.pagador.rfc.toUpperCase().replace(/\s+/g, '');
            },

            normalizarCodigoPostal: function () {
                this.pagador.codigoPostal = this.pagador.codigoPostal.replace(/[^0-9]/g, '').slice(0, 5);
            },

            /* El servidor lo guarda en minúsculas; espejo de FacturacionController. */
            normalizarCorreo: function () {
                this.pagador.correoCfdi = this.pagador.correoCfdi.toLowerCase().trim();
            },

            normalizarCurp: function (persona) {
                persona.curp = persona.curp.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 18);
                this.buscarPersona(persona);
            },

            /* El nombre no se teclea: se trae. Mientras la CURP esté incompleta
               los tres campos van vacíos, así que puedeEnviar los rechaza y no
               hay forma de mandar el nombre de una CURP anterior. */
            buscarPersona: function (persona) {
                if (!PATRON_CURP.test(persona.curp)) {
                    this.limpiarPersona(persona, '');

                    return;
                }

                var folio = ++folioBusqueda;
                var curp = persona.curp;

                persona.folio = folio;
                persona.estado = 'buscando';

                window.fetch(buscarUrl + '?curp=' + encodeURIComponent(curp), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                }).then(function (respuesta) {
                    if (!respuesta.ok) {
                        throw new Error('No fue posible consultar la CURP.');
                    }

                    return respuesta.json();
                }).then(function (datos) {
                    /* Llegó tarde: la CURP ya cambió y este nombre es de otra. */
                    if (persona.folio !== folio) {
                        return;
                    }

                    if (!datos.encontrada) {
                        this.limpiarPersona(persona, 'sin-coincidencia');

                        return;
                    }

                    persona.nombre = datos.persona.nombre;
                    persona.primer_apellido = datos.persona.primer_apellido;
                    persona.segundo_apellido = datos.persona.segundo_apellido;
                    persona.estado = 'encontrada';
                }.bind(this)).catch(function () {
                    /* Un tropiezo de red se ve igual que una CURP que no aplica:
                       en los dos casos falta el nombre y el envío sigue cerrado.
                       El servidor vuelve a resolverla al confirmar. */
                    if (persona.folio === folio) {
                        this.limpiarPersona(persona, 'sin-coincidencia');
                    }
                }.bind(this));
            },

            limpiarPersona: function (persona, estado) {
                persona.nombre = '';
                persona.primer_apellido = '';
                persona.segundo_apellido = '';
                persona.estado = estado;
            },

            avisoPersona: function (persona) {
                if (!persona.curp) {
                    return 'Escribe la CURP de esta persona.';
                }

                if (!PATRON_CURP.test(persona.curp)) {
                    return 'La CURP debe tener 18 letras o números.';
                }

                /* Mientras se consulta no hay nada que corregir todavía. */
                if (persona.estado === 'buscando') {
                    return null;
                }

                if (!persona.nombre || !persona.primer_apellido || !persona.segundo_apellido) {
                    return 'Esa CURP no puede incluirse en tu referencia.';
                }

                return null;
            },

            agregar: function () {
                if (this.participantes.length >= this.maximo) {
                    return;
                }

                this.participantes.push(personaVacia());
            },

            /* El primer renglón es quien solicita la referencia y no se quita:
               el servidor exige que su CURP esté en la lista. */
            quitar: function (indice) {
                if (indice > 0) {
                    this.participantes.splice(indice, 1);
                }
            },

            quitarUltimo: function () {
                this.quitar(this.participantes.length - 1);
            },

            abrirConfirmacion: function (evento) {
                if (!this.puedeEnviar || this.enviando) {
                    return;
                }

                formularioPendiente = evento.target;
                focoAnterior = document.activeElement;
                this.confirmando = true;

                document.body.classList.add('refesp-modal-abierto');

                /* Arranca el foco en Volver: la acción de al lado no tiene
                   vuelta atrás. */
                this.$nextTick(function () {
                    if (this.$refs.cancelar) {
                        this.$refs.cancelar.focus();
                    }
                }.bind(this));
            },

            cerrarConfirmacion: function () {
                this.confirmando = false;
                formularioPendiente = null;
                document.body.classList.remove('refesp-modal-abierto');

                if (focoAnterior) {
                    focoAnterior.focus();
                    focoAnterior = null;
                }
            },

            confirmar: function () {
                if (!formularioPendiente || this.enviando) {
                    return;
                }

                var formulario = formularioPendiente;

                this.enviando = true;
                formularioPendiente = null;
                this.avisoError = '';
                this.erroresServidor = {};

                window.SUIF.enviarYSeguir(formulario).then(function (resultado) {
                    /* Solicitar lleva a la pantalla de la referencia, que es
                       otra: ahí sí se navega. */
                    if (resultado.navegando) {
                        return;
                    }

                    this.enviando = false;
                    this.cerrarConfirmacion();
                    this.avisoError = resultado.mensaje;
                    this.erroresServidor = resultado.errores;
                }.bind(this));
            },

            atraparFoco: function (evento) {
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
        }
    }).mount(raiz);
}());
