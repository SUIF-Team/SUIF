<?php

namespace App\Servicios;

use App\Mail\ClaveRestablecida;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * GestionClaves
 *
 * Responsabilidad: generar, guardar y enviar por correo claves de acceso.
 * La comparten el pre-registro, la restauración administrativa y la
 * recuperación pública, para que toda clave del sistema tenga el mismo
 * formato y el mismo cuidado: hasheada en la base y jamás en un log.
 */
class GestionClaves
{
    /** Clave con formato XXXX-XXXX-XXXX, la misma del pre-registro. */
    public function generar(): string
    {
        return strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
    }

    /**
     * Hashea y guarda la clave. Recibe siempre un string: escribir NULL
     * sacaría a la persona de las bandejas administrativas.
     */
    public function actualizar(int $id_usuario, string $clave): void
    {
        DB::table('usuario')
            ->where('usua_id_usuario', $id_usuario)
            ->update(['usua_clave_acceso' => Hash::make($clave)]);
    }

    /** Envía la clave restablecida y responde si el correo salió. */
    public function enviar(string $correo, string $clave): bool
    {
        try {
            Mail::to($correo)->send(new ClaveRestablecida($clave));

            return true;
        } catch (\Throwable $exception) {
            /* Solo el motivo del fallo: la clave jamás debe ir al log. */
            Log::warning('No fue posible enviar la clave restablecida.', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    /** Correo principal más reciente de la persona, o null si no registró uno. */
    public function correoPrincipal(int $id_persona): ?string
    {
        $correo = DB::table('comunicacion as co')
            ->join('tipo_comunicacion as tc', 'tc.tico_id_tipo_comunicacion', '=', 'co.comu_id_tipo_comunicacion')
            ->where('co.comu_id_persona', $id_persona)
            ->where('tc.tico_tipo_comunicacion', 'Correo principal')
            ->orderByDesc('co.comu_id_comunicacion')
            ->value('co.comu_descripcion');

        return $correo === null ? null : (string) $correo;
    }
}
