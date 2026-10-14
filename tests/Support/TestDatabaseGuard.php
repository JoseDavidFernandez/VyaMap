<?php

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;
use Throwable;

/**
 * Impide que las pruebas usen una base de datos distinta de la de pruebas.
 *
 * Se llama desde Tests\CreatesApplication justo después de arrancar la
 * aplicación: Laravel crea la aplicación (refreshApplication) antes de
 * ejecutar los traits como RefreshDatabase (setUpTraits), así que nada puede
 * migrar ni vaciar tablas si esta comprobación falla.
 *
 * Comprueba, en este orden y sin imprimir credenciales:
 *   1. Entorno de pruebas (APP_ENV=testing y ejecución desde PHPUnit).
 *   2. Se ha cargado .env.testing (no .env ni una caché de configuración).
 *   3. Conexión por defecto MySQL con la base autorizada en la configuración.
 *   4. Base de la conexión resuelta (incluye DATABASE_URL), sin conectar.
 *   5. Base real del servidor (SELECT DATABASE()).
 *
 * La lista es deliberadamente cerrada: para otra base de pruebas legítima,
 * documentarla y añadirla aquí; no se admite cualquier nombre con "test".
 */
final class TestDatabaseGuard
{
    public const ALLOWED_DATABASES = ['vyamap_testing'];

    public const ALLOWED_DRIVERS = ['mysql'];

    public const ENVIRONMENT_FILE = '.env.testing';

    /** Directorios temporales de TestDatabaseGuardTest (VYAMAP_TEST_ENV_PATH). */
    public const ENV_PATH_PREFIX = 'vyamap-db-guard-';

    public static function check(Application $app): void
    {
        if (! $app->environment('testing') || ! $app->runningUnitTests()) {
            self::abort("el entorno es «{$app->environment()}», no «testing» bajo PHPUnit");
        }

        // Sin .env.testing Laravel cargaría .env (desarrollo); con la caché de
        // configuración no cargaría ninguno. En ambos casos se detiene aquí,
        // antes de resolver ninguna conexión y sin depender de MySQL.
        if ($app->environmentFile() !== self::ENVIRONMENT_FILE || ! is_file($app->environmentFilePath())) {
            self::abort('falta la configuración de pruebas: no se ha cargado ' . self::ENVIRONMENT_FILE
                . " (archivo cargado: «{$app->environmentFile()}»)");
        }

        $config = $app['config'];
        $connection = (string) $config->get('database.default');
        $driver = $config->get("database.connections.{$connection}.driver");

        if (! in_array($driver, self::ALLOWED_DRIVERS, true)) {
            self::abort("la conexión «{$connection}» usa el driver «{$driver}»");
        }

        self::assertAllowed('configuración', $config->get("database.connections.{$connection}.database"), $connection);

        // La conexión resuelta aplica DATABASE_URL; crearla no abre la conexión.
        $db = $app['db']->connection($connection);
        self::assertAllowed('conexión resuelta', $db->getDatabaseName(), $connection);

        try {
            $live = $db->selectOne('select database() as name')?->name;
        } catch (Throwable $exception) {
            // Solo la clase y el código SQLSTATE: el mensaje puede incluir el usuario.
            self::abort("no se ha podido comprobar la base real de «{$connection}» (" . $exception::class . ' ' . $exception->getCode() . ')');
        }

        self::assertAllowed('servidor', $live, $connection);
    }

    private static function assertAllowed(string $source, mixed $database, string $connection): void
    {
        if (! is_string($database) || ! in_array($database, self::ALLOWED_DATABASES, true)) {
            $name = is_string($database) ? $database : get_debug_type($database);

            self::abort("la base de datos ({$source}) de «{$connection}» es «{$name}»");
        }
    }

    private static function abort(string $reason): never
    {
        throw new RuntimeException(
            'TestDatabaseGuard: pruebas detenidas antes de tocar la base de datos: ' . $reason
            . '. Solo se permite ' . implode(', ', self::ALLOWED_DATABASES)
            . ' (phpunit.xml fija DB_DATABASE; revisa también .env.testing, DATABASE_URL y la caché de configuración).'
        );
    }
}
