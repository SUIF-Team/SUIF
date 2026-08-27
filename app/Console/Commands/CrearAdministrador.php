<?php

namespace App\Console\Commands;

use App\Servicios\GestionAdministradores;
use App\Support\Admin\AccesoAdministrativo;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CrearAdministrador
 *
 * Responsabilidad: dar de alta —o promover— una persona con un rol
 * administrativo y dejar su rol con los privilegios que le tocan.
 *
 * Es la única puerta de entrada cuando todavía no hay nadie que administre
 * usuarios: en cuanto existe un Superusuario, las altas se hacen desde el
 * módulo de administradores, que comparte servicio con este comando.
 *
 * El acceso se resuelve cruzando PERSONA (CURP) con USUARIO (clave y rol),
 * igual que en AuthController, por eso se escriben ambas tablas.
 *
 * Uso:
 *   php artisan suif:crear-admin
 *   php artisan suif:crear-admin --curp=XXXX800101HDFRRN01 --nombre=Ana --paterno=Ruiz --materno=Soto
 *   php artisan suif:crear-admin --rol="Admin UIF" --curp=...
 */
class CrearAdministrador extends Command
{
    protected $signature = 'suif:crear-admin
        {--curp= : CURP con la que inicia sesión (18 caracteres)}
        {--nombre= : Nombre de pila}
        {--paterno= : Apellido paterno}
        {--materno= : Apellido materno}
        {--inegi=009 : Clave INEGI de la entidad federativa}
        {--rol=Superusuario : Superusuario, Admin UIF o Admin DEC}
        {--clave= : Clave de acceso; si se omite se pide sin mostrarla en pantalla}';

    protected $description = 'Crea un usuario administrador y concede a su rol los privilegios que le corresponden';

    /**
     * Privilegios de cada rol. Los da de alta si el catálogo todavía no los
     * tiene, para que el comando deje un administrador operativo aunque
     * suif_roles_administrativos.sql no se haya corrido.
     */
    private const PRIVILEGIOS_POR_ROL = [
        GestionAdministradores::SUPERUSUARIO => [
            AccesoAdministrativo::VALIDACION_REGISTRO,
            AccesoAdministrativo::GESTIONAR_PAGOS,
            AccesoAdministrativo::GENERAR_REPORTES,
            AccesoAdministrativo::GESTIONAR_USUARIOS,
            AccesoAdministrativo::GESTIONAR_REFERENCIAS,
            AccesoAdministrativo::GESTIONAR_SEDES,
        ],
        GestionAdministradores::ADMIN_UIF => [
            AccesoAdministrativo::VALIDACION_REGISTRO,
        ],
        GestionAdministradores::ADMIN_DEC => [
            AccesoAdministrativo::GESTIONAR_PAGOS,
            AccesoAdministrativo::GESTIONAR_REFERENCIAS,
        ],
    ];

    public function handle(GestionAdministradores $gestion): int
    {
        $nombre_rol = trim((string) $this->option('rol'));

        if (!isset(self::PRIVILEGIOS_POR_ROL[$nombre_rol])) {
            $this->error(sprintf(
                'El rol "%s" no existe. Usa uno de: %s.',
                $nombre_rol,
                implode(', ', array_keys(self::PRIVILEGIOS_POR_ROL))
            ));

            return self::FAILURE;
        }

        $curp = strtoupper(trim((string) ($this->option('curp') ?: $this->ask('CURP'))));

        if (strlen($curp) !== 18) {
            $this->error('La CURP debe tener exactamente 18 caracteres.');

            return self::FAILURE;
        }

        $clave = (string) ($this->option('clave') ?: $this->secret('Clave de acceso'));

        if (strlen($clave) < 8) {
            $this->error('La clave de acceso debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $rol = DB::table('rol')->where('rol_tipo_rol', $nombre_rol)->first();

        if (!$rol) {
            $this->error(
                "No existe el rol \"{$nombre_rol}\". Ejecuta antes database/scripts/suif_roles_administrativos.sql."
            );

            return self::FAILURE;
        }

        $persona = DB::table('persona')->where('pers_curp', $curp)->first();
        $inegi = str_pad(trim((string) $this->option('inegi')), 3, '0', STR_PAD_LEFT);

        try {
            $concedidos = $gestion->concederPrivilegios(
                (int) $rol->rol_id_rol,
                self::PRIVILEGIOS_POR_ROL[$nombre_rol]
            );

            /* Una CURP que ya existe no se da de alta otra vez: se promueve al
               rol pedido y se le repone la clave. Es la vía para recuperar el
               acceso cuando nadie puede entrar al módulo. */
            if ($persona) {
                DB::table('usuario')
                    ->where('usua_id_usuario', $persona->pers_id_usuario)
                    ->update([
                        'usua_id_rol' => $rol->rol_id_rol,
                        'usua_clave_acceso' => Hash::make($clave),
                        'usua_activo' => true,
                    ]);
            } else {
                $gestion->crear([
                    'curp' => $curp,
                    'nombre' => trim((string) ($this->option('nombre') ?: $this->ask('Nombre'))),
                    'primer_apellido' => trim((string) ($this->option('paterno') ?: $this->ask('Apellido paterno'))),
                    'segundo_apellido' => trim((string) ($this->option('materno') ?: $this->ask('Apellido materno'))),
                    'entidad_federativa' => $inegi,
                    'rol_id' => (int) $rol->rol_id_rol,
                    'clave' => $clave,
                ]);
            }
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Privilegios del rol %s: %d en total (%d concedidos ahora).',
            $nombre_rol,
            count(self::PRIVILEGIOS_POR_ROL[$nombre_rol]),
            $concedidos
        ));

        $this->info($persona
            ? "La CURP {$curp} ya existía: se promovió a {$nombre_rol} y se cambió su clave."
            : "Administrador creado. Inicia sesión con la CURP {$curp}.");

        return self::SUCCESS;
    }
}
