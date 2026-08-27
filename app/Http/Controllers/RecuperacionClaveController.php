<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Servicios\GestionClaves;
use Illuminate\Http\Request;

/**
 * RecuperacionClaveController
 *
 * Responsabilidad: recuperación pública de la clave de acceso. Genera una
 * clave nueva y la envía al correo principal registrado; la respuesta es
 * idéntica exista o no la CURP para no revelar cuáles están registradas,
 * el mismo criterio del login.
 */
class RecuperacionClaveController extends Controller
{
    /* Solo estos roles se recuperan solos: la clave de una cuenta
       privilegiada no debe poder revocarse desde un formulario público. */
    private const ROLES_RECUPERABLES = ['Persona', 'Candidato'];

    private const MENSAJE_GENERICO = 'Si tu CURP está registrada, enviaremos una clave de acceso nueva a tu correo principal. Revisa también tu bandeja de spam.';

    /**
     * Muestra el formulario para solicitar una clave nueva.
     */
    public function formulario()
    {
        return view('auth.recuperar-clave');
    }

    /**
     * Genera y envía la clave nueva. Todas las ramas terminan con el mismo
     * mensaje: la respuesta no dice si la CURP existe ni si el correo salió.
     */
    public function restablecer(Request $request, GestionClaves $gestion_claves)
    {
        $datos = $this->validate($request, [
            'curp' => 'required|string|size:18',
        ], [
            'curp.required' => 'Escribe tu CURP.',
            'curp.size' => 'La CURP debe tener 18 caracteres.',
        ]);

        $persona = Persona::where('pers_curp', strtoupper($datos['curp']))->first();

        if ($this->puedeRecuperarse($persona)) {
            $correo = $gestion_claves->correoPrincipal((int) $persona->pers_id_persona);

            if ($correo !== null) {
                $clave = $gestion_claves->generar();

                /* Primero el correo y solo entonces el hash: si el envío
                   falla, la clave vigente sigue sirviendo y la persona no
                   queda fuera sin ningún canal de recuperación. */
                if ($gestion_claves->enviar($correo, $clave)) {
                    $gestion_claves->actualizar((int) $persona->usuario->usua_id_usuario, $clave);
                }
            }
        }

        return redirect()->route('clave.recuperar')->with('success', self::MENSAJE_GENERICO);
    }

    private function puedeRecuperarse(?Persona $persona): bool
    {
        return $persona !== null
            && $persona->usuario !== null
            && $persona->usuario->usua_clave_acceso !== null
            && in_array($persona->usuario->rol?->rol_tipo_rol, self::ROLES_RECUPERABLES, true);
    }
}
