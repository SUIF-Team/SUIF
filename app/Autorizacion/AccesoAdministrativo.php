<?php

namespace App\Autorizacion;

use App\Models\Usuario;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * AccesoAdministrativo
 *
 * Responsabilidad: nombrar los privilegios del catálogo, decidir en qué
 * pantalla aterriza cada quien y quién es administrador o persona.
 *
 * Está aparte de los gates porque la misma respuesta hace falta en dos
 * momentos distintos —al iniciar sesión y al armar el tablero— y los dos
 * tienen que coincidir. Si no, un administrador de área entra a una pantalla
 * que su rol no puede abrir y lo primero que ve es un 403.
 */
class AccesoAdministrativo
{
    public const VALIDACION_REGISTRO = 'Validación Registro';

    public const GESTIONAR_PAGOS = 'Gestionar Pagos';

    public const GESTIONAR_REFERENCIAS = 'Gestionar Referencias';

    public const GESTIONAR_SEDES = 'Gestionar Sedes';

    public const GESTIONAR_USUARIOS = 'Gestionar usuarios';

    public const GENERAR_REPORTES = 'Generación Reportes';

    public const GESTIONAR_CONVOCATORIAS = 'Gestionar Convocatorias';

    /**
     * Privilegio que abre la puerta -> pantalla donde se entra, en orden de
     * precedencia.
     *
     * El Superusuario es el único que administra usuarios y el único a quien
     * le sirve el tablero, porque es donde ve junto todo lo que puede abrir.
     * A un administrador de área el tablero le sobra: se le manda directo a
     * la bandeja a la que viene a trabajar.
     *
     * Convocatorias va al final y no antes: hoy sólo lo tiene el Superusuario,
     * que ya aterriza en el tablero por GESTIONAR_USUARIOS. Moverlo de lugar
     * cambiaría dónde entra alguien más el día que el privilegio se reparta.
     *
     * @var array<string, string>
     */
    private const DESTINOS = [
        self::GESTIONAR_USUARIOS => 'admin.dashboard',
        self::VALIDACION_REGISTRO => 'admin.personas.index',
        self::GESTIONAR_PAGOS => 'admin.pagos.index',
        self::GESTIONAR_REFERENCIAS => 'admin.referencias.index',
        self::GESTIONAR_SEDES => 'admin.sedes.index',
        self::GENERAR_REPORTES => 'admin.resultados.index',
        self::GESTIONAR_CONVOCATORIAS => 'admin.convocatorias.index',
    ];

    /**
     * Ruta nombrada donde arranca la sesión. Quien no tiene ningún privilegio
     * administrativo es una persona solicitante y va a su propio tablero.
     */
    public function rutaInicial(?Usuario $usuario): string
    {
        if (!$usuario) {
            return 'persona.dashboard';
        }

        foreach (self::DESTINOS as $privilegio => $ruta) {
            if ($usuario->tienePrivilegio($privilegio)) {
                return $ruta;
            }
        }

        return 'persona.dashboard';
    }

    /**
     * El catálogo completo, en el orden en que se reparte.
     *
     * @return array<int, string>
     */
    public function privilegiosAdministrativos(): array
    {
        return array_keys(self::DESTINOS);
    }

    /**
     * Basta un privilegio del catálogo para pisar la zona administrativa. Qué
     * se puede hacer una vez dentro lo decide el permiso de cada módulo.
     *
     * Quien perdió el acceso no conserva ningún privilegio, igual que en
     * Usuario::tienePrivilegio(). Es una sola consulta y no una por
     * privilegio porque la puerta de /persona la hace en cada petición, y
     * para una persona —que no tiene ninguno— serían siete.
     */
    public function esAdministrador(?Usuario $usuario): bool
    {
        if (!$usuario || !$usuario->tieneAcceso()) {
            return false;
        }

        return self::privilegiosDelCatalogo(DB::query())
            ->where('pr_admin.ropr_id_rol', $usuario->usua_id_rol)
            ->exists();
    }

    /**
     * Persona solicitante: una cuenta con acceso y sin ningún privilegio del
     * catálogo. Es la única definición del sistema; no se decide por el
     * nombre del rol, que puede cambiar o repartirse, sino por lo que el rol
     * puede hacer.
     *
     * Pide el acceso vigente para que un administrador dado de baja —que ya
     * no tiene privilegios— no pase por persona: ni entra a /persona ni
     * puede restablecer su clave desde el formulario público.
     */
    public function esPersona(?Usuario $usuario): bool
    {
        return $usuario !== null
            && $usuario->tieneAcceso()
            && !$this->esAdministrador($usuario);
    }

    /**
     * La misma frontera para las consultas que listan personas, como
     * condición de whereNotExists() sobre la columna del rol:
     *
     *     ->whereNotExists(AccesoAdministrativo::rolConPrivilegioAdministrativo('u.usua_id_rol'))
     *
     * No mira USUA_ACTIVO a propósito: un listado es historial, y quien dejó
     * de tener acceso sigue apareciendo en lo que ya hizo.
     */
    public static function rolConPrivilegioAdministrativo(string $columna_rol): Closure
    {
        return function (Builder $consulta) use ($columna_rol): void {
            self::privilegiosDelCatalogo($consulta)
                ->whereColumn('pr_admin.ropr_id_rol', $columna_rol);
        };
    }

    private static function privilegiosDelCatalogo(Builder $consulta): Builder
    {
        return $consulta->selectRaw('1')
            ->from('privilegio_rol as pr_admin')
            ->join('privilegio as p_admin', 'p_admin.priv_id_privilegio', '=', 'pr_admin.ropr_id_privilegio')
            ->whereIn('p_admin.priv_privilegio', array_keys(self::DESTINOS));
    }
}
