<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\GestionClaves;
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
        $personas = collect($consulta_personas->personas())
            ->map(function (array $persona): array {
                $persona['ruta_restaurar_clave'] = route('admin.personas.registradas.restaurar-clave', [
                    'id' => $persona['id'],
                ]);

                return $persona;
            })
            ->all();

        return view('admin.personas-registradas', [
            'datos_vista' => [
                'personas' => $personas,
                'estados' => $consulta_personas->estados(),
            ],
        ]);
    }

    /**
     * Genera una clave de acceso nueva para una persona de la bandeja y la
     * envía a su correo principal. La clave solo se muestra al administrador
     * cuando el correo no pudo salir: ese aviso es la única copia.
     */
    public function restaurarClave(
        string $id,
        ConsultaPersonasRegistradas $consulta_personas,
        GestionClaves $gestion_claves
    )
    {
        $persona = ctype_digit($id) ? $consulta_personas->persona((int) $id) : null;

        if (!$persona) {
            return redirect()->route('admin.personas.registradas.index')
                ->with('warning', 'La persona solicitada no fue encontrada.');
        }

        $clave = $gestion_claves->generar();
        $gestion_claves->actualizar($persona['id_usuario'], $clave);

        $correo = $gestion_claves->correoPrincipal((int) $id);

        if ($correo === null) {
            return redirect()->route('admin.personas.registradas.index')
                ->with('warning', 'La clave de '.$persona['nombre_completo'].' fue restaurada, pero no tiene un correo principal registrado. Anótala y entrégala por otro medio: '.$clave.'. No volverá a mostrarse.');
        }

        if (!$gestion_claves->enviar($correo, $clave)) {
            return redirect()->route('admin.personas.registradas.index')
                ->with('warning', 'La clave de '.$persona['nombre_completo'].' fue restaurada, pero el correo no pudo enviarse. Anótala y entrégala por otro medio: '.$clave.'. No volverá a mostrarse.');
        }

        return redirect()->route('admin.personas.registradas.index')
            ->with('success', 'La clave de '.$persona['nombre_completo'].' fue restaurada y enviada a su correo principal.');
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
