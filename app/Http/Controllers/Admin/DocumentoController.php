<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\PreRegistroDatosPrueba;
use Illuminate\Http\Request;

/**
 * Admin\DocumentacionController
 *
 * Migrado desde: app/controllers/admin/DocumentacionController.php
 * Responsabilidad: revisión y validación de documentación de participantes por el administrador.
 */
class DocumentoController extends Controller
{
    public function show(Request $request, string $id, PreRegistroDatosPrueba $datos_prueba)
    {
        $participante = $datos_prueba->participante($id);

        abort_unless($participante, 404);

        $estados = array_merge(
            $datos_prueba->estadoInicial(),
            (array) $request->session()->get('suif.admin.preregistro.'.$id, [])
        );

        return view('admin.preregistro-documentacion', compact('participante', 'estados'));
    }
}
