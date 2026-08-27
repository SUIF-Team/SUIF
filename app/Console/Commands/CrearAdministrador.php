<?php

namespace App\Console\Commands;

use App\Servicios\GestionAdministradores;
use App\Support\Admin\AccesoAdministrativo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CrearAdministrador
 *
 * Responsabilidad: dar de alta —o promover— una persona con rol Superusuario
 * y concederle todos los privilegios del catálogo.
 *
 * Dejó de ser el camino normal: las altas se hacen desde
 * /admin/administradores, que además permite elegir el área. Este comando
 * queda como puerta de emergencia, y es la única forma de arrancar una
 * instalación nueva o de recuperar el sistema si se perdió al último
 * Superusuario.
 *
 * El acceso se resuelve cruzando PERSONA (CURP) con USUARIO (clave y rol),
 * igual que en AuthController, por eso se escriben ambas tablas.
 *
 * Uso:
 *   php artisan suif:crear-admin
 *   php artisan suif:crear-admin --curp=XXXX800101HDFRRN01 --nombre=Ana --paterno=Ruiz --materno=Soto
 */
class CrearAdministrador extends Command
{
    protected $signature = 'suif:crear-admin
        {--curp= : CURP con la que inicia sesión (18 caracteres)}
        {--nombre= : Nombre de pila}
        {--paterno= : Apellido paterno}
        {--materno= : Apellido materno}
        {--inegi=009 : Clave INEGI de la entidad federativa}
        {--clave= : Clave de acceso; si se omite se pide sin mostrarla en pantalla}';

    protected $description = 'Crea un usuario con rol Superusuario y le concede todos los privilegios';

    /**
     * Catálogo completo. Los que falten se dan de alta.
     *
     * Se nombran desde AccesoAdministrativo para que el comando y los gates no
     * puedan discrepar: un privilegio mal escrito aquí crearía un renglón
     * gemelo en PRIVILEGIO que ningún permiso consulta.
     */
    private const PRIVILEGIOS = [
        AccesoAdministrativo::VALIDACION_REGISTRO,
        AccesoAdministrativo::GESTIONAR_PAGOS,
        AccesoAdministrativo::GENERAR_REPORTES,
        AccesoAdministrativo::GESTIONAR_USUARIOS,
        AccesoAdministrativo::GESTIONAR_REFERENCIAS,
        AccesoAdministrativo::GESTIONAR_SEDES,
    ];

    public function handle(GestionAdministradores $gestion): int
    {
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

        $rol = DB::table('rol')
            ->where('rol_tipo_rol', GestionAdministradores::SUPERUSUARIO)
            ->first();

        if (!$rol) {
            $this->error(sprintf(
                'No existe el rol "%s". Ejecuta antes database/scripts/suif_roles_administrativos.sql.',
                GestionAdministradores::SUPERUSUARIO
            ));

            return self::FAILURE;
        }

        $persona = DB::table('persona')->where('pers_curp', $curp)->first();

        $inegi = str_pad(trim((string) $this->option('inegi')), 3, '0', STR_PAD_LEFT);
        $nombre = '';
        $paterno = '';
        $materno = '';

        // Los datos personales sólo se piden cuando hay que crear la persona.
        if (!$persona) {
            $nombre = trim((string) ($this->option('nombre') ?: $this->ask('Nombre')));
            $paterno = trim((string) ($this->option('paterno') ?: $this->ask('Apellido paterno')));
            $materno = trim((string) ($this->option('materno') ?: $this->ask('Apellido materno')));

            if ($nombre === '' || $materno === '') {
                $this->error('El nombre y el apellido materno son obligatorios.');

                return self::FAILURE;
            }

            if (!DB::table('entidad_federativa')->where('enfe_clave_inegi', $inegi)->exists()) {
                $this->error("La clave INEGI {$inegi} no existe en entidad_federativa.");

                return self::FAILURE;
            }
        }

        $creada = false;
        $concedidos = 0;

        DB::transaction(function () use (
            $gestion, $curp, $clave, $rol, $persona, $inegi,
            $nombre, $paterno, $materno, &$creada, &$concedidos
        ): void {
            $concedidos = $gestion->concederPrivilegios((int) $rol->rol_id_rol, self::PRIVILEGIOS);

            if ($persona) {
                /* Promover devuelve el acceso: si la cuenta estaba dada de
                   baja, este comando es justo el que se usa para rescatarla. */
                DB::table('usuario')
                    ->where('usua_id_usuario', $persona->pers_id_usuario)
                    ->update([
                        'usua_id_rol' => $rol->rol_id_rol,
                        'usua_clave_acceso' => Hash::make($clave),
                        'usua_activo' => true,
                    ]);

                return;
            }

            $gestion->sincronizarSecuencia('usuario', 'usua_id_usuario');
            $gestion->sincronizarSecuencia('persona', 'pers_id_persona');

            $idUsuario = DB::table('usuario')->insertGetId([
                'usua_id_rol' => $rol->rol_id_rol,
                'usua_clave_acceso' => Hash::make($clave),
                'usua_activo' => true,
            ], 'usua_id_usuario');

            DB::table('persona')->insert([
                'pers_clave_inegi' => $inegi,
                'pers_id_usuario' => $idUsuario,
                'pers_curp' => $curp,
                'pers_nombre' => $nombre,
                'pers_apellido_paterno' => $paterno,
                'pers_apellido_materno' => $materno,
                'pers_fecha_registro' => now()->toDateString(),
            ]);

            $creada = true;
        });

        $this->info(sprintf(
            'Privilegios del rol %s: %d en total (%d concedidos ahora).',
            GestionAdministradores::SUPERUSUARIO,
            count(self::PRIVILEGIOS),
            $concedidos
        ));

        $this->info($creada
            ? "Superusuario creado. Inicia sesión con la CURP {$curp}."
            : "La CURP {$curp} ya existía: se promovió a Superusuario y se cambió su clave.");

        return self::SUCCESS;
    }
}
