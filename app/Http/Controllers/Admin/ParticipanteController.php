<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\PreRegistroDatosPrueba;
use Illuminate\Http\Request;

/**
 * Admin\ParticipanteController
 *
 * Migrado desde: app/controllers/admin/ParticipanteController.php
 * Responsabilidad: listado, búsqueda y gestión de participantes por el administrador.
 */
class ParticipanteController extends Controller
{
    public function index(PreRegistroDatosPrueba $datos_prueba)
    {
        return view('admin.participantes', [
            'participantes' => array_values($datos_prueba->participantes()),
            'estados_iniciales' => $datos_prueba->estadoInicial(),
        ]);
    }

    public function show(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        return view('admin.preregistro-detalle', [
            'participante' => $participante,
            'estados' => $this->estados($request, $id, $datos_prueba),
        ]);
    }

    public function aceptarPreRegistro(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        abort_unless($datos_prueba->participante($id), 404);

        $request->session()->put($this->claveEstado($id), $datos_prueba->estadoAceptado());

        return redirect()->route('admin.documentos.show', ['id' => $id]);
    }

    private function estados(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba): array
    {
        return array_merge(
            $datos_prueba->estadoInicial(),
            (array) $request->session()->get($this->claveEstado($id), [])
        );
    }

    private function claveEstado(string $id): string
    {
        return 'suif.admin.preregistro.'.$id;
    }
}
