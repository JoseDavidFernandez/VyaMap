<?php

namespace Tests\Feature;

use App\Models\Airport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * airports:enrich-coordinates (Fase 05): solo completa pares totalmente
 * vacíos, valida las coordenadas, simula por defecto y aplica el plan en una
 * transacción.
 *
 * Cada prueba usa un storage temporal con su propio CSV: los datos reales
 * (storage/app/data) nunca se leen ni se modifican.
 */
class EnrichAirportCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = '"id","ident","type","name","latitude_deg","longitude_deg","iso_country","icao_code","iata_code"';

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        $this->storage = sys_get_temp_dir() . '/vyamap-enrich-coordinates-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists("{$this->storage}/app/data");
        $this->app->useStoragePath($this->storage);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->storage));

        $this->assertStringStartsWith(sys_get_temp_dir(), storage_path('app/data'));
    }

    public function test_dry_run_plans_without_writing(): void
    {
        $this->airport('MAD');
        $this->csv([$this->row('MAD', '40.4719', '-3.5626')]);
        $before = $this->airports();

        $writes = $this->countWrites(fn () => $this->artisan('airports:enrich-coordinates')
            ->expectsOutputToContain('+ MAD: 40.4719, -3.5626')
            ->expectsOutputToContain('DRY RUN: no database records were modified.')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->airports());
    }

    public function test_apply_fills_airports_with_both_coordinates_missing(): void
    {
        $mad = $this->airport('MAD');
        $this->csv([$this->row('MAD', '40.4719', '-3.5626')]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();

        $this->assertEquals(40.4719, (float) $mad->fresh()->latitude);
        $this->assertEquals(-3.5626, (float) $mad->fresh()->longitude);
    }

    public function test_existing_coordinate_pairs_are_never_overwritten(): void
    {
        $mad = $this->airport('MAD', 40.0, -3.0);
        $this->csv([$this->row('MAD', '40.4719', '-3.5626')]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertEquals(40.0, (float) $mad->fresh()->latitude);
        $this->assertEquals(-3.0, (float) $mad->fresh()->longitude);
    }

    /**
     * @return array<string, array{?float, ?float}>
     */
    public static function partialCoordinates(): array
    {
        return ['solo latitud' => [40.0, null], 'solo longitud' => [null, -3.0]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('partialCoordinates')]
    public function test_partial_coordinates_are_left_untouched(?float $latitude, ?float $longitude): void
    {
        $mad = $this->airport('MAD', $latitude, $longitude);
        $before = $mad->fresh()->toArray();
        $this->csv([$this->row('MAD', '40.4719', '-3.5626')]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('MAD: solo tiene una coordenada; no se completa para no sobrescribirla')
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertSame($before, $mad->fresh()->toArray());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidCoordinates(): array
    {
        return [
            'latitud > 90' => ['90.0001', '-3.5'],
            'latitud < -90' => ['-91', '-3.5'],
            'longitud > 180' => ['40.4', '180.5'],
            'longitud < -180' => ['40.4', '-181'],
            'latitud vacía' => ['', '-3.5'],
            'longitud vacía' => ['40.4', ''],
            'no numérica' => ['abc', '-3.5'],
            'infinita' => ['1e400', '-3.5'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCoordinates')]
    public function test_invalid_coordinates_are_reported_and_never_stored(string $latitude, string $longitude): void
    {
        $mad = $this->airport('MAD');
        $this->csv([$this->row('MAD', $latitude, $longitude)]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('línea 2 MAD: coordenadas no válidas')
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertNull($mad->fresh()->latitude);
        $this->assertNull($mad->fresh()->longitude);
    }

    public function test_boundary_values_are_valid(): void
    {
        $north = $this->airport('NPA');
        $south = $this->airport('SPA');
        $this->csv([$this->row('NPA', '90', '180'), $this->row('SPA', '-90', '-180')]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 2')
            ->assertSuccessful();

        $this->assertEquals(90.0, (float) $north->fresh()->latitude);
        $this->assertEquals(-180.0, (float) $south->fresh()->longitude);
    }

    public function test_valid_rows_are_applied_despite_invalid_ones(): void
    {
        $mad = $this->airport('MAD');
        $bcn = $this->airport('BCN');
        $this->csv([
            $this->row('BCN', '95', '2.0'),
            $this->row('MAD', '40.4719', '-3.5626'),
            '"x","short row"',
        ]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('línea 2 BCN: coordenadas no válidas')
            ->expectsOutputToContain('línea 4 ?: número de columnas incorrecto')
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();

        $this->assertEquals(40.4719, (float) $mad->fresh()->latitude);
        $this->assertNull($bcn->fresh()->latitude);
    }

    public function test_repeated_iata_with_different_coordinates_is_skipped(): void
    {
        $mad = $this->airport('MAD');
        $this->csv([$this->row('MAD', '40.4719', '-3.5626'), $this->row('MAD', '41.0', '-3.0')]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('MAD: IATA repetido en el CSV con coordenadas distintas')
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertNull($mad->fresh()->latitude);
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function structuralErrors(): array
    {
        return [
            'sin archivo' => [null, 'Airport dataset not found or unreadable'],
            'vacío' => ['', 'Unable to read CSV headers.'],
            'sin columna obligatoria' => ["\"iata_code\",\"latitude_deg\"\n\"MAD\",\"40.4\"", 'Missing required CSV column: longitude_deg'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('structuralErrors')]
    public function test_structural_errors_fail_without_writing(?string $contents, string $message): void
    {
        $this->airport('MAD');
        $before = $this->airports();

        if ($contents !== null) {
            file_put_contents($this->csvPath(), $contents);
        }

        $writes = $this->countWrites(fn () => $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain($message)
            ->assertFailed());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->airports());
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->airport('MAD');
        $this->csv([$this->row('MAD', '40.4719', '-3.5626')]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])->assertSuccessful();
        $after = $this->airports();

        $writes = $this->countWrites(fn () => $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($after, $this->airports());
    }

    public function test_summary_and_exit_code(): void
    {
        $this->airport('MAD');
        $this->airport('BCN', 41.29, 2.07);
        $this->airport('AGP', 36.67, null);
        $this->airport('SVQ');

        $this->csv([
            $this->row('MAD', '40.4719', '-3.5626'),   // por actualizar
            $this->row('BCN', '41.2971', '2.0785'),    // ya completo
            $this->row('AGP', '36.6749', '-4.4991'),   // parcial
            $this->row('ZZZ', '10.0', '10.0'),         // sin aeropuerto
            $this->row('SVQ', '', '-5.89'),            // no válida
            $this->row('', '1.0', '1.0'),              // sin IATA
            '"x","short row"',                         // omitida
        ]);

        $this->artisan('airports:enrich-coordinates', ['--apply' => true])
            ->expectsTable(['resultado', 'número'], [
                ['filas leídas', 7],
                ['filas sin IATA (no aplicables)', 1],
                ['aeropuertos actualizados', 1],
                ['aeropuertos ya completos', 1],
                ['omitidos: coordenadas parciales (no se sobrescriben)', 1],
                ['filas sin aeropuerto en VyaMap', 1],
                ['filas con coordenadas no válidas', 1],
                ['filas omitidas por otros motivos', 1],
            ])
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();
    }

    public function test_a_failing_write_rolls_back_the_whole_plan(): void
    {
        $this->airport('MAD');
        $this->airport('BCN');
        $this->csv([$this->row('MAD', '40.4719', '-3.5626'), $this->row('BCN', '41.2971', '2.0785')]);
        $before = $this->airports();

        $updates = 0;
        Airport::updating(function () use (&$updates) {
            if (++$updates === 2) {
                throw new RuntimeException('simulated write failure');
            }
        });

        try {
            Artisan::call('airports:enrich-coordinates', ['--apply' => true]);
            $this->fail('The command should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated write failure', $exception->getMessage());
        }

        $this->assertSame($before, $this->airports());
    }

    private function airport(string $iata, ?float $latitude = null, ?float $longitude = null): Airport
    {
        return Airport::create(['name' => "{$iata} Airport", 'iata_code' => $iata, 'latitude' => $latitude, 'longitude' => $longitude]);
    }

    private function row(string $iata, string $latitude, string $longitude): string
    {
        return implode(',', array_map(fn ($value) => '"' . $value . '"', ['1', 'X', 'large_airport', "{$iata} Airport", $latitude, $longitude, 'ES', '', $iata]));
    }

    private function csv(array $lines): void
    {
        file_put_contents($this->csvPath(), implode("\n", [self::HEADER, ...$lines]) . "\n");
    }

    private function csvPath(): string
    {
        return storage_path('app/data/airports.csv');
    }

    private function airports(): array
    {
        return Airport::query()->orderBy('id')->get()->toArray();
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
