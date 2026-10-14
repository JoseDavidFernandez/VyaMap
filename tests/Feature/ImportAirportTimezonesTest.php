<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * airports:import-timezones (Fase 05): simulación por defecto, zonas
 * existentes conservadas, aeropuertos con vuelos protegidos, identificación
 * por ICAO con alternativa por IATA y escritura transaccional.
 *
 * Cada prueba usa un storage temporal con su propio CSV: los datos reales
 * (storage/app/data) nunca se leen ni se modifican.
 */
class ImportAirportTimezonesTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'id,name,iata_code,icao_code,latitude,longitude,timezone,timezone_status';

    private string $storage;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        $this->storage = sys_get_temp_dir() . '/vyamap-import-timezones-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists("{$this->storage}/app/data");
        $this->app->useStoragePath($this->storage);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->storage));

        $this->assertStringStartsWith(sys_get_temp_dir(), storage_path('app/data'));

        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $this->city = City::create(['country_id' => $country->id, 'name' => 'Madrid', 'latitude' => 40.42, 'longitude' => -3.70]);
    }

    public function test_dry_run_plans_without_writing(): void
    {
        $this->airport('MAD', 'LEMD', null);
        $this->csv([$this->row('MAD', 'LEMD', 'Europe/Madrid')]);
        $before = $this->airports();

        $writes = $this->countWrites(fn () => $this->artisan('airports:import-timezones')
            ->expectsOutputToContain('+ MAD: sin zona → Europe/Madrid')
            ->expectsOutputToContain('DRY RUN: no database records were modified.')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->airports());
    }

    public function test_apply_updates_airports_without_flights(): void
    {
        $empty = $this->airport('MAD', 'LEMD', null);
        $wrong = $this->airport('BCN', 'LEBL', 'Europe/Lisbon');
        $this->csv([
            $this->row('MAD', 'LEMD', 'Europe/Madrid'),
            $this->row('BCN', 'LEBL', 'Europe/Madrid'),
        ]);

        $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 2')
            ->assertSuccessful();

        $this->assertSame('Europe/Madrid', $empty->fresh()->timezone);
        $this->assertSame('Europe/Madrid', $wrong->fresh()->timezone);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unusableTimezones(): array
    {
        return [
            'vacía' => ['', 'zona horaria vacía'],
            'no IANA' => ['Mars/Olympus', 'zona horaria no válida «Mars/Olympus»'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusableTimezones')]
    public function test_empty_or_invalid_timezones_keep_the_existing_one(string $timezone, string $reason): void
    {
        $mad = $this->airport('MAD', 'LEMD', 'Europe/Madrid');
        $this->csv([$this->row('MAD', 'LEMD', $timezone)]);

        $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsOutputToContain("LEMD: {$reason}")
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertSame('Europe/Madrid', $mad->fresh()->timezone);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function flightRoles(): array
    {
        return ['origen' => ['origin'], 'destino' => ['destination']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('flightRoles')]
    public function test_airports_used_by_flights_keep_their_timezone(string $role): void
    {
        $used = $this->airport('MAD', 'LEMD', 'Europe/Madrid');
        $other = $this->airport('BCN', 'LEBL', 'Europe/Madrid');
        $free = $this->airport('AGP', 'LEMG', null);

        $flight = $this->flight(
            $role === 'origin' ? $used : $other,
            $role === 'origin' ? $other : $used,
        );
        $flightBefore = $flight->fresh()->toArray();

        $this->csv([
            $this->row('MAD', 'LEMD', 'Europe/Lisbon'),
            // Sin vuelos: sí se actualiza en la misma ejecución.
            $this->row('AGP', 'LEMG', 'Europe/Madrid'),
        ]);

        $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsOutputToContain('! MAD: conflicto, tiene 1 vuelo(s); cambiar Europe/Madrid → Europe/Lisbon desplazaría su hora local')
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();

        $this->assertSame('Europe/Madrid', $used->fresh()->timezone);
        $this->assertSame('Europe/Madrid', $free->fresh()->timezone);
        $this->assertSame($flightBefore, $flight->fresh()->toArray());
    }

    public function test_unknown_icao_falls_back_to_iata_only_when_it_does_not_contradict_the_stored_icao(): void
    {
        $withoutIcao = $this->airport('MAD', null, null);
        $otherIcao = $this->airport('BCN', 'LEBL', null);

        $this->csv([
            // ICAO desconocido; el MAD guardado no tiene ICAO: se usa el IATA.
            $this->row('MAD', 'XXXX', 'Europe/Madrid'),
            // ICAO desconocido; el BCN guardado tiene otro ICAO: no se asocia.
            $this->row('BCN', 'YYYY', 'Europe/Madrid'),
            // Ni ICAO ni IATA existen.
            $this->row('ZZZ', 'ZZZZ', 'Europe/Madrid'),
            // Sin ICAO: por IATA, que no existe.
            $this->row('QQQ', '', 'Europe/Madrid'),
        ]);

        $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsOutputToContain('YYYY: el ICAO YYYY no existe y el IATA BCN corresponde a otro ICAO (LEBL)')
            ->expectsOutputToContain('línea 4 ZZZZ: aeropuerto no encontrado')
            ->expectsOutputToContain('línea 5 QQQ: aeropuerto no encontrado')
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();

        $this->assertSame('Europe/Madrid', $withoutIcao->fresh()->timezone);
        $this->assertNull($otherIcao->fresh()->timezone);
    }

    public function test_contradictory_rows_for_the_same_airport_are_a_conflict(): void
    {
        $mad = $this->airport('MAD', 'LEMD', null);
        $this->csv([
            $this->row('MAD', 'LEMD', 'Europe/Madrid'),
            $this->row('MAD', '', 'Europe/Lisbon'),
        ]);

        $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsOutputToContain('MAD: conflicto, filas con zonas distintas (Europe/Madrid, Europe/Lisbon)')
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertNull($mad->fresh()->timezone);
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function structuralErrors(): array
    {
        return [
            'sin archivo' => [null, 'Timezone dataset not found or unreadable'],
            'vacío' => ['', 'Unable to read CSV headers.'],
            'sin columna obligatoria' => ["iata_code,icao_code\nMAD,LEMD", 'Missing required CSV column: timezone'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('structuralErrors')]
    public function test_structural_errors_fail_without_writing(?string $contents, string $message): void
    {
        $this->airport('MAD', 'LEMD', null);
        $before = $this->airports();

        if ($contents !== null) {
            file_put_contents($this->csvPath(), $contents);
        }

        $writes = $this->countWrites(fn () => $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsOutputToContain($message)
            ->assertFailed());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->airports());
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->airport('MAD', 'LEMD', null);
        $this->csv([$this->row('MAD', 'LEMD', 'Europe/Madrid')]);

        $this->artisan('airports:import-timezones', ['--apply' => true])->assertSuccessful();
        $after = $this->airports();

        $writes = $this->countWrites(fn () => $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($after, $this->airports());
    }

    public function test_summary_and_exit_code_with_skipped_not_found_and_conflicts(): void
    {
        $used = $this->airport('MAD', 'LEMD', 'Europe/Madrid');
        $this->flight($used, $this->airport('BCN', 'LEBL', 'Europe/Madrid'));
        $this->airport('AGP', 'LEMG', null);
        $this->airport('SVQ', 'LEZL', 'Europe/Madrid');

        $this->csv([
            $this->row('MAD', 'LEMD', 'Europe/Lisbon'),   // conflicto (vuelos)
            $this->row('AGP', 'LEMG', 'Europe/Madrid'),   // actualizado
            $this->row('SVQ', 'LEZL', 'Europe/Madrid'),   // sin cambios
            $this->row('ZZZ', 'ZZZZ', 'Europe/Madrid'),   // no encontrado
            $this->row('BCN', 'LEBL', 'Not/AZone'),       // omitida
            '9,short,row',                                // mal formada
        ]);

        // Omitidas, no encontradas y conflictos no convierten en FAILURE una
        // ejecución aplicada correctamente.
        $this->artisan('airports:import-timezones', ['--apply' => true])
            ->expectsTable(['resultado', 'número'], [
                ['filas leídas', 6],
                ['aeropuertos actualizados', 1],
                ['aeropuertos sin cambios', 1],
                ['conflictos (no se cambian)', 1],
                ['filas omitidas', 2],
                ['filas sin aeropuerto en VyaMap', 1],
            ])
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();
    }

    public function test_a_failing_write_rolls_back_the_whole_plan(): void
    {
        $this->airport('MAD', 'LEMD', null);
        $this->airport('BCN', 'LEBL', null);
        $this->csv([
            $this->row('MAD', 'LEMD', 'Europe/Madrid'),
            $this->row('BCN', 'LEBL', 'Europe/Madrid'),
        ]);
        $before = $this->airports();

        $saves = 0;
        Airport::updating(function () use (&$saves) {
            if (++$saves === 2) {
                throw new RuntimeException('simulated write failure');
            }
        });

        try {
            Artisan::call('airports:import-timezones', ['--apply' => true]);
            $this->fail('The command should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated write failure', $exception->getMessage());
        }

        $this->assertSame($before, $this->airports());
    }

    private function airport(string $iata, ?string $icao, ?string $timezone): Airport
    {
        return Airport::create([
            'city_id' => $this->city->id,
            'name' => "{$iata} Airport",
            'iata_code' => $iata,
            'icao_code' => $icao,
            'timezone' => $timezone,
        ]);
    }

    private function flight(Airport $origin, Airport $destination): Flight
    {
        $user = User::factory()->create();
        $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05']);

        return Flight::create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'flight_number' => 'VY1',
            'departure_at' => '2026-01-02 07:00:00',
            'arrival_at' => '2026-01-02 08:15:00',
        ]);
    }

    private function row(string $iata, string $icao, string $timezone): string
    {
        return implode(',', ['1', "{$iata} Airport", $iata, $icao, '0', '0', $timezone, 'calculated']);
    }

    private function csv(array $lines): void
    {
        file_put_contents($this->csvPath(), implode("\n", [self::HEADER, ...$lines]) . "\n");
    }

    private function csvPath(): string
    {
        return storage_path('app/data/airport_timezones.csv');
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
