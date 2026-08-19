<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CrearAdministrador
 *
 * Responsabilidad: dar de alta —o promover— una persona con rol
 * Administrador y concederle todos los privilegios del catálogo.
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

    protected $description = 'Crea un usuario con rol Administrador y le concede todos los privilegios';

    /**
     * Catálogo de privilegios del sistema. Los que falten se dan de alta.
     */
    private const PRIVILEGIOS = [
        'Validación Registro',
        'Gestionar Pagos',
        'Generación Reportes',
        'Gestionar usuarios',
    ];

    public function handle(): int
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

        $rol = DB::table('rol')->where('rol_tipo_rol', 'Administrador')->first();

        if (!$rol) {
            $this->error('No existe el rol "Administrador". Ejecuta antes database/scripts/suif_catalogos.sql.');

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

        DB::transaction(function () use ($curp, $clave, $rol, $persona, $inegi, $nombre, $paterno, $materno, &$creada) {
            $privilegios = $this->asegurarPrivilegios();
            $concedidos = $this->concederPrivilegios((int) $rol->rol_id_rol, $privilegios);

            if ($persona) {
                DB::table('usuario')
                    ->where('usua_id_usuario', $persona->pers_id_usuario)
                    ->update([
                        'usua_id_rol' => $rol->rol_id_rol,
                        'usua_clave_acceso' => Hash::make($clave),
                    ]);
            } else {
                $this->sincronizarSecuencia('usuario', 'usua_id_usuario');
                $this->sincronizarSecuencia('persona', 'pers_id_persona');

                $idUsuario = DB::table('usuario')->insertGetId([
                    'usua_id_rol' => $rol->rol_id_rol,
                    'usua_clave_acceso' => Hash::make($clave),
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
            }

            $this->info(sprintf(
                'Privilegios del rol Administrador: %d en total (%d concedidos ahora).',
                count($privilegios),
                $concedidos
            ));
        });

        $this->info($creada
            ? "Administrador creado. Inicia sesión con la CURP {$curp}."
            : "La CURP {$curp} ya existía: se promovió a Administrador y se cambió su clave.");

        return self::SUCCESS;
    }

    /**
     * Da de alta los privilegios del catálogo que aún no existan.
     *
     * @return array<int, int> ids de todos los privilegios
     */
    private function asegurarPrivilegios(): array
    {
        $this->sincronizarSecuencia('privilegio', 'priv_id_privilegio');

        foreach (self::PRIVILEGIOS as $privilegio) {
            $existe = DB::table('privilegio')
                ->where('priv_privilegio', $privilegio)
                ->exists();

            if (!$existe) {
                DB::table('privilegio')->insert(['priv_privilegio' => $privilegio]);
            }
        }

        return DB::table('privilegio')->pluck('priv_id_privilegio')->all();
    }

    /**
     * Concede al rol todos los privilegios que le falten. Devuelve cuántos añadió.
     *
     * @param array<int, int> $privilegios
     */
    private function concederPrivilegios(int $idRol, array $privilegios): int
    {
        $this->sincronizarSecuencia('privilegio_rol', 'ropr_id_privilegio_rol');

        $yaTiene = DB::table('privilegio_rol')
            ->where('ropr_id_rol', $idRol)
            ->pluck('ropr_id_privilegio')
            ->all();

        $faltantes = array_diff($privilegios, $yaTiene);

        foreach ($faltantes as $idPrivilegio) {
            DB::table('privilegio_rol')->insert([
                'ropr_id_privilegio' => $idPrivilegio,
                'ropr_id_rol' => $idRol,
            ]);
        }

        return count($faltantes);
    }

    /**
     * Alinea la secuencia con el máximo real de la tabla.
     *
     * Las tablas se cargaron con ids explícitos desde los scripts SQL, así que
     * la secuencia puede haber quedado atrás y provocar llave duplicada.
     */
    private function sincronizarSecuencia(string $tabla, string $columna): void
    {
        DB::statement(
            "SELECT setval(pg_get_serial_sequence(?, ?),
                    COALESCE((SELECT MAX({$columna}) FROM {$tabla}), 0) + 1,
                    false)",
            [$tabla, $columna]
        );
    }
}
