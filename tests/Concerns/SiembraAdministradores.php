<?php

namespace Tests\Concerns;

use App\Servicios\GestionAdministradores;
use App\Support\Admin\AccesoAdministrativo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Siembra de roles y privilegios para el esquema temporal de las pruebas.
 *
 * Los permisos se resuelven contra PRIVILEGIO_ROL y no contra el nombre del rol,
 * así que un administrador de prueba sin privilegios sembrados recibe 403 en
 * todas partes. Esto lo pone en su sitio sin que cada prueba repita la matriz.
 */
trait SiembraAdministradores
{
    /**
     * @return array<int, string>
     */
    private function privilegiosDeSuperusuario(): array
    {
        return [
            AccesoAdministrativo::VALIDACION_REGISTRO,
            AccesoAdministrativo::GESTIONAR_PAGOS,
            AccesoAdministrativo::GENERAR_REPORTES,
            AccesoAdministrativo::GESTIONAR_USUARIOS,
            AccesoAdministrativo::GESTIONAR_REFERENCIAS,
            AccesoAdministrativo::GESTIONAR_SEDES,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function privilegiosDeAdminUif(): array
    {
        return [AccesoAdministrativo::VALIDACION_REGISTRO];
    }

    /**
     * @return array<int, string>
     */
    private function privilegiosDeAdminDec(): array
    {
        return [
            AccesoAdministrativo::GESTIONAR_PAGOS,
            AccesoAdministrativo::GESTIONAR_REFERENCIAS,
        ];
    }

    private function crearTablasDePrivilegios(): void
    {
        Schema::dropIfExists('privilegio_rol');
        Schema::dropIfExists('privilegio');

        Schema::create('privilegio', function (Blueprint $table): void {
            $table->increments('priv_id_privilegio');
            $table->string('priv_privilegio', 35);
        });

        Schema::create('privilegio_rol', function (Blueprint $table): void {
            $table->increments('ropr_id_privilegio_rol');
            $table->integer('ropr_id_privilegio');
            $table->integer('ropr_id_rol');
        });
    }

    /**
     * @param array<int, string> $privilegios
     */
    private function concederPrivilegiosAlRol(int $idRol, array $privilegios): void
    {
        app(GestionAdministradores::class)->concederPrivilegios($idRol, $privilegios);
    }

    /**
     * Los tres roles del módulo, con los identificadores que la prueba indique.
     *
     * @param array<string, int> $roles nombre => id
     */
    private function sembrarRolesAdministrativos(array $roles): void
    {
        $privilegios = [
            GestionAdministradores::SUPERUSUARIO => $this->privilegiosDeSuperusuario(),
            GestionAdministradores::ADMIN_UIF => $this->privilegiosDeAdminUif(),
            GestionAdministradores::ADMIN_DEC => $this->privilegiosDeAdminDec(),
        ];

        foreach ($roles as $nombre => $idRol) {
            $existe = DB::table('rol')->where('rol_id_rol', $idRol)->exists();

            if (!$existe) {
                DB::table('rol')->insert(['rol_id_rol' => $idRol, 'rol_tipo_rol' => $nombre]);
            }

            $this->concederPrivilegiosAlRol($idRol, $privilegios[$nombre]);
        }
    }
}
