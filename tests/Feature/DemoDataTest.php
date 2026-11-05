<?php

namespace Tests\Feature;

use App\Console\Commands\ResetDemoData;
use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Services\TravelStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * php artisan demo:reset: datos ficticios para la cuenta de demostración, sin
 * tocar otras cuentas y con sus salvaguardas.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    private User $demo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        config(['app.user_timezone' => 'Europe/Madrid']);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'UTC'));

        $this->demo = User::factory()->create(['email' => 'demo@example.test']);
    }

    public function test_it_creates_fictitious_trips_visits_and_flights_for_the_demo_account(): void
    {
        $this->catalog();

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])->assertSuccessful();

        $trips = Trip::where('user_id', $this->demo->id)->get();
        $this->assertSame(15, $trips->count());
        $this->assertSame(13, $trips->where('status', Trip::COMPLETED)->count());
        $this->assertSame(1, $trips->where('status', Trip::CANCELLED)->count());
        $this->assertSame(27, Visit::where('user_id', $this->demo->id)->count());
        $this->assertSame(35, Flight::where('user_id', $this->demo->id)->count());
        $this->assertSame(2, Flight::where('user_id', $this->demo->id)->whereNull('trip_id')->whereNotNull('confirmed_at')->count());
        $this->assertSame(4, Flight::where('user_id', $this->demo->id)->whereNull('departure_at')->count());

        // El viaje planificado empieza siempre 45 días después de hoy.
        $planned = $trips->where('status', Trip::PLANNED)->sole();
        $this->assertSame(Carbon::parse(Trip::today())->addDays(45)->toDateString(), $planned->start_date->toDateString());

        // Coherencia con las reglas: visitas y salidas dentro de su viaje, llegada tras la salida.
        foreach ($trips as $trip) {
            foreach ($trip->visits as $visit) {
                $this->assertTrue($visit->visited_from->betweenIncluded($trip->start_date, $trip->end_date) && $visit->visited_until->lte($trip->end_date));
            }

            foreach ($trip->flights as $flight) {
                $this->assertTrue($flight->departure_date->betweenIncluded($trip->start_date, $trip->end_date), "{$trip->name}: vuelo fuera del viaje");
                $this->assertTrue($flight->arrival_at === null || $flight->arrival_at->gt($flight->departure_at));
            }

            // Los viajes completados ya han terminado.
            if ($trip->status === Trip::COMPLETED) {
                $this->assertTrue($trip->end_date->lt(Trip::today()), "{$trip->name}: completado sin haber terminado");
            }
        }

        // Escala en Doha y llegadas al día siguiente (hora local del destino).
        $flights = Flight::with(['originAirport', 'destinationAirport'])->where('user_id', $this->demo->id)->get();
        $this->assertSame(2, $flights->filter(fn ($flight) => $flight->destinationAirport->iata_code === 'DOH')->count());
        $this->assertGreaterThanOrEqual(3, $flights->filter(fn ($flight) => $flight->arrival_at
            && $flight->arrival_at->setTimezone($flight->destinationAirport->timezone)->toDateString() > $flight->departure_date->toDateString())->count());

        // Estadísticas: países por año, un territorio, cuatro continentes, distancia y tiempo en el aire.
        $statistics = app(TravelStatistics::class);
        $places = $statistics->places($this->demo->id);
        $this->assertSame(16, $places['total']['countries']);
        $this->assertSame(1, $places['total']['territories']);
        $this->assertSame(4, $places['total']['continents']);
        $newByYear = collect($places['by_year'])->map(fn ($year) => collect($year['new_countries'])->pluck('iso_code')->sort()->values()->all());
        $this->assertSame(['ES', 'IT', 'MA', 'PT', 'US'], $newByYear[2024]);
        $this->assertSame(['BE', 'GR', 'IS', 'JP', 'MX', 'NL'], $newByYear[2025]);
        $this->assertSame(['AT', 'CZ', 'GB', 'HU', 'TR'], $newByYear[2026]);
        $this->assertSame(1, $places['by_year'][2024]['new_territories']);

        $stats = $statistics->flights(Flight::withStatuses(Flight::with(['trip', 'originAirport.city', 'destinationAirport.city'])->where('user_id', $this->demo->id)->get()));
        // Los del viaje cancelado y los del planificado no cuentan.
        $this->assertSame(31, $stats['flights']);
        $this->assertSame(27, $stats['flights_with_times']);
        $this->assertSame(123 * 60 + 45, $stats['air_minutes']);
        $this->assertGreaterThan(85000, $stats['distance_km']);
        $this->assertSame(['MAD', 'MEX'], [$stats['longest']['origin'], $stats['longest']['destination']]);
    }

    public function test_running_it_again_restores_the_same_demo_data(): void
    {
        $this->catalog();

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])->assertSuccessful();
        Trip::where('user_id', $this->demo->id)->first()->update(['name' => 'Edited by a visitor']);

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])->assertSuccessful();

        $this->assertSame(15, Trip::where('user_id', $this->demo->id)->count());
        $this->assertSame(0, Trip::where('name', 'Edited by a visitor')->count());
        $this->assertSame(35, Flight::where('user_id', $this->demo->id)->count());
    }

    public function test_other_accounts_are_never_touched(): void
    {
        $this->catalog();
        $other = User::factory()->create();
        $trip = Trip::create(['user_id' => $other->id, 'name' => 'Private trip', 'start_date' => '2025-01-01', 'end_date' => '2025-01-05', 'status' => Trip::COMPLETED]);
        $visit = Visit::create(['user_id' => $other->id, 'trip_id' => $trip->id, 'city_id' => Airport::where('iata_code', 'LIS')->value('city_id'), 'visited_from' => '2025-01-01', 'visited_until' => '2025-01-02']);

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])->assertSuccessful();

        $this->assertModelExists($trip);
        $this->assertModelExists($visit);
        $this->assertSame(1, Trip::where('user_id', $other->id)->count());
    }

    public function test_it_refuses_an_unknown_account(): void
    {
        $this->catalog();

        $this->artisan('demo:reset', ['email' => 'nobody@example.test', '--force' => true])->assertFailed();

        $this->assertSame(0, Trip::count());
    }

    public function test_it_refuses_an_account_with_photos_and_changes_nothing(): void
    {
        $this->catalog();
        $trip = Trip::create(['user_id' => $this->demo->id, 'name' => 'Real trip', 'start_date' => '2025-01-01', 'end_date' => '2025-01-05', 'status' => Trip::COMPLETED]);
        Photo::create(['user_id' => $this->demo->id, 'path' => 'photos/1.jpg', 'original_filename' => '1.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])->assertFailed();

        $this->assertModelExists($trip);
        $this->assertSame(1, Trip::where('user_id', $this->demo->id)->count());
    }

    public function test_it_changes_nothing_when_the_catalog_is_incomplete(): void
    {
        $this->catalog(except: 'JFK');
        $trip = Trip::create(['user_id' => $this->demo->id, 'name' => 'Existing', 'start_date' => '2025-01-01', 'end_date' => '2025-01-05', 'status' => Trip::COMPLETED]);

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])
            ->expectsOutputToContain('JFK')
            ->assertFailed();

        $this->assertModelExists($trip);
    }

    public function test_without_force_it_asks_and_a_no_changes_nothing(): void
    {
        $this->catalog();
        $trip = Trip::create(['user_id' => $this->demo->id, 'name' => 'Existing', 'start_date' => '2025-01-01', 'end_date' => '2025-01-05', 'status' => Trip::COMPLETED]);

        $this->artisan('demo:reset', ['email' => 'demo@example.test'])
            ->expectsConfirmation('¿Continuar?', 'no')
            ->assertFailed();

        $this->assertModelExists($trip);
        $this->assertSame(0, Flight::count());
    }

    // Catálogo mínimo: los aeropuertos que usa la demo, con ciudad y zona horaria.
    /**
     * Los aeropuertos que usa demo:reset, con sus ciudades, países, coordenadas y
     * zonas horarias tal como están en el catálogo versionado (database/data/catalog).
     */
    private function catalog(?string $except = null): void
    {
        $codes = (new \ReflectionClassConstant(ResetDemoData::class, 'AIRPORTS'))->getValue();
        $read = fn (string $file) => collect(array_map('str_getcsv', file(base_path("database/data/catalog/{$file}"), FILE_IGNORE_NEW_LINES)))
            ->pipe(fn ($rows) => $rows->slice(1)->map(fn ($row) => array_combine($rows->first(), $row)))
            ->keyBy('iata_code');
        $mapping = $read('vyamap_airport_city_mapping.csv');
        $coordinates = $read('airports.csv');
        $timezones = $read('airport_timezones.csv');

        foreach ($codes as $code) {
            if ($code === $except) {
                continue;
            }

            $row = $mapping[$code];
            $country = Country::firstOrCreate(['iso_code' => $row['country_iso_code']], ['name' => $row['country_name']]);
            $city = City::firstOrCreate(
                ['country_id' => $country->id, 'name' => $row['city_name']],
                ['latitude' => $row['city_latitude'], 'longitude' => $row['city_longitude']],
            );
            Airport::create([
                'city_id' => $city->id, 'name' => $row['airport_name'], 'iata_code' => $code,
                'latitude' => $coordinates[$code]['latitude_deg'], 'longitude' => $coordinates[$code]['longitude_deg'],
                'timezone' => $timezones[$code]['timezone'],
            ]);
        }
    }
}
