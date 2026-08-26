{{--
    Acciones que revierten una resolución ya notificada: reanudar un trámite o
    un pago resuelto y cancelar una solicitud.

    Recibe $acciones, un arreglo de ['id', 'ruta', 'etiqueta', 'titulo_modal',
    'texto_modal'] armado por App\Support\Admin\NotificacionResultado. Cada una
    confirma en su propio modal antes de enviar, porque deshacer una decisión
    que la persona ya vio no debería ocurrir por un clic de más.

    Se incluye FUERA de la raíz Vue de cada pantalla: así ninguna de las dos
    apps (admin-preregistro / admin-pago-detalle) lo compila como plantilla.

    El @can duplica el middleware de las rutas a propósito: quien no puede
    revertir tampoco debería ver los botones.
--}}
@can('reanudar-tramite')
@if(!empty($acciones))
    <section class="admin-preregistro-tarjeta admin-preregistro-reversion" aria-labelledby="acciones-reversion-titulo">
        <h2 id="acciones-reversion-titulo">Corregir la resolución</h2>
        <p class="admin-preregistro-reversion-ayuda">
            El historial conserva cada resolución: reanudar o cancelar agrega un movimiento nuevo, no borra el anterior.
        </p>

        <div class="admin-preregistro-reversion-acciones">
            @foreach($acciones as $accion)
                <button
                    class="admin-preregistro-boton admin-preregistro-boton--rechazar"
                    type="button"
                    data-abrir-reversion="{{ $accion['id'] }}">
                    {{ $accion['etiqueta'] }}
                </button>
            @endforeach
        </div>
    </section>

    @foreach($acciones as $accion)
        <div class="admin-reversion-modal" data-modal-reversion="{{ $accion['id'] }}" hidden>
            <div class="admin-reversion-modal-fondo" data-cerrar-reversion></div>
            <section
                class="admin-reversion-modal-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="{{ $accion['id'] }}-titulo"
                aria-describedby="{{ $accion['id'] }}-descripcion">
                <h2 id="{{ $accion['id'] }}-titulo">{{ $accion['titulo_modal'] }}</h2>
                <p id="{{ $accion['id'] }}-descripcion">{{ $accion['texto_modal'] }}</p>
                <form method="POST" action="{{ $accion['ruta'] }}" class="admin-reversion-modal-acciones">
                    @csrf
                    <button class="admin-preregistro-boton" type="button" data-cerrar-reversion>
                        Cancelar
                    </button>
                    <button class="admin-preregistro-boton admin-preregistro-boton--aceptar" type="submit">
                        Sí, continuar
                    </button>
                </form>
            </section>
        </div>
    @endforeach
@endif
@endcan
