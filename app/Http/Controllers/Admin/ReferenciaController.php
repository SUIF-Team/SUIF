<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Servicios\CatalogoReferencias;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Admin\ReferenciaController
 *
 * Migrado desde: app/controllers/admin/ReferenciaController.php
 * Responsabilidad: gestión de referencias bancarias desde el panel administrativo.
 */
class ReferenciaController extends Controller
{
    /**
     * Catálogo de referencias con su estado de asignación.
     */
    public function index(Request $request, CatalogoReferencias $catalogo)
    {
        $filtros = $request->only(['buscar', 'estado']);

        return view('admin.referencias', [
            'referencias' => $catalogo->catalogo($filtros),
            'resumen' => $catalogo->resumen(),
            'filtros' => $filtros,
        ]);
    }

    /**
     * Pantalla de carga: catálogo en CSV y formatos PDF en ZIP.
     */
    public function carga(CatalogoReferencias $catalogo)
    {
        return view('admin.referencias-carga', [
            'resumen' => $catalogo->resumen(),
            'importacion' => session('importacion'),
        ]);
    }

    public function guardarCatalogo(Request $request, CatalogoReferencias $catalogo): RedirectResponse
    {
        $request->validate([
            'catalogo' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'catalogo.required' => 'Selecciona el archivo CSV con las referencias.',
            'catalogo.mimes' => 'El catálogo debe ser un archivo CSV.',
            'catalogo.max' => 'El archivo CSV no debe exceder los 2048 KB.',
        ]);

        try {
            $resultado = $catalogo->importarCatalogo($request->file('catalogo'));
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('No fue posible importar el catálogo de referencias.', ['error' => $exception->getMessage()]);

            return back()->with('error', 'No fue posible procesar el archivo CSV.');
        }

        return back()
            ->with('success', 'El catálogo de referencias se procesó correctamente.')
            ->with('importacion', ['tipo' => 'catalogo'] + $resultado);
    }

    public function guardarFormatos(Request $request, CatalogoReferencias $catalogo): RedirectResponse
    {
        $request->validate([
            'formatos' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ], [
            'formatos.required' => 'Selecciona el archivo ZIP con los formatos PDF.',
            'formatos.mimes' => 'Los formatos deben venir en un archivo ZIP.',
            'formatos.max' => 'El archivo ZIP no debe exceder los 50 MB.',
        ]);

        try {
            $resultado = $catalogo->importarFormatos($request->file('formatos'));
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('No fue posible importar los formatos de referencia.', ['error' => $exception->getMessage()]);

            return back()->with('error', 'No fue posible procesar el archivo ZIP.');
        }

        return back()
            ->with('success', 'Los formatos PDF se extrajeron correctamente.')
            ->with('importacion', ['tipo' => 'formatos'] + $resultado);
    }

    /**
     * Sirve el PDF de una referencia del catálogo.
     */
    public function formato(string $id, CatalogoReferencias $catalogo)
    {
        abort_unless(ctype_digit($id), 404);

        $referencia = DB::table('referencia_bancaria')
            ->where('reba_id_referencia_bancaria', (int) $id)
            ->first();

        abort_unless($referencia, 404);

        $ruta = $catalogo->rutaFormatoDisponible($referencia->reba_path);

        abort_unless($ruta, 404);

        $nombre = preg_replace('/[^A-Za-z0-9-]/', '', (string) $referencia->reba_referencia);

        $respuesta = response()->file(Storage::disk('referencias')->path($ruta), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="referencia-'.$nombre.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective('no-store');

        return $respuesta;
    }
}
