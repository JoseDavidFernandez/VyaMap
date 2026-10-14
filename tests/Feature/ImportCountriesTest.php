<?php

namespace Tests\Feature;

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * countries:import (Fase 05): solo añade países, nunca renombra; valida
 * ambos archivos antes de escribir, simula por defecto y aplica el plan en
 * una transacción.
 *
 * Cada prueba usa un storage temporal: los CSV reales solo se copian (sin
 * modificarlos) en la prueba de AX y SJ.
 */
class ImportCountriesTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN_HEADER = '"id","code","name","continent","wikipedia_link","keywords"';

    private string $storage;

    private string $realData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        $this->realData = storage_path('app/data');
        $this->storage = sys_get_temp_dir() . '/vyamap-import-countries-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists("{$this->storage}/app/data");
        $this->app->useStoragePath($this->storage);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->storage));

        $this->assertStringStartsWith(sys_get_temp_dir(), storage_path('app/data'));
    }

    public function test_dry_run_plans_without_writing(): void
    {
        $this->files([$this->row('ES', 'Spain')], ['AX,Åland Islands']);

        $writes = $this->countWrites(fn () => $this->artisan('countries:import')
            ->expectsOutputToContain('+ ES Spain (countries.csv)')
            ->expectsOutputToContain('+ AX Åland Islands (countries_overrides.csv)')
            ->expectsOutputToContain('DRY RUN: no database records were modified.')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame(0, Country::count());
    }

    public function test_apply_adds_new_countries_from_both_files(): void
    {
        $this->files([$this->row('ES', 'Spain'), $this->row('fr', 'France')], ['AX,Åland Islands']);

        $this->artisan('countries:import', ['--apply' => true])
            ->expectsOutputToContain('CREATED: 3')
            ->assertSuccessful();

        $this->assertSame(['AX' => 'Åland Islands', 'ES' => 'Spain', 'FR' => 'France'], Country::orderBy('iso_code')->pluck('name', 'iso_code')->all());
    }

    public function test_existing_countries_keep_their_name(): void
    {
        $spain = Country::create(['name' => 'España', 'iso_code' => 'ES']);
        $before = $spain->fresh()->toArray();
        $this->files([$this->row('ES', 'Spain')], []);

        $this->artisan('countries:import', ['--apply' => true])
            ->expectsOutputToContain('= ES: se conserva «España» (el CSV dice «Spain»)')
            ->expectsOutputToContain('CREATED: 0')
            ->assertSuccessful();

        $this->assertSame($before, $spain->fresh()->toArray());
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->files([$this->row('ES', 'Spain')], ['AX,Åland Islands']);

        $this->artisan('countries:import', ['--apply' => true])->assertSuccessful();
        $after = $this->countries();

        $writes = $this->countWrites(fn () => $this->artisan('countries:import', ['--apply' => true])
            ->expectsOutputToContain('CREATED: 0')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($after, $this->countries());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidRows(): array
    {
        return [
            'tres letras' => ['ESP', 'código ISO no válido «ESP»'],
            'una letra' => ['E', 'código ISO no válido «E»'],
            'dígitos' => ['E1', 'código ISO no válido «E1»'],
            'vacío' => ['', 'código ISO no válido «»'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRows')]
    public function test_invalid_iso_codes_are_skipped(string $code, string $reason): void
    {
        $this->files([$this->row($code, 'Somewhere'), $this->row('ES', 'Spain')], []);

        $this->artisan('countries:import', ['--apply' => true])
            ->expectsOutputToContain("countries.csv línea 2: {$reason}")
            ->expectsOutputToContain('CREATED: 1')
            ->assertSuccessful();

        $this->assertSame(['ES'], Country::pluck('iso_code')->all());
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function structuralErrors(): array
    {
        return [
            'principal inexistente' => ['countries.csv', null, 'CSV not found or unreadable: countries.csv'],
            'principal vacío' => ['countries.csv', '', 'CSV is empty: countries.csv'],
            'principal sin columna' => ['countries.csv', "\"id\",\"code\"\n1,\"ES\"", "Missing required column 'name' in countries.csv"],
            'overrides inexistente' => ['countries_overrides.csv', null, 'CSV not found or unreadable: countries_overrides.csv'],
            'overrides vacío' => ['countries_overrides.csv', '', 'CSV is empty: countries_overrides.csv'],
            'overrides sin columna' => ['countries_overrides.csv', "name\nÅland Islands", "Missing required column 'code' in countries_overrides.csv"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('structuralErrors')]
    public function test_structural_errors_in_either_file_fail_without_writing(string $file, ?string $contents, string $message): void
    {
        $this->files([$this->row('ES', 'Spain')], ['AX,Åland Islands']);
        $path = storage_path("app/data/{$file}");
        $contents === null ? unlink($path) : file_put_contents($path, $contents);

        $writes = $this->countWrites(fn () => $this->artisan('countries:import', ['--apply' => true])
            ->expectsOutputToContain($message)
            ->assertFailed());

        $this->assertSame(0, $writes);
        $this->assertSame(0, Country::count());
    }

    /**
     * @return array<string, array{list<string>, list<string>, string}>
     */
    public static function contradictoryCodes(): array
    {
        return [
            'en el principal' => [['"1","ES","Spain","EU","",""', '"2","ES","Kingdom of Spain","EU","",""'], [], 'Conflicting names for ES'],
            'en overrides' => [[], ['AX,Åland Islands', 'AX,Aland'], 'Conflicting names for AX'],
            'entre archivos' => [['"1","AX","Aland","EU","",""'], ['AX,Åland Islands'], 'Conflicting names for AX'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('contradictoryCodes')]
    public function test_contradictory_duplicate_codes_abort_without_writing(array $main, array $overrides, string $message): void
    {
        $this->files([$this->row('FR', 'France'), ...$main], $overrides);

        $writes = $this->countWrites(fn () => $this->artisan('countries:import', ['--apply' => true])
            ->expectsOutputToContain($message)
            ->assertFailed());

        $this->assertSame(0, $writes);
        $this->assertSame(0, Country::count());
    }

    public function test_summary_with_valid_skipped_and_repeated_rows(): void
    {
        Country::create(['name' => 'España', 'iso_code' => 'ES']);

        $this->files([
            $this->row('ES', 'Spain'),                 // existente, otro nombre
            $this->row('FR', 'France'),                // nuevo
            $this->row('FR', 'France'),                // repetido, mismo nombre
            $this->row('XYZ', 'Bad'),                  // omitido: ISO
            $this->row('IT', ''),                      // omitido: nombre vacío
            $this->row('PT', str_repeat('x', 101)),    // omitido: nombre largo
            '"1","DE"',                                // omitido: mal formada
        ], ['AX,Åland Islands']);

        $this->artisan('countries:import', ['--apply' => true])
            ->expectsTable(['resultado', 'número'], [
                ['filas leídas (ambos archivos)', 8],
                ['países añadidos', 2],
                ['países ya existentes (sin cambios)', 1],
                ['  de ellos con otro nombre en el CSV (se conserva el actual)', 1],
                ['filas repetidas (mismo ISO y nombre)', 1],
                ['filas omitidas', 4],
            ])
            ->expectsOutputToContain('CREATED: 2')
            ->assertSuccessful();

        $this->assertSame(['AX', 'ES', 'FR'], Country::orderBy('iso_code')->pluck('iso_code')->all());
    }

    public function test_a_failing_insert_rolls_back_the_whole_plan(): void
    {
        $this->files([$this->row('ES', 'Spain'), $this->row('FR', 'France')], []);

        $inserts = 0;
        Country::creating(function () use (&$inserts) {
            if (++$inserts === 2) {
                throw new RuntimeException('simulated write failure');
            }
        });

        try {
            Artisan::call('countries:import', ['--apply' => true]);
            $this->fail('The command should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated write failure', $exception->getMessage());
        }

        $this->assertSame(0, Country::count());
    }

    public function test_current_files_still_add_ax_and_sj_from_the_overrides(): void
    {
        foreach (['countries.csv', 'countries_overrides.csv'] as $file) {
            copy("{$this->realData}/{$file}", storage_path("app/data/{$file}"));
        }

        $this->artisan('countries:import', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Åland Islands', Country::where('iso_code', 'AX')->value('name'));
        $this->assertSame('Svalbard & Jan Mayen', Country::where('iso_code', 'SJ')->value('name'));
        $this->assertSame(251, Country::count());

        $writes = $this->countWrites(fn () => $this->artisan('countries:import', ['--apply' => true])
            ->expectsOutputToContain('CREATED: 0')
            ->assertSuccessful());
        $this->assertSame(0, $writes);
    }

    private function files(array $mainRows, array $overrideRows): void
    {
        file_put_contents(storage_path('app/data/countries.csv'), implode("\n", [self::MAIN_HEADER, ...$mainRows]) . "\n");
        file_put_contents(storage_path('app/data/countries_overrides.csv'), implode("\n", ['code,name', ...$overrideRows]));
    }

    private function row(string $code, string $name): string
    {
        return '"1","' . $code . '","' . $name . '","EU","https://example.test",""';
    }

    private function countries(): array
    {
        return Country::query()->orderBy('id')->get()->toArray();
    }

    private function countWrites(callable $callback): int
    {
        $writes = 0;

        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes++;
            }
        });

        $callback();

        return $writes;
    }
}
