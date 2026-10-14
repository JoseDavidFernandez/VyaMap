<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * app:import-airports (Fase 05, P0.2): una importación repetida no puede
 * borrar los datos enriquecidos (city_id, coordenadas, zona horaria).
 *
 * Cada prueba usa un storage temporal con su propio CSV: el catálogo real
 * (storage/app/data) nunca se lee ni se modifica.
 */
class ImportAirportsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'id_original,iata_code,icao_code,airport_name,city_name,country_name,country_iso_code,city_latitude,city_longitude';

    private string $storage;

    private City $madrid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . '/vyamap-import-airports-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists("{$this->storage}/app/data");
        $this->app->useStoragePath($this->storage);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->storage));

        $this->assertStringStartsWith(sys_get_temp_dir(), storage_path('app/data'));

        $this->madrid = City::create([
            'country_id' => Country::create(['name' => 'Spain', 'iso_code' => 'ES'])->id,
            'name' => 'Madrid',
            'latitude' => 40.42,
            'longitude' => -3.70,
        ]);
    }

    public function test_new_airports_are_imported_without_enriched_fields(): void
    {
        $this->csv(['5,MAD,LEMD,Madrid-Barajas Airport,Madrid,Spain,ES,40.4200,-3.7000']);

        $this->artisan('app:import-airports')->assertSuccessful();

        $airport = Airport::where('iata_code', 'MAD')->firstOrFail();
        $this->assertSame('Madrid-Barajas Airport', $airport->name);
        $this->assertSame('LEMD', $airport->icao_code);

        // Las coordenadas del CSV son de la ciudad: no se copian al aeropuerto.
        $this->assertNull($airport->city_id);
        $this->assertNull($airport->latitude);
        $this->assertNull($airport->longitude);
        $this->assertNull($airport->timezone);
    }

    public function test_existing_airport_updates_descriptive_fields_and_keeps_enriched_ones(): void
    {
        $airport = $this->enrichedAirport('MAD', 'LEMD', 'Barajas');

        $this->csv(['5,MAD,LEMM,Adolfo Suárez Madrid–Barajas Airport,Madrid,Spain,ES,40.4200,-3.7000']);

        $this->artisan('app:import-airports')
            ->expectsOutputToContain('MAD name: «Barajas» → «Adolfo Suárez Madrid–Barajas Airport»')
            ->assertSuccessful();

        $airport->refresh();
        $this->assertSame('Adolfo Suárez Madrid–Barajas Airport', $airport->name);
        $this->assertSame('LEMM', $airport->icao_code);
        $this->assertEnrichedFieldsKept($airport);
    }

    public function test_empty_values_in_the_csv_never_erase_existing_data(): void
    {
        $airport = $this->enrichedAirport('MAD', 'LEMD', 'Madrid-Barajas Airport');
        $before = $airport->fresh()->toArray();

        // Sin nombre ni ICAO, y sin columnas de ciudad.
        $this->csv(['5,MAD,,,,,,,', '6,MAD']);

        $this->artisan('app:import-airports')->assertSuccessful();

        $this->assertSame($before, $airport->fresh()->toArray());
    }

    public function test_running_the_same_import_twice_changes_nothing_the_second_time(): void
    {
        $this->enrichedAirport('MAD', 'LEMD', 'Old name');
        $this->csv([
            '5,MAD,LEMD,Madrid-Barajas Airport,Madrid,Spain,ES,40.4200,-3.7000',
            '6,BCN,LEBL,Barcelona-El Prat Airport,Barcelona,Spain,ES,41.3900,2.1700',
        ]);

        $this->artisan('app:import-airports')->assertSuccessful();
        $afterFirst = $this->airports();

        $this->travel(1)->hours();
        $writes = $this->countWrites(fn () => $this->artisan('app:import-airports')->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($afterFirst, $this->airports());
        $this->assertSame(2, Airport::count());
        $this->assertEnrichedFieldsKept(Airport::where('iata_code', 'MAD')->firstOrFail());
    }

    public function test_dry_run_reports_the_plan_without_any_write(): void
    {
        $this->enrichedAirport('MAD', 'LEMD', 'Old name');
        $this->csv([
            '5,MAD,LEMD,Madrid-Barajas Airport,Madrid,Spain,ES,40.4200,-3.7000',
            '6,BCN,LEBL,Barcelona-El Prat Airport,Barcelona,Spain,ES,41.3900,2.1700',
            '7,X1,,Broken,,,,,',
        ]);
        $before = $this->airports();

        $writes = $this->countWrites(fn () => $this->artisan('app:import-airports', ['--dry-run' => true])
            ->expectsOutputToContain('+ BCN Barcelona-El Prat Airport')
            ->expectsOutputToContain('~ MAD name: «Old name» → «Madrid-Barajas Airport»')
            ->expectsOutputToContain('línea 4 (X1): código IATA no válido')
            ->expectsOutputToContain('Simulación (--dry-run): no se ha modificado nada.')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->airports());
    }

    public function test_invalid_rows_are_skipped_and_valid_ones_imported(): void
    {
        $this->enrichedAirport('MAD', 'LEMD', 'Madrid-Barajas Airport');

        $this->csv([
            '1,M1,ABCD,Bad IATA,,,,,',
            '2,BCN,LEBL,Barcelona-El Prat Airport,,,,,',
            '3,BCN,LEBX,Barcelona duplicate,,,,,',
            '4,AGP,LE-MG,Bad ICAO,,,,,',
            '5,SVQ,LEZL,,,,,,',
            '6,VLC,LEVC,' . str_repeat('x', 201) . ',,,,,',
            '7,TFN,LEMD,Steals Madrid ICAO,,,,,',
            '8,pmi,lepa,Palma de Mallorca Airport,,,,,',
        ]);

        $this->artisan('app:import-airports')
            ->expectsOutputToContain('línea 2 (M1): código IATA no válido')
            ->expectsOutputToContain('línea 4 (BCN): IATA repetido en el CSV (primera aparición en la línea 3)')
            ->expectsOutputToContain('línea 5 (AGP): código ICAO no válido')
            ->expectsOutputToContain('línea 6 (SVQ): aeropuerto nuevo sin nombre')
            ->expectsOutputToContain('línea 7 (VLC): nombre demasiado largo')
            ->expectsOutputToContain('línea 8 (TFN): el ICAO LEMD ya pertenece a MAD')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['MAD', 'BCN', 'PMI'], Airport::pluck('iata_code')->all());
        $this->assertSame('LEBL', Airport::where('iata_code', 'BCN')->value('icao_code'));
        $this->assertSame('LEPA', Airport::where('iata_code', 'PMI')->value('icao_code'));
        $this->assertEnrichedFieldsKept(Airport::where('iata_code', 'MAD')->firstOrFail());
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'sin archivo' => [null, 'Airport dataset not found or unreadable'],
            'vacío' => ['', 'Unable to read CSV headers.'],
            'sin columna obligatoria' => ["id_original,iata_code,airport_name\n5,MAD,Madrid", 'Missing required CSV column: icao_code'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInputs')]
    public function test_invalid_input_fails_without_writing(?string $contents, string $message): void
    {
        $this->enrichedAirport('MAD', 'LEMD', 'Madrid-Barajas Airport');
        $before = $this->airports();

        if ($contents !== null) {
            file_put_contents($this->csvPath(), $contents);
        }

        $this->artisan('app:import-airports')
            ->expectsOutputToContain($message)
            ->assertFailed();

        $this->assertSame($before, $this->airports());
    }

    private function enrichedAirport(string $iata, string $icao, string $name): Airport
    {
        return Airport::create([
            'iata_code' => $iata,
            'icao_code' => $icao,
            'name' => $name,
            'city_id' => $this->madrid->id,
            'latitude' => 40.4719,
            'longitude' => -3.5626,
            'timezone' => 'Europe/Madrid',
        ]);
    }

    private function assertEnrichedFieldsKept(Airport $airport): void
    {
        $airport->refresh();

        $this->assertSame($this->madrid->id, $airport->city_id);
        $this->assertEquals(40.4719, (float) $airport->latitude);
        $this->assertEquals(-3.5626, (float) $airport->longitude);
        $this->assertSame('Europe/Madrid', $airport->timezone);
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
        return Airport::query()->orderBy('iata_code')->get()->toArray();
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
