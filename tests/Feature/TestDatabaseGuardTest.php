<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

/**
 * La suite solo puede usar vyamap_testing (Fase 05, P0.1).
 *
 * Las comprobaciones de rechazo con el arranque real se ejecutan en procesos
 * PHPUnit aparte, con un entorno limpio (env -i) y contra 127.0.0.1:1 (sin
 * servidor): aunque la protección fallara, RefreshDatabase no podría
 * conectar con ninguna base. Nunca se usa la base de desarrollo.
 */
class TestDatabaseGuardTest extends TestCase
{
    private const PROBES = 'tests/Fixtures/DatabaseGuard/';

    private const UNREACHABLE = ['DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_SOCKET' => ''];

    public function test_normal_configuration_selects_the_testing_database(): void
    {
        $this->assertSame('.env.testing', $this->app->environmentFile());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('vyamap_testing', config('database.connections.mysql.database'));
        $this->assertSame('vyamap_testing', DB::connection()->getDatabaseName());
        $this->assertSame('vyamap_testing', DB::selectOne('select database() as name')->name);
        $this->assertSame(['vyamap_testing'], TestDatabaseGuard::ALLOWED_DATABASES);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unauthorizedDatabases(): array
    {
        return [
            'desarrollo' => ['vyamap'],
            'mayúsculas' => ['VYAMAP_TESTING'],
            'contiene test' => ['vyamap_test'],
            'sufijo' => ['vyamap_testing_old'],
            'genérica' => ['testing'],
            'vacía' => [''],
            'sin valor' => [null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unauthorizedDatabases')]
    public function test_unauthorized_database_names_are_rejected_without_touching_tables(mixed $database): void
    {
        $before = $this->schemaSnapshot();

        $this->assertRejected(function () use ($database) {
            config(['database.connections.mysql.database' => $database]);
            DB::purge('mysql');
        }, '(configuración)');

        $this->assertSame($before, $this->schemaSnapshot());
    }

    public function test_database_url_override_is_rejected_before_connecting(): void
    {
        $this->assertRejected(function () {
            config(['database.connections.mysql.url' => 'mysql://nobody@127.0.0.1:1/vyamap']);
            DB::purge('mysql');
        }, '(conexión resuelta)');
    }

    public function test_other_drivers_and_environments_are_rejected(): void
    {
        $this->assertRejected(fn () => config(['database.default' => 'sqlite']), 'driver «sqlite»');

        $environment = $this->app['env'];

        try {
            $this->assertRejected(fn () => $this->app['env'] = 'local', 'no «testing»');
        } finally {
            $this->app['env'] = $environment;
        }
    }

    public function test_real_bootstrap_uses_the_testing_database(): void
    {
        [$exit, $output] = $this->runProbe('EffectiveDatabaseProbeTest.php');

        $this->assertSame(0, $exit, $this->summary($output));
        $this->assertStringContainsString('PROBE_EFFECTIVE_DATABASE=vyamap_testing', $output);
    }

    public function test_development_database_in_the_shell_environment_does_not_win(): void
    {
        [$exit, $output] = $this->runProbe('EffectiveDatabaseProbeTest.php', ['DB_DATABASE' => 'vyamap', 'DB_CONNECTION' => 'sqlite']);

        $this->assertSame(0, $exit, $this->summary($output));
        $this->assertStringContainsString('PROBE_EFFECTIVE_DATABASE=vyamap_testing', $output);
    }

    public function test_missing_env_testing_stops_before_any_connection(): void
    {
        // Directorio de entorno con solo .env (enlace al real, sin copiar
        // secretos) y sin .env.testing. El servidor es inalcanzable: el
        // resultado no depende de los permisos de MySQL.
        $directory = $this->temporaryDirectory();
        symlink(base_path('.env'), "{$directory}/.env");

        [$exit, $output] = $this->runProbe('RefreshDatabaseProbeTest.php', [...self::UNREACHABLE, 'VYAMAP_TEST_ENV_PATH' => $directory]);

        $this->assertNotSame(0, $exit, $this->summary($output));
        $this->assertStringContainsString('falta la configuración de pruebas: no se ha cargado .env.testing (archivo cargado: «.env»)', $output);
        $this->assertStoppedBeforeConnecting($output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ignoredEnvironmentPaths(): array
    {
        return [
            'fuera del directorio temporal' => ['/'],
            'temporal sin el prefijo de la prueba' => ['other-'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ignoredEnvironmentPaths')]
    public function test_env_path_hook_only_accepts_the_tests_own_temporary_directories(string $path): void
    {
        // Ninguno de los dos tiene .env.testing: si el gancho los aceptara,
        // la protección se detendría. Al ignorarlos, se usa el entorno normal.
        if ($path === 'other-') {
            $path = $this->temporaryDirectory('other-');
        }

        [$exit, $output] = $this->runProbe('EffectiveDatabaseProbeTest.php', ['VYAMAP_TEST_ENV_PATH' => $path]);

        $this->assertSame(0, $exit, $this->summary($output));
        $this->assertStringContainsString('PROBE_EFFECTIVE_DATABASE=vyamap_testing', $output);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function rejectedConfigurations(): array
    {
        return [
            'phpunit.xml con la base de desarrollo' => [['DB_DATABASE' => 'vyamap'], '(configuración) de «mysql» es «vyamap»'],
            'phpunit.xml con otra base' => [['DB_DATABASE' => 'vyamap_staging'], '«vyamap_staging»'],
            'DATABASE_URL hacia la base de desarrollo' => [['DATABASE_URL' => 'mysql://nobody@127.0.0.1:1/vyamap'], '(conexión resuelta) de «mysql» es «vyamap»'],
        ];
    }

    /**
     * @param  array<string, string>  $phpunitEnv
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedConfigurations')]
    public function test_refresh_database_never_runs_with_an_unauthorized_configuration(array $phpunitEnv, string $reason): void
    {
        [$exit, $output] = $this->runProbe('RefreshDatabaseProbeTest.php', self::UNREACHABLE, $phpunitEnv);

        $this->assertNotSame(0, $exit, $this->summary($output));
        $this->assertStringContainsString($reason, $output);
        $this->assertStoppedBeforeConnecting($output);
    }

    /**
     * La protección detuvo la prueba: ni el cuerpo ni RefreshDatabase se
     * ejecutaron y no hubo ningún intento de conexión. Sin secretos.
     */
    private function assertStoppedBeforeConnecting(string $output): void
    {
        $this->assertStringContainsString('TestDatabaseGuard: pruebas detenidas antes de tocar la base de datos', $output);
        $this->assertStringNotContainsString('PROBE_BODY_REACHED', $output);
        $this->assertStringNotContainsString('SQLSTATE', $output);
        $this->assertStringNotContainsString('Connection refused', $output);
        $this->assertOutputHasNoSecrets($output);
    }

    /**
     * Mensaje para aserciones fallidas: solo las líneas de la protección y de
     * las sondas, nunca la salida completa del proceso (podría incluir datos
     * de conexión en un error inesperado).
     */
    private function summary(string $output): string
    {
        return implode(PHP_EOL, preg_grep('/TestDatabaseGuard:|PROBE_|^(OK|Tests:)/', explode(PHP_EOL, $output)) ?: ['(sin líneas de la protección)']);
    }

    private function assertRejected(callable $misconfigure, string $reason): void
    {
        $misconfigure();

        try {
            TestDatabaseGuard::check($this->app);
            $this->fail('TestDatabaseGuard debía rechazar la configuración.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('TestDatabaseGuard: pruebas detenidas', $exception->getMessage());
            $this->assertStringContainsString($reason, $exception->getMessage());
        } finally {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => 'vyamap_testing',
                'database.connections.mysql.url' => null,
            ]);
            DB::purge('mysql');
        }
    }

    /**
     * Tablas y número de filas de vyamap_testing (solo lectura).
     */
    private function schemaSnapshot(): array
    {
        return collect(DB::select('show tables'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])
            ->all();
    }

    /**
     * Ejecuta una sonda en un proceso PHPUnit aparte con un entorno limpio.
     *
     * @param  array<string, string>  $env  Variables del proceso.
     * @param  array<string, string>  $phpunitEnv  Valores que sustituyen a los de phpunit.xml.
     * @return array{int, string}
     */
    private function runProbe(string $probe, array $env = [], array $phpunitEnv = []): array
    {
        $config = base_path('phpunit.xml');

        if ($phpunitEnv !== []) {
            $xml = file_get_contents($config);
            $xml = str_replace('bootstrap="vendor/autoload.php"', 'bootstrap="' . base_path('vendor/autoload.php') . '"', $xml);

            foreach ($phpunitEnv as $name => $value) {
                // Tanto en <env> como en <server>.
                $xml = preg_replace(
                    '/<(env|server) name="' . preg_quote($name, '/') . '" value="[^"]*"/',
                    '<$1 name="' . $name . '" value="' . htmlspecialchars($value, ENT_QUOTES) . '"',
                    $xml,
                    -1,
                    $count
                );
                $this->assertSame(2, $count, "phpunit.xml debe fijar {$name} en <env> y <server>");
            }

            $config = $this->temporaryDirectory() . '/phpunit.xml';
            file_put_contents($config, $xml);
        }

        if (($env['DB_PORT'] ?? null) === self::UNREACHABLE['DB_PORT']) {
            $socket = @fsockopen('127.0.0.1', (int) self::UNREACHABLE['DB_PORT'], $errno, $error, 1);
            $this->assertFalse($socket, 'El puerto de seguridad debe estar cerrado para ejecutar esta prueba.');
        }

        // TMPDIR: el hijo debe ver el mismo directorio temporal que esta prueba.
        $command = ['/usr/bin/env', '-i', 'PATH=' . getenv('PATH'), 'HOME=' . getenv('HOME'), 'TMPDIR=' . sys_get_temp_dir()];

        foreach ($env as $name => $value) {
            $command[] = "{$name}={$value}";
        }

        $process = new Process([
            ...$command,
            PHP_BINARY, base_path('vendor/bin/phpunit'),
            '-c', $config,
            '--do-not-cache-result', '--no-progress', '--colors=never',
            base_path(self::PROBES . $probe),
        ], base_path(), null, null, 120);

        $process->run();

        return [$process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }

    private function assertOutputHasNoSecrets(string $output): void
    {
        $values = Dotenv::parse((string) @file_get_contents(base_path('.env.testing')));

        // Solo secretos (el usuario puede coincidir con parte del nombre de la
        // base). assertFalse no imprime el valor si la aserción falla.
        foreach (['DB_PASSWORD', 'APP_KEY'] as $key) {
            if (strlen($values[$key] ?? '') >= 4) {
                $this->assertFalse(str_contains($output, $values[$key]), "La salida no debe incluir {$key}");
            }
        }
    }

    private function temporaryDirectory(string $prefix = TestDatabaseGuard::ENV_PATH_PREFIX): string
    {
        $directory = rtrim(sys_get_temp_dir(), '/') . '/' . $prefix . bin2hex(random_bytes(6));
        mkdir($directory, 0700);

        $this->beforeApplicationDestroyed(function () use ($directory) {
            foreach (glob("{$directory}/{,.}*", GLOB_BRACE) as $file) {
                if (! in_array(basename($file), ['.', '..'], true)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        });

        return $directory;
    }
}
