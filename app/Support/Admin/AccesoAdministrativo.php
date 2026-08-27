<?php

namespace App\Support\Admin;

use App\Models\Usuario;

/**
 * AccesoAdministrativo
 *
 * Responsabilidad: decidir a qué pantalla entra cada quien y qué privilegios
 * cuentan como administrativos.
 *
 * Vive aparte porque la respuesta se necesita en tres momentos distintos —al
 * iniciar sesión, al volver a /login con sesión abierta y al armar el tablero—
 * y las tres tienen que coincidir: un administrador de área que aterrice en una
 * pantalla que no puede abrir se lleva un 403 en la cara al entrar.
 */
class AccesoAdministrativo
{
    public const VALIDACION_REGISTRO = 'Validación Registro';

    public const GESTIONAR_PAGOS = 'Gestionar Pagos';

    public const GESTIONAR_REFERENCIAS = 'Gestionar Referencias';

    public const GESTIONAR_SEDES = 'Gestionar Sedes';

    public const GESTIONAR_USUARIOS = 'Gestionar usuarios';

    public const GENERAR_REPORTES = 'Generación Reportes';

    /**
     * Privilegio -> pantalla de entrada, en orden de precedencia.
     *
     * Quien administra usuarios es el rol sin límites y entra al tablero, que
     * es el único lugar donde ve todo lo suyo junto. A los demás el tablero les
     * sobra: se les manda directo a su bandeja, que es a lo que vienen.
     */
    private const DESTINOS = [
        self::GESTIONAR_USUARIOS => 'admin.dashboard',
        self::VALIDACION_REGISTRO => 'admin.personas.index',
        self::GESTIONAR_PAGOS => 'admin.pagos.index',
        self::GESTIONAR_REFERENCIAS => 'admin.referencias.index',
        self::GESTIONAR_SEDES => 'admin.sedes.index',
        self::GENERAR_REPORTES => 'admin.resultados.index',
    ];

    /**
     * Ruta nombrada donde aterriza el usuario. Quien no tiene ningún privilegio
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
     * @return array<int, string>
     */
    public function privilegiosAdministrativos(): array
    {
        return array_keys(self::DESTINOS);
    }

    /**
     * Basta un privilegio del catálogo para pisar la zona administrativa; qué
     * puede hacer ahí dentro lo deciden los permisos de cada módulo.
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
