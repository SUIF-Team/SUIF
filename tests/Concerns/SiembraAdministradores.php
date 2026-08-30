<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema y siembra que comparten las pruebas del módulo de administradores.
 *
 * Las claves primarias van como `increments` y no como enteros sueltos porque
 * GestionAdministradores da de alta con insertGetId: sin autoincremento, el
 * alta fallaría por una razón que no tiene nada que ver con lo que se prueba.
 */
trait SiembraAdministradores
{
    protected const ROL_PERSONA = 1;

    protected const ROL_SUPERUSUARIO = 2;

    protected const ROL_ADMIN_UIF = 3;

    protected const ROL_ADMIN_DEC = 4;

    protected function crearEsquemaAdministrativo(): void
    {
        foreach ([
            'estado_convocatoria',
            'c_estado_convocatoria',
            'estado_pago',
            'c_estado_pago',
            'referencia_bancaria',
            'pago',
            'estado_solicitud',
            'c_estado_solicitud',
            'solicitud',
            'convocatoria',
            'persona',
            'privilegio_rol',
            'privilegio',
            'usuario',
            'rol',
            'entidad_federativa',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('rol', function (Blueprint $table): void {
            $table->increments('rol_id_rol');
            $table->string('rol_tipo_rol', 15);
        });

        Schema::create('usuario', function (Blueprint $table): void {
            $table->increments('usua_id_usuario');
            $table->integer('usua_id_rol');
            $table->string('usua_clave_acceso')->nullable();
            $table->boolean('usua_activo')->default(true);
        });

        Schema::create('privilegio', function (Blueprint $table): void {
            $table->increments('priv_id_privilegio');
            $table->string('priv_privilegio', 35);
        });

        Schema::create('privilegio_rol', function (Blueprint $table): void {
            $table->increments('ropr_id_privilegio_rol');
            $table->integer('ropr_id_privilegio');
            $table->integer('ropr_id_rol');
        });

        Schema::create('entidad_federativa', function (Blueprint $table): void {
            $table->string('enfe_clave_inegi', 3)->primary();
            $table->string('enfe_entidad_federativa', 20);
        });

        Schema::create('persona', function (Blueprint $table): void {
            $table->increments('pers_id_persona');
            $table->string('pers_clave_inegi', 3)->nullable();
            $table->integer('pers_id_usuario');
            $table->string('pers_curp', 18);
            $table->string('pers_rfc', 13)->nullable();
            $table->string('pers_nombre', 45);
            $table->string('pers_apellido_paterno', 45)->nullable();
            $table->string('pers_apellido_materno', 45);
            $table->date('pers_fecha_registro')->nullable();
        });

        /* El tablero cuenta personas registradas, solicitudes en revisión y
           pagos por validar. Las tablas pueden ir vacías: lo que se prueba es
           qué indicadores y qué tarjetas aparecen, no sus cifras. */
        Schema::create('convocatoria', function (Blueprint $table): void {
            $table->increments('conv_id_convocatoria');
            $table->date('conv_fecha_inicio_registro');
            $table->date('conv_fecha_fin');
            /* Las cinco restantes son nulables aquí y obligatorias en
               PostgreSQL: las pruebas que sólo necesitan una convocatoria para
               colgarle solicitudes la siembran con dos columnas. */
            $table->string('conv_nombre', 300)->nullable();
            $table->string('conv_monto_recuperacion', 25)->nullable();
            $table->date('conv_fecha_fin_registro')->nullable();
            $table->date('conv_fin_fecha_entrega_docs')->nullable();
            $table->date('conv_fecha_inicio')->nullable();
        });

        /* El estado de la convocatoria es una bitácora, igual que el de la
           solicitud y el del pago: un renglón por cambio y el vigente es el de
           identificador más alto. */
        Schema::create('c_estado_convocatoria', function (Blueprint $table): void {
            $table->increments('esco_id_c_estado_convocatoria');
            $table->string('esco_estado_convocatoria', 15);
        });

        Schema::create('estado_convocatoria', function (Blueprint $table): void {
            $table->increments('esco_id_estado_convocatoria');
            $table->integer('esco_id_c_estado_convocatoria');
            $table->integer('esco_id_convocatoria');
            $table->date('esco_fecha')->nullable();
            $table->time('esco_hora')->nullable();
        });

        Schema::create('solicitud', function (Blueprint $table): void {
            $table->increments('soli_id_solicitud');
            $table->integer('soli_id_persona')->nullable();
            $table->integer('soli_id_convocatoria')->nullable();
            $table->integer('soli_id_pago')->nullable();
        });

        Schema::create('c_estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_c_estado_solicitud');
            $table->string('esso_estado_solicitud', 40);
        });

        Schema::create('estado_solicitud', function (Blueprint $table): void {
            $table->increments('esso_id_estado_solicitud');
            $table->integer('esso_id_c_estado_solicitud');
            $table->integer('esso_id_solicitud');
            $table->string('esso_motivo_rechazo', 255)->nullable();
        });

        Schema::create('pago', function (Blueprint $table): void {
            $table->increments('pago_id_pago');
            $table->string('pago_comprobante_path', 200)->nullable();
            $table->decimal('pago_monto_pagado', 10, 4)->nullable();
            $table->string('pago_referencia_bancaria', 20)->nullable();
            $table->date('pago_fecha_pago')->nullable();
            $table->time('pago_hora_pago')->nullable();
            $table->boolean('pago_uso_cfdi')->nullable();
            $table->integer('pago_id_dato_fiscal')->nullable();
        });

        Schema::create('referencia_bancaria', function (Blueprint $table): void {
            $table->increments('reba_id_referencia_bancaria');
            $table->integer('reba_id_pago')->nullable();
            $table->string('reba_referencia', 20);
            $table->decimal('reba_monto', 10, 4)->nullable();
            $table->date('reba_vigencia')->nullable();
        });

        Schema::create('c_estado_pago', function (Blueprint $table): void {
            $table->increments('espa_id_c_estado_pago');
            $table->string('esta_estado_pago', 15);
        });

        Schema::create('estado_pago', function (Blueprint $table): void {
            $table->increments('espa_id_estado_pago');
            $table->integer('espa_id_pago');
            $table->integer('espa_id_c_estado_pago');
            $table->date('espa_fecha')->nullable();
            $table->time('espa_hora')->nullable();
            $table->text('espa_comentario')->nullable();
        });
    }

    /**
     * Los cuatro roles y el reparto de privilegios, igual que
     * database/scripts/suif_roles_administrativos.sql.
     */
    protected function sembrarRolesYPrivilegios(): void
    {
        DB::table('rol')->insert([
            ['rol_id_rol' => self::ROL_PERSONA, 'rol_tipo_rol' => 'Persona'],
            ['rol_id_rol' => self::ROL_SUPERUSUARIO, 'rol_tipo_rol' => 'Superusuario'],
            ['rol_id_rol' => self::ROL_ADMIN_UIF, 'rol_tipo_rol' => 'Admin UIF'],
            ['rol_id_rol' => self::ROL_ADMIN_DEC, 'rol_tipo_rol' => 'Admin DEC'],
        ]);

        DB::table('privilegio')->insert([
            ['priv_id_privilegio' => 1, 'priv_privilegio' => 'Validación Registro'],
            ['priv_id_privilegio' => 2, 'priv_privilegio' => 'Gestionar Pagos'],
            ['priv_id_privilegio' => 3, 'priv_privilegio' => 'Generación Reportes'],
            ['priv_id_privilegio' => 4, 'priv_privilegio' => 'Gestionar usuarios'],
            ['priv_id_privilegio' => 5, 'priv_privilegio' => 'Gestionar Referencias'],
            ['priv_id_privilegio' => 6, 'priv_privilegio' => 'Gestionar Sedes'],
            ['priv_id_privilegio' => 7, 'priv_privilegio' => 'Gestionar Convocatorias'],
        ]);

        $reparto = [
            self::ROL_SUPERUSUARIO => [1, 2, 3, 4, 5, 6, 7],
            self::ROL_ADMIN_UIF => [1],
            self::ROL_ADMIN_DEC => [2, 5],
        ];

        foreach ($reparto as $idRol => $privilegios) {
            foreach ($privilegios as $idPrivilegio) {
                DB::table('privilegio_rol')->insert([
                    'ropr_id_privilegio' => $idPrivilegio,
                    'ropr_id_rol' => $idRol,
                ]);
            }
        }

        DB::table('entidad_federativa')->insert([
            ['enfe_clave_inegi' => '009', 'enfe_entidad_federativa' => 'Ciudad de México'],
            ['enfe_clave_inegi' => '015', 'enfe_entidad_federativa' => 'Estado de México'],
        ]);
    }

    /**
     * Da de alta una cuenta con su persona. Devuelve el id de usuario.
     */
    protected function crearCuenta(
        int $idUsuario,
        int $idRol,
        string $curp,
        string $nombre,
        string $paterno,
        string $materno,
        bool $activo = true
    ): int {
        DB::table('usuario')->insert([
            'usua_id_usuario' => $idUsuario,
            'usua_id_rol' => $idRol,
            'usua_clave_acceso' => Hash::make('CLAVE-DE-PRUEBA'),
            'usua_activo' => $activo,
        ]);

        DB::table('persona')->insert([
            'pers_id_usuario' => $idUsuario,
            'pers_clave_inegi' => '009',
            'pers_curp' => $curp,
            'pers_nombre' => $nombre,
            'pers_apellido_paterno' => $paterno,
            'pers_apellido_materno' => $materno,
            'pers_fecha_registro' => '2026-08-01',
        ]);

        return $idUsuario;
    }

    /**
     * Datos válidos para el formulario de alta. Se sobreescribe lo que cada
     * prueba necesite cambiar.
     *
     * @param array<string, mixed> $cambios
     * @return array<string, mixed>
     */
    protected function datosDeAlta(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Nueva',
            'primer_apellido' => 'Cuenta',
            'segundo_apellido' => 'Administrativa',
            'curp' => 'NUCA900101MDFXYZ01',
            'entidad_federativa' => '009',
            'rol_id' => self::ROL_ADMIN_UIF,
            'clave' => 'clave-larga-de-prueba',
        ], $cambios);
    }
}
