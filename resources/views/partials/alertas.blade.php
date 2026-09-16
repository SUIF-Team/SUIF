{{--
    partials/alertas.blade.php
    Mensajes flash del panel: session('success'), session('error') y
    session('warning'). Lo incluye layouts/admin, así que sale encima de
    cualquier pantalla administrativa y necesita su propio ancho.

    Es la caja compartida del sistema, la misma que pintan las vistas de la
    persona y que devuelve Alertas.js; aquí sólo se elige el papel y el ícono.
--}}
@php
    $avisos = [
        ['clave' => 'success', 'papel' => 'exito', 'icono' => 'fa-circle-check', 'rol' => 'status'],
        ['clave' => 'error', 'papel' => 'error', 'icono' => 'fa-circle-exclamation', 'rol' => 'alert'],
        ['clave' => 'warning', 'papel' => 'advertencia', 'icono' => 'fa-circle-exclamation', 'rol' => 'alert'],
    ];
@endphp

@foreach($avisos as $aviso)
    @if(session($aviso['clave']))
        <div class="notificaciones-sistema">
            <div class="notificacion notificacion--{{ $aviso['papel'] }}" role="{{ $aviso['rol'] }}">
                <i class="fa-solid {{ $aviso['icono'] }} notificacion__icono" aria-hidden="true"></i>
                <span>{{ session($aviso['clave']) }}</span>
            </div>
        </div>
    @endif
@endforeach
