{{--
    partials/pago-comprobante-fiscal.blade.php
    El comprobante que la persona pide de su pago: ticket o CFDI. Se muestra
    sólo cuando el pago quedó validado.

    Pedirlo no es obligatorio y la elección es definitiva, así que antes de
    guardarla se pide confirmación. Todo se pinta desde Blade: Vue únicamente
    intercepta el envío para abrir el diálogo, de modo que sin JavaScript el
    formulario se manda igual y el servidor valida lo mismo.

    Va un formulario por opción y el tipo viaja en un input oculto porque
    formulario.submit() no incluye el name/value del botón que envió.

    Parámetros:
    - $comprobanteFiscal: lo que arma PagoController::comprobanteFiscalVista().
--}}
<section
    id="comprobante-fiscal-app"
    class="pago-comprobante"
    data-vista='@json($comprobanteFiscal)'>

    @if(!$comprobanteFiscal['eleccion'])

        <h2 class="pago-comprobante__titulo">¿Quieres un comprobante de tu pago?</h2>

        <p class="pago-comprobante__nota" id="comprobante-fiscal-nota">
            Solicitar comprobante <strong>no es obligatorio</strong>. Si quieres, puedes generar
            un ticket o un CFDI; si no necesitas ninguno, puedes continuar con tu trámite sin
            elegir nada. La opción que elijas <strong>no se podrá modificar después</strong>.
        </p>

        <div class="pago-comprobante__opciones">
            <form
                method="POST"
                action="{{ route('persona.pago.tipo-comprobante') }}"
                class="pago-opcion"
                @submit.prevent="abrirConfirmacion('ticket', $event)">
                @csrf
                <input type="hidden" name="tipo" value="ticket">
                <h3 class="pago-opcion__titulo">Ticket</h3>
                <p class="pago-opcion__texto">Comprobante simple de tu pago, sin efectos fiscales.</p>
                <button type="submit" class="pago-boton" aria-describedby="comprobante-fiscal-nota">
                    Quiero ticket
                </button>
            </form>

            <form
                method="POST"
                action="{{ route('persona.pago.tipo-comprobante') }}"
                class="pago-opcion"
                @submit.prevent="abrirConfirmacion('cfdi', $event)">
                @csrf
                <input type="hidden" name="tipo" value="cfdi">
                <h3 class="pago-opcion__titulo">CFDI</h3>
                <p class="pago-opcion__texto">Factura con uso «gastos en general». Después tendrás que capturar tus datos fiscales.</p>
                <button type="submit" class="pago-boton" aria-describedby="comprobante-fiscal-nota">
                    Quiero CFDI
                </button>
            </form>
        </div>

        {{-- La elección no se puede deshacer, así que media un diálogo. Mismo
             patrón accesible que la confirmación de sede. --}}
        <div class="pago-modal" v-if="confirmacion" v-cloak @keydown.esc="cerrarConfirmacion">
            <div class="pago-modal__fondo" @click="cerrarConfirmacion"></div>
            <section
                class="pago-modal__card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="pago-modal-titulo"
                aria-describedby="pago-modal-descripcion"
                @keydown.tab="atraparFoco">
                <h2 id="pago-modal-titulo">¿Confirmas que quieres @{{ etiquetaConfirmacion }}?</h2>
                <p id="pago-modal-descripcion">
                    @{{ descripcionConfirmacion }}
                    <strong>Una vez confirmada, esta opción ya no podrá modificarse.</strong>
                </p>

                <div class="pago-modal__acciones">
                    <button
                        type="button"
                        class="pago-boton pago-boton--secundario"
                        ref="cancelar"
                        @click="cerrarConfirmacion">
                        Cancelar
                    </button>
                    <button type="button" class="pago-boton" :disabled="enviando" @click="confirmarEleccion">
                        @{{ enviando ? 'Confirmando…' : 'Sí, confirmar' }}
                    </button>
                </div>
            </section>
        </div>

    @else

        <h2 class="pago-comprobante__titulo">Comprobante solicitado</h2>

        <p class="pago-comprobante__elegido">
            Elegiste
            <span class="pago-chip pago-chip--{{ $comprobanteFiscal['eleccion'] }}">
                {{ $comprobanteFiscal['eleccion'] === 'cfdi' ? 'CFDI' : 'Ticket' }}
            </span>
        </p>

        <p class="pago-comprobante__aviso">Te lo haremos llegar por correo electrónico.</p>

        @if($comprobanteFiscal['eleccion'] === 'cfdi')
            @if($comprobanteFiscal['tieneDatosFiscales'])
                <p class="pago-comprobante__aviso">Tus datos de facturación ya quedaron registrados.</p>
            @else
                <a href="{{ $comprobanteFiscal['urlFormulario'] }}" class="pago-boton pago-boton--secundario">
                    Llenar formulario
                </a>
            @endif
        @endif

    @endif

</section>
