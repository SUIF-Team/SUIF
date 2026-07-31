{{--
    partials/sidebar-progreso.blade.php
    Barra de avance del participante: calcula solo qué pasos ya completó.
--}}
<?php
    $estado = (array) session('suif.participante.estado', []);
    $pre = !empty($estado['preregistro_completo']);
    $ref = !empty($estado['referencia_generada']);
    $pagoEstado = isset($estado['pago_estado']) ? $estado['pago_estado'] : 'sin_cargar';
    $sede = !empty($estado['sede_seleccionada']);

    $pasos = [
        [
            'titulo' => 'Pre-registro',
            'subtitulo' => 'Captura de datos',
            'ruta' => 'participante.preregistro.index',
            'activo' => request()->routeIs('participante.preregistro.*'),
            'completo' => $pre,
        ],
        [
            'titulo' => 'Obtener referencia',
            'subtitulo' => '$'.number_format((float) config('suif.cuota_recuperacion', 7000), 2).' '.config('suif.moneda', 'MXN'),
            'ruta' => 'participante.referencia.index',
            'activo' => request()->routeIs('participante.referencia.*'),
            'completo' => $ref,
        ],
        [
            'titulo' => 'Pago',
            'subtitulo' => 'Ticket o CFDI',
            'ruta' => 'participante.pago.index',
            'activo' => request()->routeIs('participante.pago.*'),
            'completo' => $pagoEstado === 'validado',
        ],
        [
            'titulo' => 'Elegir sede',
            'subtitulo' => 'Sede y horario',
            'ruta' => 'participante.sede.index',
            'activo' => request()->routeIs('participante.sede.*'),
            'completo' => $sede,
        ],
    ];
?>
<nav class="progreso" aria-label="Avance del trámite">
    @foreach($pasos as $indice => $paso)
        <a href="{{ route($paso['ruta']) }}" class="progreso-paso @if($paso['activo']) progreso-paso--activo @elseif($paso['completo']) progreso-paso--completo @else progreso-paso--pendiente @endif">
            <span class="progreso-paso__numero">{{ $paso['completo'] ? '✓' : $indice + 1 }}</span>
            <span class="progreso-paso__texto">
                <strong>{{ $paso['titulo'] }}</strong>
                <small>{{ $paso['subtitulo'] }}</small>
            </span>
        </a>
    @endforeach
</nav>