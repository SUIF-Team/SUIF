<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ConsultaPersonasRegistradas;
use App\Support\Admin\ConsultaPreRegistros;
use App\Support\Admin\OrigenBandejaAdmin;
use Illuminate\Http\Request;

/**
 * Admin\PersonaController
 *
 * Migrado desde: app/controllers/admin/PersonaController.php
 * Responsabilidad: listado, búsqueda y gestión de personas por el administrador.
 */
class PersonaController extends Controller
{
    public function index(
        ConsultaPreRegistros $consulta_pre_registros,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $personas = collect($consulta_pre_registros->bandeja())
            ->map(function (array $persona) use ($origen_bandeja): array {
                $persona['ruta_expediente'] = route('admin.personas.show', [
                    'id' => $persona['id'],
                    'origen' => $origen_bandeja->contexto()['origen'],
                ]);

                return $persona;
            })
            ->sortByDesc('fecha_registro')
            ->values()
            ->all();

        return view('admin.personas', [
            'datos_vista' => [
                'personas' => $personas,
                'estados' => $consulta_pre_registros->estados(),
            ],
        ]);
    }

    public function registradas(ConsultaPersonasRegistradas $consulta_personas)
    {
        // TODO futuro: definir un expediente general antes de enlazar una
        // persona que puede tener varias solicitudes.
        $personas = $consulta_personas->personas();

        return view('admin.personas-registradas', [
            'datos_vista' => [
                'personas' => $personas,
                'estados' => $consulta_personas->estados(),
            ],
        ]);
    }

    public function show(
        Request $request,
        string $id,
        ConsultaPreRegistros $consulta_pre_registros,
        OrigenBandejaAdmin $origen_bandeja
    )
    {
        $contexto_bandeja = $origen_bandeja->contexto();

        abort_unless(ctype_digit($id), 404);

        $expediente = $consulta_pre_registros->solicitud((int) $id);

        abort_unless($expediente, 404);

        $expediente['persona']['documentos'] = [];

        return view('admin.preregistro-detalle', [
            'persona' => $expediente['persona'],
            'estados' => $expediente['estados'],
            'contexto_bandeja' => $contexto_bandeja,
            'modo_solo_lectura' => true,
            'ruta_documentacion' => route('admin.documentos.show', [
                'id' => $id,
                'origen' => $contexto_bandeja['origen'],
            ]),
        ]);
    }
}
