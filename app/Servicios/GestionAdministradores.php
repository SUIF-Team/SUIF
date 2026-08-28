<?php

namespace App\Servicios;

use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * GestionAdministradores
 *
 * Responsabilidad: alta, edición y baja de las cuentas que operan el sistema.
 *
 * Un administrador no es una entidad propia: es un USUARIO cuyo rol es uno de
 * los tres administrativos, más su PERSONA, que es donde vive la CURP con la
 * que inicia sesión. Por eso cada alta escribe las dos tablas y todo va en
 * transacción.
 */
class GestionAdministradores
{
    public const SUPERUSUARIO = 'Superusuario';

    public const ADMIN_UIF = 'Admin UIF';

    public const ADMIN_DEC = 'Admin DEC';

    /**
     * Los roles que este módulo administra, con lo que hace cada uno.
     *
     * El orden es el que ve quien captura: primero los de área, que son el
     * alta del día a día, y al final el que no tiene límites.
     *
     * Los nombres son los que guarda ROL_TIPO_ROL, que mide 15 caracteres. La
     * etiqueta es aparte porque en pantalla se lee mejor completa.
     *
     * @var array<string, array{etiqueta: string, descripcion: string}>
     */
    private const ROLES = [
        self::ADMIN_UIF => [
            'etiqueta' => 'Administrador UIF',
            'descripcion' => 'Revisa y valida los pre-registros y la documentación de cada expediente.',
        ],
        self::ADMIN_DEC => [
            'etiqueta' => 'Administrador DEC',
            'descripcion' => 'Valida los comprobantes de pago y gestiona el catálogo de referencias bancarias.',
        ],
        self::SUPERUSUARIO => [
            'etiqueta' => 'Superusuario',
            'descripcion' => 'Acceso total a los módulos, con la capacidad de crear y administrar usuarios.',
        ],
    ];

    /**
     * Renglones de la bandeja ya filtrados, más el resumen del encabezado.
     *
     * @param array<string, mixed> $filtros
     * @return array{administradores: Collection<int, array>, resumen: array<string, int>}
     */
    public function bandeja(array $filtros = []): array
    {
        $todos = $this->filas();

        $buscar = mb_strtolower(trim((string) ($filtros['buscar'] ?? '')), 'UTF-8');
        $rol = trim((string) ($filtros['rol'] ?? ''));
        $estatus = trim((string) ($filtros['estatus'] ?? ''));

        $filtrados = $todos->filter(function (array $fila) use ($buscar, $rol, $estatus): bool {
            if ($buscar !== '') {
                $texto = mb_strtolower($fila['nombre'].' '.$fila['curp'], 'UTF-8');

                if (!str_contains($texto, $buscar)) {
                    return false;
                }
            }

            if ($rol !== '' && $fila['rol'] !== $rol) {
                return false;
            }

            if ($estatus === 'activos' && !$fila['activo']) {
                return false;
            }

            if ($estatus === 'inactivos' && $fila['activo']) {
                return false;
            }

            return true;
        })->values();

        return [
            'administradores' => $filtrados,
            'resumen' => [
                'total' => $todos->count(),
                'activos' => $todos->where('activo', true)->count(),
                'inactivos' => $todos->where('activo', false)->count(),
                'superusuarios' => $todos->where('rol', self::SUPERUSUARIO)->where('activo', true)->count(),
            ],
        ];
    }

    /**
     * Roles que el formulario ofrece, con su id real.
     *
     * Se resuelven por nombre y no por id fijo: los identificadores dependen
     * de en qué orden se sembró cada base.
     *
     * @return Collection<int, array{id: int, nombre: string, etiqueta: string, descripcion: string}>
     */
    public function rolesAsignables(): Collection
    {
        $orden = array_keys(self::ROLES);

        return DB::table('rol')
            ->whereIn('rol_tipo_rol', $orden)
            ->get()
            ->sortBy(fn (object $rol): int => (int) array_search($rol->rol_tipo_rol, $orden, true))
            ->map(fn (object $rol): array => [
                'id' => (int) $rol->rol_id_rol,
                'nombre' => (string) $rol->rol_tipo_rol,
                'etiqueta' => self::ROLES[$rol->rol_tipo_rol]['etiqueta'],
                'descripcion' => self::ROLES[$rol->rol_tipo_rol]['descripcion'],
            ])
            ->values();
    }

    /**
     * Un administrador por su usuario, para llenar el formulario de edición.
     *
     * @return array<string, mixed>
     */
    public function administrador(int $idUsuario): array
    {
        $fila = $this->filas()->firstWhere('id_usuario', $idUsuario);

        if (!$fila) {
            throw new DomainException('El administrador indicado no existe.');
        }

        return $fila;
    }

    /**
     * Devuelve el identificador del usuario recién creado.
     *
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): int
    {
        return DB::transaction(function () use ($datos): int {
            $rol = $this->rolAsignable($datos['rol_id']);
            $this->verificarEntidad((string) $datos['entidad_federativa']);
            $this->verificarCurpLibre((string) $datos['curp']);

            /* Las tablas se cargaron con identificadores explícitos desde los
               scripts SQL, así que la secuencia puede haber quedado atrás y el
               alta chocaría con una llave existente. */
            $this->sincronizarSecuencia('usuario', 'usua_id_usuario');
            $this->sincronizarSecuencia('persona', 'pers_id_persona');

            $idUsuario = (int) DB::table('usuario')->insertGetId([
                'usua_id_rol' => (int) $rol->rol_id_rol,
                'usua_clave_acceso' => Hash::make((string) $datos['clave']),
                'usua_activo' => true,
            ], 'usua_id_usuario');

            DB::table('persona')->insert([
                'pers_id_usuario' => $idUsuario,
                'pers_clave_inegi' => (string) $datos['entidad_federativa'],
                'pers_curp' => (string) $datos['curp'],
                'pers_nombre' => (string) $datos['nombre'],
                'pers_apellido_paterno' => (string) $datos['primer_apellido'],
                'pers_apellido_materno' => (string) $datos['segundo_apellido'],
                'pers_fecha_registro' => Carbon::now()->toDateString(),
            ]);

            return $idUsuario;
        });
    }

    /**
     * La clave vacía conserva la que ya tenía: cambiar un apellido no debería
     * obligar a inventarle una contraseña nueva a nadie.
     *
     * @param array<string, mixed> $datos
     */
    public function actualizar(int $idUsuario, array $datos, int $idEnSesion): void
    {
        DB::transaction(function () use ($idUsuario, $datos, $idEnSesion): void {
            $administrador = $this->bloquear($idUsuario);
            $rol = $this->rolAsignable($datos['rol_id']);
            $this->verificarEntidad((string) $datos['entidad_federativa']);
            $this->verificarCurpLibre((string) $datos['curp'], $idUsuario);

            $cambiaDeRol = (int) $administrador->usua_id_rol !== (int) $rol->rol_id_rol;

            /* Degradarse a uno mismo deja la sesión abierta sobre una pantalla
               que ya no se puede abrir, y si era el único Superusuario nadie
               puede deshacerlo. */
            if ($cambiaDeRol && $idUsuario === $idEnSesion) {
                throw new DomainException(
                    'No puedes cambiar tu propio tipo de administrador. Pídele el cambio a otro Superusuario.'
                );
            }

            if ($cambiaDeRol && $administrador->rol_tipo_rol === self::SUPERUSUARIO) {
                $this->verificarNoEsElUltimoSuperusuario($administrador, 'cambiar de tipo');
            }

            $credenciales = ['usua_id_rol' => (int) $rol->rol_id_rol];

            if ((string) $datos['clave'] !== '') {
                $credenciales['usua_clave_acceso'] = Hash::make((string) $datos['clave']);
            }

            DB::table('usuario')
                ->where('usua_id_usuario', $idUsuario)
                ->update($credenciales);

            DB::table('persona')
                ->where('pers_id_usuario', $idUsuario)
                ->update([
                    'pers_clave_inegi' => (string) $datos['entidad_federativa'],
                    'pers_curp' => (string) $datos['curp'],
                    'pers_nombre' => (string) $datos['nombre'],
                    'pers_apellido_paterno' => (string) $datos['primer_apellido'],
                    'pers_apellido_materno' => (string) $datos['segundo_apellido'],
                ]);
        });
    }

    /**
     * La baja retira el acceso y conserva el renglón: PERSONA y USUARIO son el
     * rastro de quién dictaminó cada expediente, y borrarlos rompería la
     * trazabilidad de los trámites ya resueltos.
     */
    public function desactivar(int $idUsuario, int $idEnSesion): void
    {
        if ($idUsuario === $idEnSesion) {
            throw new DomainException('No puedes darte de baja a ti mismo.');
        }

        DB::transaction(function () use ($idUsuario): void {
            $administrador = $this->bloquear($idUsuario);

            if (!$administrador->usua_activo) {
                return;
            }

            if ($administrador->rol_tipo_rol === self::SUPERUSUARIO) {
                $this->verificarNoEsElUltimoSuperusuario($administrador, 'dar de baja');
            }

            DB::table('usuario')
                ->where('usua_id_usuario', $idUsuario)
                ->update(['usua_activo' => false]);
        });
    }

    public function reactivar(int $idUsuario): void
    {
        DB::transaction(function () use ($idUsuario): void {
            $administrador = $this->bloquear($idUsuario);

            if ($administrador->usua_activo) {
                return;
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

            $concedidos = 0;

            foreach ($privilegios as $nombre) {
                $idPrivilegio = DB::table('privilegio')
                    ->where('priv_privilegio', $nombre)
                    ->value('priv_id_privilegio');

                if (!$idPrivilegio) {
                    $idPrivilegio = DB::table('privilegio')
                        ->insertGetId(['priv_privilegio' => $nombre], 'priv_id_privilegio');
                }

                $yaLoTiene = DB::table('privilegio_rol')
                    ->where('ropr_id_rol', $idRol)
                    ->where('ropr_id_privilegio', $idPrivilegio)
                    ->exists();

                if ($yaLoTiene) {
                    continue;
                }

                /* PRIVILEGIO_ROL no tiene índice único sobre el par, así que
                   la guarda contra duplicados es esta consulta y no la base. */
                DB::table('privilegio_rol')->insert([
                    'ropr_id_rol' => $idRol,
                    'ropr_id_privilegio' => $idPrivilegio,
                ]);

                $concedidos++;
            }

            return $concedidos;
        });
    }

    /**
     * Alinea la secuencia con el máximo real de la tabla.
     *
     * Sólo aplica a PostgreSQL: setval no existe en SQLite y la suite de
     * pruebas corre ahí.
     *
     * El nombre de la tabla y el de la columna se interpolan porque no pueden
     * ir como parámetro enlazado. Nunca vienen de una petición: sólo se llama
     * con literales de esta clase, y el filtro de abajo lo deja explícito.
     */
    public function sincronizarSecuencia(string $tabla, string $columna): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([$tabla, $columna] as $identificador) {
            if (preg_match('/^[a-z_]+$/', $identificador) !== 1) {
                throw new DomainException('Identificador de secuencia no válido.');
            }
        }

        DB::statement(
            'SELECT setval(pg_get_serial_sequence(?, ?),'
            .' COALESCE((SELECT MAX('.$columna.') FROM '.$tabla.'), 1),'
            .' (SELECT COUNT(*) > 0 FROM '.$tabla.'))',
            [$tabla, $columna]
        );
    }

    /**
     * Un renglón por administrador, con su rol y su estatus de acceso.
     *
     * La lista blanca de roles es lo que mantiene fuera de esta bandeja a las
     * personas solicitantes: aquí sólo se administran cuentas de operación.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function filas(): Collection
    {
        return DB::table('usuario as u')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->join('persona as p', 'p.pers_id_usuario', '=', 'u.usua_id_usuario')
            ->whereIn('r.rol_tipo_rol', array_keys(self::ROLES))
            ->orderBy('p.pers_apellido_paterno')
            ->orderBy('p.pers_apellido_materno')
            ->orderBy('p.pers_nombre')
            ->select(
                'u.usua_id_usuario',
                'u.usua_id_rol',
                'u.usua_activo',
                'r.rol_tipo_rol',
                'p.pers_curp',
                'p.pers_nombre',
                'p.pers_apellido_paterno',
                'p.pers_apellido_materno',
                'p.pers_clave_inegi',
                'p.pers_fecha_registro'
            )
            ->get()
            ->map(function (object $fila): array {
                $rol = (string) $fila->rol_tipo_rol;

                return [
                    'id_usuario' => (int) $fila->usua_id_usuario,
                    'id_rol' => (int) $fila->usua_id_rol,
                    'rol' => $rol,
                    'rol_etiqueta' => self::ROLES[$rol]['etiqueta'] ?? $rol,
                    'curp' => (string) $fila->pers_curp,
                    'nombre' => trim(sprintf(
                        '%s %s %s',
                        $fila->pers_nombre,
                        (string) $fila->pers_apellido_paterno,
                        (string) $fila->pers_apellido_materno
                    )),
                    'nombre_pila' => (string) $fila->pers_nombre,
                    'primer_apellido' => (string) $fila->pers_apellido_paterno,
                    'segundo_apellido' => (string) $fila->pers_apellido_materno,
                    'entidad_federativa' => (string) $fila->pers_clave_inegi,
                    'fecha_registro' => $fila->pers_fecha_registro,
                    /* PostgreSQL devuelve booleano y SQLite un entero: se
                       normaliza aquí para que la vista no tenga que saberlo. */
                    'activo' => (bool) $fila->usua_activo,
                ];
            })
            ->values();
    }

    /**
     * Toma el renglón del administrador y lo retiene hasta el fin de la
     * transacción, para que dos Superusuarios simultáneos no lo modifiquen a
     * la vez.
     */
    private function bloquear(int $idUsuario): object
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

        $administrador->usua_activo = (bool) $administrador->usua_activo;

        return $administrador;
    }

    /**
     * Sólo los tres roles del módulo son asignables. Esto es lo que impide que
     * un formulario manipulado convierta a alguien en Persona —o al revés— por
     * la puerta de atrás.
     */
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
     * PERSONA.PERS_CURP no tiene índice único, así que la base no puede
     * rechazar un duplicado por sí sola.
     *
     * Repetir aquí la comprobación de la validación estrecha la ventana —el
     * renglón que exista queda retenido hasta el fin de la transacción— pero
     * no la cierra: sin índice único, dos altas simultáneas de una CURP que
     * todavía no existe pueden pasar las dos. Cerrarla de verdad pide un
     * UNIQUE sobre la columna, y el esquema es del responsable de la base.
     */
    private function verificarCurpLibre(string $curp, ?int $exceptoUsuario = null): void
    {
        $consulta = DB::table('persona')->where('pers_curp', $curp);

        if ($exceptoUsuario !== null) {
            $consulta->where('pers_id_usuario', '!=', $exceptoUsuario);
        }

        if ($consulta->lockForUpdate()->first()) {
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
     * administre las cuentas, y desde la interfaz ya no habría forma de
     * repararlo: haría falta volver a la consola.
     */
    private function verificarNoEsElUltimoSuperusuario(object $administrador, string $accion): void
    {
        $quedaOtro = DB::table('usuario as u')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->where('r.rol_tipo_rol', self::SUPERUSUARIO)
            ->where('u.usua_id_usuario', '!=', $administrador->usua_id_usuario)
            ->where('u.usua_activo', true)
            ->exists();

        if (!$quedaOtro) {
            throw new DomainException(sprintf(
                'No puedes %s al único Superusuario activo: el sistema se quedaría sin quien administre las cuentas.',
                $accion
            ));
        }
    }
}
