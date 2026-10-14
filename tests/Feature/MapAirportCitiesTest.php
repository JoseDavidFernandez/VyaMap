<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * airports:map-cities (Fase 05): resuelve ciudades fusionadas, no trata
 * coordenadas ausentes como (0, 0), no resuelve ambigüedades en silencio, no
 * sobrescribe asignaciones existentes y aplica el plan en una transacción.
 *
 * Cada prueba usa un storage temporal con su propio CSV: el catálogo real
 * (storage/app/data) nunca se lee ni se modifica.
 */
class MapAirportCitiesTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'id_original,iata_code,icao_code,airport_name,city_name,country_name,country_iso_code,city_latitude,city_longitude';

    private string $storage;

    /** @var array<string, Country> */
    private array $countries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . '/vyamap-map-cities-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists("{$this->storage}/app/data");
        $this->app->useStoragePath($this->storage);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->storage));

        $this->assertStringStartsWith(sys_get_temp_dir(), storage_path('app/data'));

        foreach (['ES' => 'Spain', 'MA' => 'Morocco', 'US' => 'United States'] as $iso => $name) {
            $this->countries[$iso] = Country::create(['name' => $name, 'iso_code' => $iso]);
        }
    }

    public function test_dry_run_reports_the_plan_without_any_write(): void
    {
        $madrid = $this->city('Madrid', 'ES', 40.42, -3.70);
        $this->airport('MAD');
        $this->csv([$this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000')]);
        $before = $this->airports();

        $writes = $this->countWrites(fn () => $this->artisan('airports:map-cities')
            ->expectsOutputToContain("+ MAD → #{$madrid->id} Madrid (ES)")
            ->expectsOutputToContain('DRY RUN: no database records were modified.')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->airports());
    }

    public function test_apply_assigns_airports_without_city_and_a_second_run_writes_nothing(): void
    {
        $madrid = $this->city('Madrid', 'ES', 40.42, -3.70);
        $mad = $this->airport('MAD');
        $this->csv([$this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000')]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();

        $this->assertSame($madrid->id, $mad->fresh()->city_id);

        $writes = $this->countWrites(fn () => $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
    }

    public function test_merged_city_resolves_to_its_canonical_and_is_never_assigned(): void
    {
        $canonical = $this->city('Tangier', 'MA', 35.7600, -5.8300);
        // La fusionada coincide exactamente con el CSV; la canónica está a ~2,6 km.
        $this->city('Tangier', 'MA', 35.7696, -5.8034, ['merged_into_id' => $canonical->id]);
        $tng = $this->airport('TNG');
        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7696', '-5.8034')]);

        $this->artisan('airports:map-cities', ['--apply' => true])->assertSuccessful();

        $this->assertSame($canonical->id, $tng->fresh()->city_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function brokenMerges(): array
    {
        return [['cycle'], ['other-country'], ['too-long']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenMerges')]
    public function test_unresolvable_merges_go_to_review(string $case): void
    {
        $a = $this->city('Tangier', 'MA', 35.7600, -5.8300);

        if ($case === 'cycle') {
            $b = $this->city('Tangier', 'MA', 35.7610, -5.8310, ['merged_into_id' => $a->id]);
            DB::table('cities')->where('id', $a->id)->update(['merged_into_id' => $b->id]);
            $reason = 'ciclo de fusiones';
        } elseif ($case === 'other-country') {
            DB::table('cities')->where('id', $a->id)->update(['merged_into_id' => $this->city('Tangier', 'ES', 40.0, -3.0)->id]);
            $reason = 'de otro país';
        } else {
            $chain = [$a];
            for ($i = 0; $i < 11; $i++) {
                $chain[] = $this->city('Tangier ' . $i, 'MA', 35.76, -5.83);
            }
            foreach (array_slice($chain, 0, -1) as $i => $city) {
                DB::table('cities')->where('id', $city->id)->update(['merged_into_id' => $chain[$i + 1]->id]);
            }
            $reason = 'cadena de fusiones demasiado larga';
        }

        $tng = $this->airport('TNG');
        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7600', '-5.8300')]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain($reason)
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertNull($tng->fresh()->city_id);
    }

    /**
     * @return array<string, array{?float, ?float, string, string, string}>
     */
    public static function invalidCoordinates(): array
    {
        return [
            'candidata sin coordenadas' => [null, null, '40.4200', '-3.7000', 'no tiene coordenadas válidas'],
            'CSV sin coordenadas' => [40.42, -3.70, '', '', 'coordenadas de la ciudad del CSV ausentes o no válidas'],
            'CSV no numérico' => [40.42, -3.70, 'abc', '-3.7000', 'coordenadas de la ciudad del CSV ausentes o no válidas'],
            'CSV fuera de rango' => [40.42, -3.70, '95.0000', '-3.7000', 'coordenadas de la ciudad del CSV ausentes o no válidas'],
            // Caso peor anterior: ambas ausentes daban 0 km.
            'ambas ausentes' => [null, null, '', '', 'coordenadas de la ciudad del CSV ausentes o no válidas'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCoordinates')]
    public function test_missing_or_invalid_coordinates_go_to_review_and_are_never_zero(?float $cityLatitude, ?float $cityLongitude, string $csvLatitude, string $csvLongitude, string $reason): void
    {
        $this->city('Madrid', 'ES', $cityLatitude, $cityLongitude);
        $mad = $this->airport('MAD');
        $this->csv([$this->row('MAD', 'Madrid', 'ES', $csvLatitude, $csvLongitude)]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain($reason)
            ->assertSuccessful();

        $this->assertNull($mad->fresh()->city_id);
    }

    public function test_two_canonical_cities_within_5_km_are_ambiguous(): void
    {
        $a = $this->city('Tangier', 'MA', 35.7600, -5.8300);
        $b = $this->city('Tangier', 'MA', 35.7696, -5.8034);
        $tng = $this->airport('TNG');
        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7650', '-5.8150')]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain("ambigua entre #{$a->id}, #{$b->id}")
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertNull($tng->fresh()->city_id);
    }

    public function test_far_homonyms_pick_the_only_city_within_5_km_and_far_rows_go_to_review(): void
    {
        $this->city('Albany', 'US', 42.6526, -73.7562);
        $georgia = $this->city('Albany', 'US', 31.5785, -84.1557);
        $aby = $this->airport('ABY');
        $xxx = $this->airport('XXX');
        $this->csv([
            $this->row('ABY', 'Albany', 'US', '31.5800', '-84.1600'),
            // A ~100 km de las dos: revisión.
            $this->row('XXX', 'Albany', 'US', '41.8000', '-73.7500'),
        ]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain('ninguna candidata a 5 km o menos')
            ->assertSuccessful();

        $this->assertSame($georgia->id, $aby->fresh()->city_id);
        $this->assertNull($xxx->fresh()->city_id);
    }

    public function test_existing_assignment_to_another_city_is_a_conflict_and_is_kept(): void
    {
        $madrid = $this->city('Madrid', 'ES', 40.42, -3.70);
        $getafe = $this->city('Getafe', 'ES', 40.31, -3.73);
        $mad = $this->airport('MAD', $getafe->id);
        $this->csv([$this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000')]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain("MAD: ya asignado a #{$getafe->id}; el CSV indica #{$madrid->id} Madrid (no se sobrescribe)")
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertSame($getafe->id, $mad->fresh()->city_id);
    }

    public function test_airport_on_a_merged_city_is_normalized_to_its_canonical(): void
    {
        $canonical = $this->city('Tangier', 'MA', 35.7600, -5.8300);
        $merged = $this->city('Tangier', 'MA', 35.7696, -5.8034, ['merged_into_id' => $canonical->id]);
        $tng = $this->airport('TNG', $merged->id);
        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7600', '-5.8300')]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain("TNG: #{$merged->id} (fusionada) → #{$canonical->id} Tangier")
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();

        $this->assertSame($canonical->id, $tng->fresh()->city_id);
    }

    public function test_airport_already_on_the_canonical_city_is_not_written(): void
    {
        $madrid = $this->city('Madrid', 'ES', 40.42, -3.70);
        $this->airport('MAD', $madrid->id);
        $this->csv([$this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000')]);

        $writes = $this->countWrites(fn () => $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
    }

    public function test_unknown_airports_countries_cities_and_duplicate_iata_are_reported(): void
    {
        $this->city('Madrid', 'ES', 40.42, -3.70);
        $this->airport('MAD');
        $bcn = $this->airport('BCN');
        $this->csv([
            $this->row('ZZZ', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('MAD', 'Madrid', 'XX', '40.4200', '-3.7000'),
            $this->row('BCN', 'Barcelona', 'ES', '41.3900', '2.1700'),
            '9,BAD,short row',
        ]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain('línea 2 ZZZ: aeropuerto no encontrado')
            ->expectsOutputToContain('línea 3 MAD: país XX no encontrado')
            ->expectsOutputToContain('línea 4 BCN: ciudad Barcelona (ES) no encontrada')
            ->expectsOutputToContain('línea 5 ?: número de columnas incorrecto')
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertNull($bcn->fresh()->city_id);

        $this->csv([
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
        ]);

        $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain('IATA repetido en el CSV')
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function structuralErrors(): array
    {
        return [
            'sin archivo' => [null, 'Mapping file not found or unreadable'],
            'vacío' => ['', 'CSV is empty.'],
            'sin columna obligatoria' => ["iata_code,city_name,country_iso_code\nMAD,Madrid,ES", 'Missing required CSV column: id_original'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('structuralErrors')]
    public function test_structural_errors_fail_before_any_write(?string $contents, string $message): void
    {
        $this->city('Madrid', 'ES', 40.42, -3.70);
        $this->airport('MAD');
        $before = $this->airports();

        if ($contents !== null) {
            file_put_contents($this->csvPath(), $contents);
        }

        $writes = $this->countWrites(fn () => $this->artisan('airports:map-cities', ['--apply' => true])
            ->expectsOutputToContain($message)
            ->assertFailed());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->airports());
    }

    public function test_a_failing_write_rolls_back_the_whole_plan(): void
    {
        $this->city('Madrid', 'ES', 40.42, -3.70);
        $this->city('Barcelona', 'ES', 41.39, 2.17);
        $this->airport('MAD');
        $this->airport('BCN');
        $this->csv([
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('BCN', 'Barcelona', 'ES', '41.3900', '2.1700'),
        ]);
        $before = $this->airports();

        // La segunda escritura falla: la primera también debe deshacerse.
        $updates = 0;
        Airport::updating(function () use (&$updates) {
            if (++$updates === 2) {
                throw new RuntimeException('simulated write failure');
            }
        });

        try {
            Artisan::call('airports:map-cities', ['--apply' => true]);
            $this->fail('The command should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated write failure', $exception->getMessage());
        }

        $this->assertSame(2, $updates);
        $this->assertSame($before, $this->airports());
    }

    private function city(string $name, string $iso, ?float $latitude, ?float $longitude, array $attributes = []): City
    {
        return City::create([
            'country_id' => $this->countries[$iso]->id,
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            ...$attributes,
        ]);
    }

    private function airport(string $iata, ?int $cityId = null): Airport
    {
        return Airport::create(['iata_code' => $iata, 'name' => "{$iata} Airport", 'city_id' => $cityId]);
    }

    private function row(string $iata, string $city, string $iso, string $latitude, string $longitude): string
    {
        return implode(',', ['1', $iata, '', "{$iata} Airport", $city, 'Country', $iso, $latitude, $longitude]);
    }

    private function csv(array $lines): void
    {
        file_put_contents($this->csvPath(), implode("\n", [self::HEADER, ...$lines]) . "\n");
    }

    private function csvPath(): string
    {
        return storage_path('app/data/vyamap_airport_city_mapping.csv');
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
