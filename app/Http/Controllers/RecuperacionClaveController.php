<?php

namespace App\Http\Controllers;

use App\Autorizacion\AccesoAdministrativo;
use App\Models\Persona;
use App\Models\Usuario;
use App\Servicios\GestionClaves;
use Illuminate\Http\Request;

/**
 * RecuperacionClaveController
 *
 * Responsabilidad: recuperación pública de la clave de acceso, en dos pasos.
 *
 * 1. La persona escribe su CURP y recibe en su correo principal un enlace
 *    firmado. Pedirlo no toca la cuenta: quien conozca una CURP ajena ya no
 *    puede dejar a su dueña fuera revocándole la clave. La respuesta es
 *    idéntica exista o no la CURP, el mismo criterio del login.
 * 2. El enlace abre una pantalla con un botón; el POST genera la clave y la
 *    muestra una sola vez. El GET no cambia nada porque los antivirus de
 *    correo abren los enlaces para revisarlos y gastarían el cambio.
 *
 * El enlace caduca por su firma y sirve una sola vez por la huella de la
 * clave vigente (GestionClaves::huella()): al cambiar la clave, deja de
 * coincidir. Por eso no hace falta una tabla de tokens.
 */
class RecuperacionClaveController extends Controller
{
    private const MENSAJE_GENERICO = 'Si tu CURP está registrada, enviaremos a tu correo principal un enlace para crear una clave nueva. Revisa también tu bandeja de spam.';

    /**
     * Muestra el formulario para solicitar el enlace.
     */
    public function formulario()
    {
        return view('auth.recuperar-clave');
    }

    /**
     * Envía el enlace de recuperación. Todas las ramas terminan con el mismo
     * mensaje: la respuesta no dice si la CURP existe ni si el correo salió.
     */
    public function restablecer(Request $request, GestionClaves $gestion_claves, AccesoAdministrativo $acceso)
    {
        $datos = $this->validate($request, [
            'curp' => 'required|string|size:18',
        ], [
            'curp.required' => 'Escribe tu CURP.',
            'curp.size' => 'La CURP debe tener 18 caracteres.',
        ]);

        $persona = Persona::where('pers_curp', strtoupper($datos['curp']))->first();

        if ($this->puedeRecuperarse($persona?->usuario, $acceso)) {
            $correo = $gestion_claves->correoPrincipal((int) $persona->pers_id_persona);

            if ($correo !== null) {
                $gestion_claves->enviarEnlace($correo, $gestion_claves->enlaceRecuperacion($persona->usuario));
            }
        }

        /* Sin destino a propósito: la confirmación pertenece a esta misma
           pantalla, así que se pinta aquí en lugar de navegar. */
        return $this->responder($request, 'success', self::MENSAJE_GENERICO);
    }

    /**
     * Pantalla del enlace: sólo el botón que confirma. No cambia nada.
     */
    public function confirmar(Request $request, $usuario, $huella, GestionClaves $gestion_claves, AccesoAdministrativo $acceso)
    {
        if (!$this->enlaceValido($request, $usuario, $huella, $gestion_claves, $acceso)) {
            return $this->enlaceInvalido();
        }

        return response()
            ->view('auth.restablecer-clave', ['accion' => $request->fullUrl()])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Genera la clave y la muestra una sola vez. Al guardarla cambia la huella
     * y el enlace deja de servir, también para una recarga de esta página.
     */
    public function generar(Request $request, $usuario, $huella, GestionClaves $gestion_claves, AccesoAdministrativo $acceso)
    {
        if (!$this->enlaceValido($request, $usuario, $huella, $gestion_claves, $acceso)) {
            return $this->enlaceInvalido();
        }

        $clave = $gestion_claves->generar();
        $gestion_claves->actualizar((int) $usuario, $clave);

        return response()
            ->view('auth.restablecer-clave', ['clave' => $clave])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Firma vigente, huella de la clave actual y una cuenta que todavía puede
     * recuperarse sola. Se comprueba en el GET y otra vez en el POST.
     */
    private function enlaceValido(Request $request, $id_usuario, $huella, GestionClaves $gestion_claves, AccesoAdministrativo $acceso): bool
    {
        if (!$request->hasValidSignature(false)) {
            return false;
        }

        $usuario = Usuario::find((int) $id_usuario);

        return $this->puedeRecuperarse($usuario, $acceso)
            && hash_equals($gestion_claves->huella($usuario), (string) $huella);
    }

    /**
     * Caducado, ya usado o alterado: la misma respuesta para los tres, que no
     * distingue cuál fue y lleva a pedir otro enlace.
     */
    private function enlaceInvalido()
    {
        return response()
            ->view('auth.restablecer-clave', ['invalido' => true], 403)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Sólo una persona se recupera sola: la clave de una cuenta
     * administrativa no debe poder cambiarse desde un formulario público, y
     * la de un administrador dado de baja tampoco.
     */
    private function puedeRecuperarse(?Usuario $usuario, AccesoAdministrativo $acceso): bool
    {
        return $usuario !== null
            && $usuario->usua_clave_acceso !== null
            && $acceso->esPersona($usuario);
    }
}
