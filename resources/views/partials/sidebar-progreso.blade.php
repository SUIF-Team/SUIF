{{--
    partials/sidebar-progreso.blade.php
    Barra de avance del participante.
    Un paso solo es navegable si todos los anteriores están completos.
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
            'subtitulo' => 'Captura de datos y documentación',
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

    /* Se recorre en orden: en cuanto aparece un paso sin completar,
       ese es el último disponible y todos los siguientes quedan bloqueados. */
    $desbloqueado = true;
    foreach ($pasos as $indice => $paso) {
        $pasos[$indice]['disponible'] = $desbloqueado;
        if (!$paso['completo']) {
            $desbloqueado = false;
        }
    }
?>
<nav class="progreso" aria-label="Avance del trámite">
    @foreach($pasos as $indice => $paso)
        <?php
            $clases = 'progreso-paso';
            if ($paso['activo']) {
                $clases .= ' progreso-paso--activo';
            } elseif ($paso['completo']) {
                $clases .= ' progreso-paso--completo';
            } else {
                $clases .= ' progreso-paso--pendiente';
            }
            if (!$paso['disponible']) {
                $clases .= ' progreso-paso--bloqueado';
            }
        ?>

        @if($paso['disponible'])
            <a href="{{ route($paso['ruta']) }}" class="{{ $clases }}">
                <span class="progreso-paso__numero">{{ $paso['completo'] ? '✓' : $indice + 1 }}</span>
                <span class="progreso-paso__texto">
                    <strong>{{ $paso['titulo'] }}</strong>
                    <small>{{ $paso['subtitulo'] }}</small>
                </span>
            </a>
        @else
            <span class="{{ $clases }}" aria-disabled="true" title="Completa los pasos anteriores para continuar.">
                <span class="progreso-paso__numero" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                <span class="progreso-paso__texto">
                    <strong>{{ $paso['titulo'] }}</strong>
                    <small>{{ $paso['subtitulo'] }}</small>
                </span>
            </span>
        @endif
    @endforeach
</nav>