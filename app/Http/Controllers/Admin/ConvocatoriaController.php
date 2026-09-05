<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\GestionConvocatorias;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin\ConvocatoriaController
 *
 * Responsabilidad: alta, edición, baja y cambio de estado de las convocatorias
 * desde el panel administrativo.
 *
 * El formulario captura los datos de la convocatoria y nunca su estado: cerrar
 * o interrumpir es una decisión aparte, con su propia confirmación, porque deja
 * un movimiento en la bitácora que ya no se corrige. Por eso hay una acción
 * `estado` además del CRUD.
 */
class ConvocatoriaController extends Controller
{
    public function index(Request $request, GestionConvocatorias $gestion)
    {
        $filtros = $request->only(['buscar', 'estado']);
        $datos = $gestion->bandeja($filtros);

        return view('admin.convocatorias', [
            'convocatorias' => $datos['convocatorias'],
            'resumen' => $datos['resumen'],
            'filtros' => $filtros,
            'estados' => $gestion->estados(),
        ]);
    }

    /**
     * La convocatoria nace vigente y sólo puede haber una a la vez, así que con
     * otra abierta el formulario no tiene salida: store() la rechazaría después
     * de capturarla entera. La bandeja apaga el botón, pero la URL se puede
     * escribir a mano y el aviso tiene que darse aquí también.
     */
    public function create(GestionConvocatorias $gestion)
    {
        if ($gestion->bandeja()['resumen']['vigente'] !== null) {
            /* Abrir el formulario es una navegación, no una acción. */
            return redirect()
                ->route('admin.convocatorias.index')
                ->with('error', 'Ya hay una convocatoria vigente. Ciérrala o interrúmpela para poder crear otra.');
        }

        return view('admin.convocatoria-formulario', [
            'convocatoria' => null,
            'modoEdicion' => false,
        ]);
    }

    public function store(Request $request, GestionConvocatorias $gestion)
    {
        try {
            $gestion->crear($this->validar($request));
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'La convocatoria se creó correctamente y quedó vigente.',
            route('admin.convocatorias.index')
        );
    }

    public function edit(int $id, GestionConvocatorias $gestion)
    {
        try {
            $convocatoria = $gestion->convocatoria($id);
        } catch (DomainException $exception) {
            /* Abrir el formulario es una navegación, no una acción: si el
               registro ya no existe se vuelve al listado como siempre. */
            return redirect()
                ->route('admin.convocatorias.index')
                ->with('error', $exception->getMessage());
        }

        return view('admin.convocatoria-formulario', [
            'convocatoria' => $convocatoria,
            'modoEdicion' => true,
            'transiciones' => $this->transiciones($convocatoria['estado'], $gestion),
        ]);
    }

    public function update(Request $request, int $id, GestionConvocatorias $gestion)
    {
        try {
            $gestion->actualizar($id, $this->validar($request));
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'La convocatoria se actualizó correctamente.',
            route('admin.convocatorias.index')
        );
    }

    public function destroy(Request $request, int $id, GestionConvocatorias $gestion)
    {
        try {
            $gestion->eliminar($id);
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            'La convocatoria se eliminó correctamente.',
            route('admin.convocatorias.index')
        );
    }

    /**
     * Cerrar, interrumpir y reabrir son el mismo movimiento con distinto
     * destino, así que comparten ruta: tres acciones gemelas triplicarían el
     * controlador sin cambiar nada de lo que hacen.
     */
    public function estado(Request $request, int $id, GestionConvocatorias $gestion)
    {
        $datos = $request->validate([
            'estado' => ['required', 'string', Rule::in($gestion->estados())],
        ], [
            'estado.required' => 'Selecciona el estado al que pasa la convocatoria.',
            'estado.in' => 'Selecciona un estado válido de la convocatoria.',
        ]);

        try {
            $gestion->cambiarEstado($id, $datos['estado']);
        } catch (DomainException $exception) {
            return $this->responder($request, 'error', $exception->getMessage());
        }

        return $this->responder(
            $request,
            'success',
            "La convocatoria quedó en estado «{$datos['estado']}».",
            route('admin.convocatorias.index')
        );
    }

    /**
     * A qué estados puede pasar la convocatoria desde el que tiene, con el
     * verbo y el aviso de cada uno.
     *
     * Se calcula aquí y no en la vista porque es una decisión de qué se puede
     * hacer, no de cómo se pinta. El estado actual se excluye: pasar una
     * convocatoria al estado en que ya está no es un cambio, y el servicio lo
     * rechaza.
     *
     * @return array<int, array<string, string>>
     */
    private function transiciones(string $actual, GestionConvocatorias $gestion): array
    {
        $catalogo = [
            GestionConvocatorias::VIGENTE => [
                'verbo' => 'Marcar vigente',
                'clase' => 'primario',
                'aviso' => 'Volverá a admitir registro mientras su ventana de fechas siga abierta, y será la convocatoria a la que se sumen las solicitudes nuevas. Sólo puede haber una vigente a la vez.',
            ],
            GestionConvocatorias::CERRADA => [
                'verbo' => 'Cerrar',
                'clase' => 'secundario',
                'aviso' => 'Dejará de admitir registro aunque su ventana de fechas siga abierta. Es el cierre normal de una convocatoria que terminó su ciclo.',
            ],
            GestionConvocatorias::INTERRUMPIDA => [
                'verbo' => 'Interrumpir',
                'clase' => 'eliminar',
                'aviso' => 'Dejará de admitir registro de inmediato. Se usa cuando la convocatoria se detiene antes de tiempo; quien ya se registró conserva su trámite.',
            ],
        ];

        $transiciones = [];

        foreach ($gestion->estados() as $estado) {
            if ($estado === $actual) {
                continue;
            }

            $transiciones[] = ['estado' => $estado] + $catalogo[$estado];
        }

        return $transiciones;
    }

    /**
     * Las cinco fechas cuentan una sola historia y por eso se validan juntas:
     * cada una tiene que caber dentro de la que la contiene. Una convocatoria
     * cuyo registro cierra después de que ella misma terminó no es un dato
     * raro, es un dato imposible.
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:300'],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d'],
            'fecha_inicio_registro' => ['required', 'date_format:Y-m-d'],
            'fecha_fin_registro' => ['required', 'date_format:Y-m-d'],
            'fin_fecha_entrega_docs' => ['required', 'date_format:Y-m-d'],
        ], [
            'nombre.required' => 'Escribe el nombre de la convocatoria.',
            'nombre.max' => 'El nombre de la convocatoria no puede exceder 300 caracteres.',
            'monto.required' => 'Indica la cuota de recuperación.',
            'monto.numeric' => 'La cuota de recuperación debe ser una cantidad.',
            'monto.min' => 'La cuota de recuperación debe ser mayor que cero.',
            'monto.max' => 'La cuota de recuperación es demasiado alta.',
            'fecha_inicio.required' => 'Indica la fecha en que inicia la convocatoria.',
            'fecha_fin.required' => 'Indica la fecha en que termina la convocatoria.',
            'fecha_inicio_registro.required' => 'Indica la fecha en que abre el registro.',
            'fecha_fin_registro.required' => 'Indica la fecha en que cierra el registro.',
            'fin_fecha_entrega_docs.required' => 'Indica la fecha límite para entregar documentos.',
            'date_format' => 'Escribe una fecha válida.',
        ]);

        $datos['nombre'] = trim($datos['nombre']);

        $this->validarCalendario($datos);

        return $datos;
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function validarCalendario(array $datos): void
    {
        $dia = static fn (string $campo): Carbon => Carbon::createFromFormat(
            'Y-m-d',
            $datos[$campo],
            config('app.timezone')
        )->startOfDay();

        $inicio = $dia('fecha_inicio');
        $fin = $dia('fecha_fin');
        $abre_registro = $dia('fecha_inicio_registro');
        $cierra_registro = $dia('fecha_fin_registro');
        $entrega_docs = $dia('fin_fecha_entrega_docs');

        if ($fin->lessThan($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La convocatoria no puede terminar antes de comenzar.',
            ]);
        }

        if ($cierra_registro->lessThan($abre_registro)) {
            throw ValidationException::withMessages([
                'fecha_fin_registro' => 'El registro no puede cerrar antes de abrir.',
            ]);
        }

        if ($entrega_docs->lessThan($cierra_registro)) {
            throw ValidationException::withMessages([
                'fin_fecha_entrega_docs' => 'La entrega de documentos no puede vencer antes de que cierre el registro.',
            ]);
        }

        /* Las tres fechas intermedias viven dentro de la convocatoria: no se
           puede recibir un registro ni un documento cuando todavía no empezó o
           cuando ya terminó. */
        foreach ([
            'fecha_inicio_registro' => $abre_registro,
            'fecha_fin_registro' => $cierra_registro,
            'fin_fecha_entrega_docs' => $entrega_docs,
        ] as $campo => $fecha) {
            if ($fecha->lessThan($inicio) || $fecha->greaterThan($fin)) {
                throw ValidationException::withMessages([
                    $campo => 'Esta fecha debe caer entre el inicio y el término de la convocatoria.',
                ]);
            }
        }
    }
}
