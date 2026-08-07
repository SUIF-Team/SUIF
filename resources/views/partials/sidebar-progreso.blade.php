{{--
    partials/sidebar-progreso.blade.php
    Barra de avance de la persona.
    Recibe $avance desde el view composer de AppServiceProvider.
    Un paso solo es navegable si todos los anteriores están completos.
--}}
<?php
    /* Pre-registro, documentación y pago salen de la base. */
    $sesion = (array) session('suif.persona.estado', []);
    $ref = $avance->tienePago();
    $pagoEstado = $avance->estadoPagoVista();
    $sede = !empty($sesion['sede_seleccionada']);
    $aprobada = $avance->solicitudAprobada();

    $pasos = [
        [
            'titulo' => 'Pre-registro',
            'subtitulo' => 'Captura de datos',
            'ruta' => 'persona.preregistro.index',
            'activo' => request()->routeIs('persona.preregistro.*'),
            'completo' => $avance->tieneSolicitud(),
            'requisito' => true,
        ],
                [
            'titulo' => 'Documentación',
            'subtitulo' => 'Adjuntar documentos',
            'ruta' => 'persona.documentos.index',
            'activo' => request()->routeIs('persona.documentos.*'),
            'completo' => $avance->documentacionEstado() === 'aprobado',
            'requisito' => true,
        ],
        [
            'titulo' => 'Obtener referencia',
            'subtitulo' => '$'.number_format((float) config('suif.cuota_recuperacion', 7000), 2).' '.config('suif.moneda', 'MXN'),
            'ruta' => 'persona.referencia.index',
            'activo' => request()->routeIs('persona.referencia.*'),
            'completo' => $aprobada && $ref,
            /* No basta con tener los documentos aprobados: el administrador
               tiene que aprobar la solicitud completa. */
            'requisito' => $aprobada,
        ],
        [
            'titulo' => 'Pago',
            'subtitulo' => 'Ticket o CFDI',
            'ruta' => 'persona.pago.index',
            'activo' => request()->routeIs('persona.pago.*'),
            'completo' => $pagoEstado === 'validado',
            'requisito' => true,
        ],
        [
            'titulo' => 'Elegir sede',
            'subtitulo' => 'Sede y horario',
            'ruta' => 'persona.sede.index',
            'activo' => request()->routeIs('persona.sede.*'),
            'completo' => $sede,
            'requisito' => true,
        ],
    ];

    /* Un paso se abre si todos los anteriores están completos Y se cumple
       su propio requisito. */
    $desbloqueado = true;
    foreach ($pasos as $indice => $paso) {
        $pasos[$indice]['disponible'] = $desbloqueado && $paso['requisito'];
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
