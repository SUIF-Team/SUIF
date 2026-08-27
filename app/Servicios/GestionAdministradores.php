<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * GestionAdministradores
 *
 * Responsabilidad: alta, edición y baja de quienes administran el sistema.
 *
 * Un administrador se guarda en dos tablas, igual que cualquier persona: USUARIO
 * lleva el rol y la clave, y PERSONA la CURP con la que inicia sesión. El acceso
 * se resuelve cruzando ambas, así que las dos se escriben juntas.
 *
 * La baja no borra: retira el acceso y conserva el renglón. PERSONA y USUARIO son
 * el rastro de quién dictaminó cada expediente, y borrarlos dejaría resoluciones
 * sin autor.
 */
class GestionAdministradores
{
    public const SUPERUSUARIO = 'Superusuario';

    public const ADMIN_UIF = 'Admin UIF';

    public const ADMIN_DEC = 'Admin DEC';

    /**
     * Los roles que este módulo da de alta, con lo que hacen. El orden es el que
     * ve quien captura: primero los de área, que son los del día a día.
     */
    private const ROLES = [
        self::ADMIN_UIF => 'Revisa y valida el pre-registro y la documentación.',
        self::ADMIN_DEC => 'Revisa y valida los pagos y el catálogo de referencias.',
        self::SUPERUSUARIO => 'Acceso a todos los módulos del sistema.',
    ];

    /**
     * Renglones de la bandeja, ya filtrados, más el resumen del encabezado.
     */
    public function bandeja(array $filtros = []): array
    {
        $todos = $this->filas();

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $rol = trim((string) ($filtros['rol'] ?? ''));
        $estatus = (string) ($filtros['estatus'] ?? '');

        $filtrados = $todos->filter(function (array $fila) use ($buscar, $rol, $estatus): bool {
            if ($buscar !== ''
                && mb_stripos($fila['nombre_completo'], $buscar) === false
                && mb_stripos($fila['curp'], $buscar) === false) {
                return false;
            }

            if ($rol !== '' && $fila['rol'] !== $rol) {
                return false;
            }

            return $estatus === '' || $fila['estatus_clave'] === $estatus;
        })->values();

        return [
            'administradores' => $filtrados,
            'resumen' => [
                'total' => $todos->count(),
                'activos' => $todos->where('activo', true)->count(),
                'inactivos' => $todos->where('activo', false)->count(),
                'superusuarios' => $todos
                    ->where('rol', self::SUPERUSUARIO)
                    ->where('activo', true)
                    ->count(),
            ],
        ];
    }

    /**
     * Roles que el formulario ofrece, con su id real. Se resuelven por nombre
     * porque los identificadores dependen de en qué orden se sembró la base.
     *
     * @return Collection<int, array{id: int, nombre: string, descripcion: string}>
     */
    public function rolesAsignables(): Collection
    {
        return DB::table('rol')
            ->whereIn('rol_tipo_rol', array_keys(self::ROLES))
            ->get()
            ->map(fn (object $rol): array => [
                'id' => (int) $rol->rol_id_rol,
                'nombre' => (string) $rol->rol_tipo_rol,
                'descripcion' => self::ROLES[$rol->rol_tipo_rol],
            ])
            ->sortBy(fn (array $rol): int => array_search($rol['nombre'], array_keys(self::ROLES), true))
            ->values();
    }

    public function administrador(int $idUsuario): array
    {
        $fila = $this->filas()->firstWhere('id', $idUsuario);

        if (!$fila) {
            throw new DomainException('El administrador indicado no existe.');
        }

        return $fila;
    }

    /**
     * Devuelve el identificador del usuario recién creado.
     */
    public function crear(array $datos): int
    {
        return DB::transaction(function () use ($datos): int {
            $rol = $this->rolAsignable($datos['rol_id']);

            $this->verificarCurpLibre($datos['curp']);
            $this->verificarEntidad($datos['entidad_federativa']);

            /* Los scripts de la base cargan identificadores explícitos, así que
               la secuencia puede haber quedado atrás y provocar llave duplicada. */
            $this->sincronizarSecuencia('usuario', 'usua_id_usuario');
            $this->sincronizarSecuencia('persona', 'pers_id_persona');

            $idUsuario = DB::table('usuario')->insertGetId([
                'usua_id_rol' => $rol->rol_id_rol,
                'usua_clave_acceso' => Hash::make($datos['clave']),
                'usua_activo' => true,
            ], 'usua_id_usuario');

            DB::table('persona')->insert([
                'pers_clave_inegi' => $datos['entidad_federativa'],
                'pers_id_usuario' => $idUsuario,
                'pers_curp' => $datos['curp'],
                'pers_nombre' => $datos['nombre'],
                'pers_apellido_paterno' => $datos['primer_apellido'],
                'pers_apellido_materno' => $datos['segundo_apellido'],
                'pers_fecha_registro' => now()->toDateString(),
            ]);

            return (int) $idUsuario;
        });
    }

    /**
     * La clave vacía conserva la que ya tenía: editar el nombre de alguien no
     * debería obligar a inventarle una contraseña nueva.
     */
    public function actualizar(int $idUsuario, array $datos, int $idEnSesion): void
    {
        DB::transaction(function () use ($idUsuario, $datos, $idEnSesion): void {
            $actual = $this->bloquearAdministrador($idUsuario);
            $rol = $this->rolAsignable($datos['rol_id']);

            if ((int) $rol->rol_id_rol !== (int) $actual->usua_id_rol) {
                if ($idUsuario === $idEnSesion) {
                    throw new DomainException('No puedes cambiar tu propio rol.');
                }

                $this->verificarNoEsElUltimoSuperusuario($actual, 'degradar');
            }

            $this->verificarCurpLibre($datos['curp'], $idUsuario);
            $this->verificarEntidad($datos['entidad_federativa']);

            $credenciales = ['usua_id_rol' => $rol->rol_id_rol];

            if (($datos['clave'] ?? '') !== '') {
                $credenciales['usua_clave_acceso'] = Hash::make($datos['clave']);
            }

            DB::table('usuario')
                ->where('usua_id_usuario', $idUsuario)
                ->update($credenciales);

            DB::table('persona')
                ->where('pers_id_usuario', $idUsuario)
                ->update([
                    'pers_clave_inegi' => $datos['entidad_federativa'],
                    'pers_curp' => $datos['curp'],
                    'pers_nombre' => $datos['nombre'],
                    'pers_apellido_paterno' => $datos['primer_apellido'],
                    'pers_apellido_materno' => $datos['segundo_apellido'],
                ]);
        });
    }

    public function desactivar(int $idUsuario, int $idEnSesion): void
    {
        DB::transaction(function () use ($idUsuario, $idEnSesion): void {
            $administrador = $this->bloquearAdministrador($idUsuario);

            /* Cerrarse la puerta a uno mismo no es una operación, es un error de
               un clic de más. */
            if ($idUsuario === $idEnSesion) {
                throw new DomainException('No puedes darte de baja a ti mismo.');
            }

            if (!$administrador->usua_activo) {
                throw new DomainException('Ese administrador ya estaba dado de baja.');
            }

            $this->verificarNoEsElUltimoSuperusuario($administrador, 'dar de baja');

            DB::table('usuario')
                ->where('usua_id_usuario', $idUsuario)
                ->update(['usua_activo' => false]);
        });
    }

    public function reactivar(int $idUsuario): void
    {
        DB::transaction(function () use ($idUsuario): void {
            $administrador = $this->bloquearAdministrador($idUsuario);

            if ($administrador->usua_activo) {
                throw new DomainException('Ese administrador ya tenía acceso.');
            }

            DB::table('usuario')
                ->where('usua_id_usuario', $idUsuario)
                ->update(['usua_activo' => true]);
        });
    }

    /**
     * Da de alta los privilegios del catálogo que aún no existan y los concede
     * al rol indicado. Lo usa el comando de consola para dejar operativo un
     * Superusuario en una base donde PRIVILEGIO todavía esté vacío.
     *
     * @param array<int, string> $privilegios
     * @return int cuántas concesiones agregó
     */
    public function concederPrivilegios(int $idRol, array $privilegios): int
    {
        return DB::transaction(function () use ($idRol, $privilegios): int {
            $this->sincronizarSecuencia('privilegio', 'priv_id_privilegio');
            $this->sincronizarSecuencia('privilegio_rol', 'ropr_id_privilegio_rol');

            foreach ($privilegios as $privilegio) {
                $existe = DB::table('privilegio')
                    ->where('priv_privilegio', $privilegio)
                    ->exists();

                if (!$existe) {
                    DB::table('privilegio')->insert(['priv_privilegio' => $privilegio]);
                }
            }

            $ids = DB::table('privilegio')
                ->whereIn('priv_privilegio', $privilegios)
                ->pluck('priv_id_privilegio')
                ->all();

            $yaTiene = DB::table('privilegio_rol')
                ->where('ropr_id_rol', $idRol)
                ->pluck('ropr_id_privilegio')
                ->all();

            $faltantes = array_diff($ids, $yaTiene);

            foreach ($faltantes as $idPrivilegio) {
                DB::table('privilegio_rol')->insert([
                    'ropr_id_privilegio' => $idPrivilegio,
                    'ropr_id_rol' => $idRol,
                ]);
            }

            return count($faltantes);
        });
    }

    /**
     * Alinea la secuencia con el máximo real de la tabla.
     *
     * Las tablas se cargaron con identificadores explícitos desde los scripts
     * SQL, así que la secuencia puede haber quedado atrás.
     */
    public function sincronizarSecuencia(string $tabla, string $columna): void
    {
        /* SQLite —el motor de las pruebas— no tiene secuencias que alinear. */
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence(?, ?),
                    COALESCE((SELECT MAX({$columna}) FROM {$tabla}), 0) + 1,
                    false)",
            [$tabla, $columna]
        );
    }

    /**
     * Un renglón por administrador, con su rol y su estatus de acceso.
     *
     * @return Collection<int, array>
     */
    private function filas(): Collection
    {
        return DB::table('usuario as u')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->join('persona as p', 'p.pers_id_usuario', '=', 'u.usua_id_usuario')
            ->leftJoin('entidad_federativa as ef', 'ef.enfe_clave_inegi', '=', 'p.pers_clave_inegi')
            ->whereIn('r.rol_tipo_rol', array_keys(self::ROLES))
            ->orderBy('p.pers_nombre')
            ->orderBy('p.pers_apellido_paterno')
            ->select([
                'u.usua_id_usuario',
                'u.usua_activo',
                'r.rol_id_rol',
                'r.rol_tipo_rol',
                'p.pers_curp',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_clave_inegi',
                'p.pers_fecha_registro',
                'ef.enfe_entidad_federativa',
            ])
            ->get()
            ->map(function (object $fila): array {
                $activo = (bool) $fila->usua_activo;

                return [
                    'id' => (int) $fila->usua_id_usuario,
                    'rol_id' => (int) $fila->rol_id_rol,
                    'rol' => (string) $fila->rol_tipo_rol,
                    'rol_descripcion' => self::ROLES[$fila->rol_tipo_rol] ?? '',
                    'curp' => (string) $fila->pers_curp,
                    'nombre' => (string) $fila->pers_nombre,
                    'primer_apellido' => (string) ($fila->pers_apellido_paterno ?? ''),
                    'segundo_apellido' => (string) ($fila->pers_apellido_materno ?? ''),
                    'nombre_completo' => trim(implode(' ', array_filter([
                        $fila->pers_nombre,
                        $fila->pers_apellido_paterno,
                        $fila->pers_apellido_materno,
                    ]))),
                    'entidad_federativa' => (string) ($fila->enfe_entidad_federativa ?? ''),
                    'clave_inegi' => (string) $fila->pers_clave_inegi,
                    'fecha_registro' => substr((string) $fila->pers_fecha_registro, 0, 10),
                    'activo' => $activo,
                    'estatus' => $activo ? 'Activo' : 'Sin acceso',
                    'estatus_clave' => $activo ? 'activo' : 'inactivo',
                ];
            });
    }

    private function bloquearAdministrador(int $idUsuario): object
    {
        $administrador = DB::table('usuario as u')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->where('u.usua_id_usuario', $idUsuario)
            ->whereIn('r.rol_tipo_rol', array_keys(self::ROLES))
            ->select('u.usua_id_usuario', 'u.usua_id_rol', 'u.usua_activo', 'r.rol_tipo_rol')
            ->lockForUpdate()
            ->first();

        if (!$administrador) {
            throw new DomainException('El administrador indicado no existe.');
        }

        return $administrador;
    }

    private function rolAsignable(mixed $idRol): object
    {
        $rol = DB::table('rol')
            ->where('rol_id_rol', (int) $idRol)
            ->whereIn('rol_tipo_rol', array_keys(self::ROLES))
            ->first();

        if (!$rol) {
            throw new DomainException('Selecciona un tipo de administrador válido.');
        }

        return $rol;
    }

    /**
     * PERSONA.PERS_CURP no tiene índice único en la base, así que la
     * comprobación de Laravel no basta: dos altas simultáneas la esquivan. Aquí
     * se repite dentro de la transacción.
     */
    private function verificarCurpLibre(string $curp, ?int $exceptoUsuario = null): void
    {
        $ocupada = DB::table('persona')
            ->where('pers_curp', $curp)
            ->when($exceptoUsuario, fn ($consulta) => $consulta->where('pers_id_usuario', '!=', $exceptoUsuario))
            ->lockForUpdate()
            ->exists();

        if ($ocupada) {
            throw new DomainException('Esa CURP ya está registrada en el sistema.');
        }
    }

    private function verificarEntidad(string $claveInegi): void
    {
        $existe = DB::table('entidad_federativa')
            ->where('enfe_clave_inegi', $claveInegi)
            ->exists();

        if (!$existe) {
            throw new DomainException('Selecciona una entidad federativa válida.');
        }
    }

    /**
     * Quedarse sin ningún Superusuario activo deja el sistema sin quien
     * administre usuarios, y desde la interfaz ya no habría cómo repararlo.
     */
    private function verificarNoEsElUltimoSuperusuario(object $administrador, string $accion): void
    {
        if ($administrador->rol_tipo_rol !== self::SUPERUSUARIO || !$administrador->usua_activo) {
            return;
        }

        $otros = DB::table('usuario as u')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->where('r.rol_tipo_rol', self::SUPERUSUARIO)
            ->where('u.usua_activo', true)
            ->where('u.usua_id_usuario', '!=', $administrador->usua_id_usuario)
            ->count();

        if ($otros === 0) {
            throw new DomainException(
                "No es posible {$accion} al único Superusuario activo: el sistema se quedaría sin quien administre usuarios."
            );
        }
    }
}
