<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Laravel 13 crea la aplicación desde bootstrap/app.php automáticamente.

    /**
     * Las pruebas crean y BORRAN tablas con Schema::dropIfExists, así que sólo
     * pueden correr contra SQLite en memoria.
     *
     * phpunit.xml ya fuerza esa conexión, pero no siempre gana: si existe
     * bootstrap/cache/config.php —lo deja `php artisan config:cache`— Laravel
     * ignora el .env y las variables de PHPUnit y usa la configuración
     * cacheada, que apunta a PostgreSQL. La suite se ejecuta entonces contra la
     * base real y la destruye.
     *
     * Por eso la comprobación va aquí y no en la configuración: es la única
     * que no se puede saltar por tener un caché viejo encima.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $conexion = DB::connection();
        $driver = $conexion->getDriverName();
        $base = $conexion->getDatabaseName();

        if ($driver !== 'sqlite' || !in_array($base, [':memory:', ''], true)) {
            $this->detener($driver, $base);
        }
    }

    private function detener(string $driver, string $base): void
    {
        throw new RuntimeException(implode("\n", [
            '',
            'PRUEBAS ABORTADAS: la conexión no es SQLite en memoria.',
            "  Conexión activa: {$driver} / {$base}",
            '',
            'La suite borra tablas con Schema::dropIfExists. Correrla contra una',
            'base real la destruye.',
            '',
            'Casi siempre es un caché de configuración viejo. Límpialo y repite:',
            '',
            '    php artisan config:clear && php artisan test',
            '',
        ]));
    }
}
