<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\Support\TestDatabaseGuard;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Solo para TestDatabaseGuardTest: simula otro directorio de archivos
        // .env (p. ej. sin .env.testing) en un proceso aparte. Solo se aceptan
        // los directorios temporales que crea esa prueba; cualquier otra ruta
        // se ignora. TestDatabaseGuard se aplica igualmente después.
        if ($path = self::guardTestEnvironmentPath()) {
            $app->useEnvironmentPath($path);
        }

        $app->make(Kernel::class)->bootstrap();

        // Antes de que RefreshDatabase (setUpTraits) pueda migrar nada.
        TestDatabaseGuard::check($app);

        return $app;
    }

    private static function guardTestEnvironmentPath(): ?string
    {
        $requested = (string) ($_SERVER['VYAMAP_TEST_ENV_PATH'] ?? '');

        if ($requested === '') {
            return null; // realpath('') devolvería el directorio actual.
        }

        $path = realpath($requested);
        $temporary = realpath(sys_get_temp_dir());

        return $path !== false && $temporary !== false
            && dirname($path) === $temporary
            && str_starts_with(basename($path), TestDatabaseGuard::ENV_PATH_PREFIX)
            ? $path
            : null;
    }
}
