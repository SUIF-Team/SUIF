<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Lo que nunca vuelve escrito en el formulario tras un fallo.
     *
     * withInput() deja lo enviado en la sesión y la vista lo devuelve con
     * old(): útil para no volver a capturar quince campos, inaceptable para
     * una clave de acceso.
     */
    private const CAMPOS_QUE_NO_SE_REPITEN = [
        'clave',
        'clave_acceso',
        'clave_actual',
        'password',
        'password_confirmation',
    ];

    /**
     * Cierra una acción sirviendo JSON o el redirect de siempre, según quien
     * pregunte.
     *
     * Las pantallas envían sus formularios con window.SUIF.enviar() y esperan
     * JSON; sin JavaScript el mismo formulario llega como POST normal y tiene
     * que seguir terminando en un redirect con su mensaje flash. Vive aquí
     * porque las dos docenas de acciones que mutan algo lo heredan todas.
     *
     * $destino es la URL ya construida con route(): sirve igual como campo
     * 'redirigir' del JSON que como destino del redirect.
     *
     * @param  string  $tipo  success | warning | error, los mismos de session().
     * @param  array  $datos  Carga extra para la pantalla (el estado que cambió).
     * @param  string  $campoError  Clave del bolsón de errores cuando $tipo es 'error'.
     */
    protected function responder(
        Request $peticion,
        string $tipo,
        string $mensaje,
        ?string $destino = null,
        array $datos = [],
        string $campoError = 'general'
    ) {
        if ($peticion->expectsJson()) {
            /* Todo lo que no sea 'success' salió mal: la petición no debe
               resolverse como correcta o la pantalla lo festejaría igual. */
            return response()->json(array_merge([
                'tipo' => $tipo,
                'mensaje' => $mensaje,
                'redirigir' => $destino,
            ], $datos), $tipo === 'success' ? 200 : 422);
        }

        $respuesta = $destino === null ? back() : redirect()->to($destino);

        /* Varias vistas sólo pintan $errors y nunca session('error') —por
           ejemplo persona/documentos—, así que un fallo tiene que viajar
           también por el bolsón o desaparece para quien navega sin
           JavaScript, que es justo el camino que hay que conservar.

           El formulario se repuebla, menos las credenciales: withInput() las
           guardaba en la sesión y las devolvía escritas en el HTML. */
        if ($tipo === 'error') {
            $respuesta = $respuesta
                ->withErrors([$campoError => $mensaje])
                ->withInput($peticion->except(self::CAMPOS_QUE_NO_SE_REPITEN));
        }

        return $respuesta->with($tipo, $mensaje);
    }
}
