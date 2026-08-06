<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * SedeController
 *
 * Migrado desde: app/controllers/SedeController.php
 * Responsabilidad: consulta y selección de sedes y horarios de certificación.
 */
class SedeController extends Controller
{
    private $sedes = [
        ['id' => 1, 'nombre' => 'Sede Centro – FCA UNAM', 'direccion' => 'Av. Insurgentes Norte 425, Cuauhtémoc', 'entidad' => 'Ciudad de México', 'fecha' => '01 de junio de 2026', 'hora' => '10:00 hrs', 'cupo_usado' => 32, 'cupo_total' => 80],
        ['id' => 2, 'nombre' => 'Sede Sur – Ciudad Universitaria', 'direccion' => 'Circuito Escolar s/n, Coyoacán', 'entidad' => 'Ciudad de México', 'fecha' => '01 de junio de 2026', 'hora' => '13:00 hrs', 'cupo_usado' => 12, 'cupo_total' => 80],
        ['id' => 3, 'nombre' => 'Sede Guadalajara – CUCEA', 'direccion' => 'Perif. Nte. 799, Zapopan', 'entidad' => 'Jalisco', 'fecha' => '03 de junio de 2026', 'hora' => '10:00 hrs', 'cupo_usado' => 45, 'cupo_total' => 80],
        ['id' => 4, 'nombre' => 'Sede Mérida – Campus UADY', 'direccion' => 'Calle 60 491A, Centro, Mérida', 'entidad' => 'Yucatán', 'fecha' => '04 de junio de 2026', 'hora' => '10:00 hrs', 'cupo_usado' => 80, 'cupo_total' => 80],
        ['id' => 5, 'nombre' => 'Sede Veracruz – USBI', 'direccion' => 'Blvd. Ávila Camacho s/n, Boca del Río', 'entidad' => 'Veracruz', 'fecha' => '05 de junio de 2026', 'hora' => '11:00 hrs', 'cupo_usado' => 58, 'cupo_total' => 80],
        ['id' => 6, 'nombre' => 'Sede Monterrey – FACPYA UANL', 'direccion' => 'Av. Universidad s/n, San Nicolás', 'entidad' => 'Nuevo León', 'fecha' => '06 de junio de 2026', 'hora' => '10:00 hrs', 'cupo_usado' => 7, 'cupo_total' => 80],
    ];

    public function index(Request $request)
    {
        $estado = (array) $request->session()->get('suif.persona.estado', []);

        if (!empty($estado['sede_seleccionada'])) {
            return view('persona.sede', [
                'confirmada' => true,
                'sede' => (array) $request->session()->get('suif.persona.sede', []),
            ]);
        }

        $entidad = $request->query('entidad');
        $buscar = trim((string) $request->query('buscar'));

        $sedes = array_map(function ($sede) {
            $sede['cupo_disponible'] = $sede['cupo_total'] - $sede['cupo_usado'];
            $sede['sin_cupo'] = $sede['cupo_disponible'] <= 0;
            return $sede;
        }, $this->sedes);

        if ($entidad) {
            $sedes = array_filter($sedes, function ($sede) use ($entidad) {
                return $sede['entidad'] === $entidad;
            });
        }

        if ($buscar !== '') {
            $sedes = array_filter($sedes, function ($sede) use ($buscar) {
                return stripos($sede['nombre'], $buscar) !== false;
            });
        }

        return view('persona.sede', [
            'confirmada' => false,
            'sedes' => $sedes,
            'entidades' => array_unique(array_column($this->sedes, 'entidad')),
            'entidadActual' => $entidad,
            'buscarActual' => $buscar,
        ]);
    }

    public function seleccionar(Request $request)
    {
        $id = (int) $request->input('sede_id');
        $sede = null;
        foreach ($this->sedes as $candidata) {
            if ($candidata['id'] === $id) {
                $sede = $candidata;
                break;
            }
        }

        if (!$sede) {
            return redirect()->route('persona.sede.index')
                ->withErrors(['sede' => 'Selecciona una sede válida.']);
        }

        $request->session()->put('suif.persona.sede', $sede);
        $request->session()->put('suif.persona.estado.sede_seleccionada', true);

        return redirect()->route('persona.sede.index');
    }

    public function reiniciar(Request $request)
    {
        if (!config('app.debug')) {
            abort(404);
        }

        $request->session()->forget('suif.persona.sede');
        $request->session()->put('suif.persona.estado.sede_seleccionada', false);

        return redirect()->route('persona.sede.index');
    }
}