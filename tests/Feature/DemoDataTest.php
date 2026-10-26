<?php

namespace Tests\Feature;

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
        $this->assertSame(5, $trips->count());
        $this->assertSame(4, $trips->where('status', Trip::COMPLETED)->count());
        $this->assertSame(1, $trips->where('status', Trip::PLANNED)->count());
        $this->assertSame(6, Visit::where('user_id', $this->demo->id)->count());
        $this->assertSame(10, Flight::where('user_id', $this->demo->id)->count());
        $this->assertSame(1, Flight::where('user_id', $this->demo->id)->whereNull('trip_id')->whereNotNull('confirmed_at')->count());

        // Coherencia con las reglas: visitas y salidas dentro de su viaje, llegada tras la salida.
        foreach ($trips as $trip) {
            foreach ($trip->visits as $visit) {
                $this->assertTrue($visit->visited_from->betweenIncluded($trip->start_date, $trip->end_date) && $visit->visited_until->lte($trip->end_date));
            }

            foreach ($trip->flights as $flight) {
                $this->assertTrue($flight->departure_date->betweenIncluded($trip->start_date, $trip->end_date), "{$trip->name}: vuelo fuera del viaje");
                $this->assertTrue($flight->arrival_at === null || $flight->arrival_at->gt($flight->departure_at));
            }
        }

        // Las estadísticas tienen datos que enseñar: países, continentes, un viaje que cruza de año y vuelos realizados.
        $statistics = app(TravelStatistics::class);
        $places = $statistics->places($this->demo->id);
        // PT, IT, GB y US. España es solo el origen de los vuelos y Francia, un viaje planificado: no cuentan.
        $this->assertSame(4, $places['total']['countries']);
        $this->assertGreaterThanOrEqual(2, count($places['by_year']));
        $flights = $statistics->flights(Flight::withStatuses(Flight::with(['trip', 'originAirport.city', 'destinationAirport.city'])->where('user_id', $this->demo->id)->get()));
        $this->assertSame(9, $flights['flights']);
        $this->assertNotNull($flights['air_minutes']);
    }

    public function test_running_it_again_restores_the_same_demo_data(): void
    {
        $this->catalog();

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])->assertSuccessful();
        Trip::where('user_id', $this->demo->id)->first()->update(['name' => 'Edited by a visitor']);

        $this->artisan('demo:reset', ['email' => 'demo@example.test', '--force' => true])->assertSuccessful();

        $this->assertSame(5, Trip::where('user_id', $this->demo->id)->count());
        $this->assertSame(0, Trip::where('name', 'Edited by a visitor')->count());
        $this->assertSame(10, Flight::where('user_id', $this->demo->id)->count());
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
    private function catalog(?string $except = null): void
    {
        $airports = [
            'MAD' => ['ES', 'Spain', 'Madrid', 40.4719, -3.5626, 'Europe/Madrid'],
            'BCN' => ['ES', 'Spain', 'Barcelona', 41.2971, 2.0785, 'Europe/Madrid'],
            'LIS' => ['PT', 'Portugal', 'Lisbon', 38.7742, -9.1342, 'Europe/Lisbon'],
            'FCO' => ['IT', 'Italy', 'Rome', 41.8003, 12.2389, 'Europe/Rome'],
            'FLR' => ['IT', 'Italy', 'Florence', 43.81, 11.2051, 'Europe/Rome'],
            'LHR' => ['GB', 'United Kingdom', 'London', 51.47, -0.4543, 'Europe/London'],
            'JFK' => ['US', 'United States', 'New York', 40.6413, -73.7781, 'America/New_York'],
            'CDG' => ['FR', 'France', 'Paris', 49.0097, 2.5479, 'Europe/Paris'],
        ];

        foreach ($airports as $code => [$iso, $country, $city, $latitude, $longitude, $timezone]) {
            if ($code === $except) {
                continue;
            }

            $countryModel = Country::firstOrCreate(['iso_code' => $iso], ['name' => $country]);
            $cityModel = City::firstOrCreate(['country_id' => $countryModel->id, 'name' => $city]);
            Airport::create(['city_id' => $cityModel->id, 'name' => "{$code} Airport", 'iata_code' => $code, 'latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezone]);
        }
    }
}
