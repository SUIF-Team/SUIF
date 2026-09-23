<?php

namespace App\Support\Admin;

use App\Models\Usuario;

/**
 * AccesoAdministrativo
 *
 * Responsabilidad: nombrar los privilegios del catálogo y decidir en qué
 * pantalla aterriza cada quien.
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
     */
    public function esAdministrador(?Usuario $usuario): bool
    {
        if (!$usuario) {
            return false;
        }

        foreach ($this->privilegiosAdministrativos() as $privilegio) {
            if ($usuario->tienePrivilegio($privilegio)) {
                return true;
            }
        }

        return false;
    }
}
