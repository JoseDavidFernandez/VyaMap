<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 05, bloque B3: contenido y orden del historial de vuelos
 * (FlightHistoryController) y datos de la página de pasaporte
 * (PassportController).
 *
 * Las estadísticas del pasaporte (países, ciudades, porcentaje, filtro por
 * año) se calculan en Passport.vue a partir de estos datos; aquí se prueba lo
 * que envía el servidor. El acceso de invitados ya está en
 * SecurityIsolationTest y la marca is_sovereign en SovereignCountriesTest.
 */
class FlightHistoryAndPassportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $other;

    private Airport $mad;

    private Airport $bcn;

    private Airport $lhr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        $this->user = User::factory()->create();
        $this->other = User::factory()->create();

        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $uk = Country::create(['name' => 'United Kingdom', 'iso_code' => 'GB']);

        $this->mad = $this->airport($this->city($spain, 'Madrid'), 'Adolfo Suárez Madrid-Barajas', 'MAD', 'LEMD', 40.4719, -3.5626, 'Europe/Madrid');
        $this->bcn = $this->airport($this->city($spain, 'Barcelona'), 'Barcelona-El Prat', 'BCN', 'LEBL', 41.2971, 2.0785, 'Europe/Madrid');
        $this->lhr = $this->airport($this->city($uk, 'London'), 'Heathrow', 'LHR', 'EGLL', 51.4700, -0.4543, 'Europe/London');
    }

    public function test_flight_history_lists_own_flights_newest_departure_first(): void
    {
        $trip2025 = $this->trip($this->user, '2025-07-01', '2025-07-10');
        $trip2026 = $this->trip($this->user, '2026-03-01', '2026-11-30');

        // Creados fuera de orden: el orden de la página no es el de los id.
        $march = $this->flight($trip2026, $this->mad, $this->bcn, '2026-03-10 08:00:00', '2026-03-10 09:20:00', 'IB1');
        $july = $this->flight($trip2025, $this->bcn, $this->lhr, '2025-07-01 12:00:00', '2025-07-01 14:05:00', 'VY2');
        $november = $this->flight($trip2026, $this->lhr, $this->mad, '2026-11-20 06:30:00', '2026-11-20 10:00:00', 'BA3');

        // Vuelo de otro usuario, más reciente que todos: no aparece.
        $this->flight($this->trip($this->other, '2026-12-01', '2026-12-31'), $this->mad, $this->lhr, '2026-12-24 10:00:00', '2026-12-24 12:00:00', 'XX9');

        $this->actingAs($this->user)
            ->get('/flight-history')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FlightHistory')
                ->has('flights', 3)
                ->where('flights', fn ($flights) => collect($flights)->pluck('id')->all() === [$november->id, $march->id, $july->id])
            );
    }

    public function test_flight_history_presents_each_flight_with_its_airports(): void
    {
        $trip = $this->trip($this->user, '2026-03-01', '2026-03-31');
        $flight = $this->flight($trip, $this->mad, $this->bcn, '2026-03-10 08:00:00', '2026-03-10 09:20:00', 'IB1', 'Iberia');

        $this->actingAs($this->user)
            ->get('/flight-history')
            ->assertInertia(fn ($page) => $page
                ->where('flights', [$this->expectedFlight($flight->id, 'IB1', 'Iberia', '2026-03-10T08:00:00.000000Z', '2026-03-10T09:20:00.000000Z', 483, 80, [
                    'code' => 'MAD', 'airport' => 'Adolfo Suárez Madrid-Barajas', 'city' => 'Madrid', 'latitude' => 40.4719, 'longitude' => -3.5626,
                ], [
                    'code' => 'BCN', 'airport' => 'Barcelona-El Prat', 'city' => 'Barcelona', 'latitude' => 41.2971, 'longitude' => 2.0785,
                ])])
            );
    }

    public function test_flight_history_uses_the_icao_code_when_an_airport_has_no_iata(): void
    {
        $this->mad->update(['iata_code' => null]);
        $trip = $this->trip($this->user, '2026-03-01', '2026-03-31');
        $this->flight($trip, $this->mad, $this->lhr, '2026-03-10 08:00:00', '2026-03-10 10:00:00', 'IB3');

        $this->actingAs($this->user)
            ->get('/flight-history')
            ->assertInertia(fn ($page) => $page
                ->where('flights.0.origin.code', 'LEMD')
                ->where('flights.0.destination.code', 'LHR')
                ->where('flights.0.airline', null)
                ->where('flights.0.distance_km', 1246)
            );
    }

    public function test_flight_history_is_empty_without_own_flights(): void
    {
        $this->flight($this->trip($this->other, '2026-03-01', '2026-03-31'), $this->mad, $this->bcn, '2026-03-10 08:00:00', '2026-03-10 09:20:00', 'XX1');

        $this->actingAs($this->user)
            ->get('/flight-history')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FlightHistory')
                ->where('flights', [])
            );
    }

    public function test_passport_sends_own_visits_trips_flights_and_years(): void
    {
        $madrid = $this->mad->city;
        $london = $this->lhr->city;

        // Viaje sin visitas ni vuelos: su año (inicio) también cuenta.
        $winter = $this->trip($this->user, '2023-12-28', '2024-01-03', 'Fin de año');
        $summer = $this->trip($this->user, '2025-07-01', '2025-07-10', 'Verano');
        $spring = $this->trip($this->user, '2026-03-01', '2026-03-31', 'Primavera');

        $early = $this->visit($summer, $madrid, '2025-07-01', '2025-07-03');
        $late = $this->visit($summer, $london, '2025-07-05', '2025-07-10');
        $undated = $this->visit($spring, $london, null, null);

        $flight = $this->flight($spring, $this->mad, $this->lhr, '2026-03-10 08:00:00', '2026-03-10 09:20:00', 'IB3');

        // Datos de otro usuario en las mismas ciudades y años: no aparecen.
        $foreignTrip = $this->trip($this->other, '2022-05-01', '2022-05-10', 'Ajeno');
        $this->visit($foreignTrip, $madrid, '2022-05-02', '2022-05-03');
        $this->flight($foreignTrip, $this->bcn, $this->lhr, '2022-05-01 10:00:00', '2022-05-01 12:00:00', 'XX1');

        $this->actingAs($this->user)
            ->get('/passport')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Passport')
                ->where('totalCountries', 193)
                // Inicio de viajes, visitas y salidas de vuelos; sin repetir, de más reciente a más antiguo.
                ->where('years', [2026, 2025, 2023])
                // Visitas por fecha de inicio, más reciente primero; sin fecha, al final.
                ->where('visits', [
                    $this->expectedVisit($late, $london, 'United Kingdom', 'GB', '2025-07-05T00:00:00.000000Z', '2025-07-10T00:00:00.000000Z'),
                    $this->expectedVisit($early, $madrid, 'Spain', 'ES', '2025-07-01T00:00:00.000000Z', '2025-07-03T00:00:00.000000Z'),
                    $this->expectedVisit($undated, $london, 'United Kingdom', 'GB', null, null),
                ])
                // La consulta de viajes no ordena: se compara el contenido, no el orden.
                ->where('trips', fn ($trips) => collect($trips)->sortBy('id')->values()->all() === [
                    ['id' => $winter->id, 'name' => 'Fin de año', 'start_date' => '2023-12-28', 'end_date' => '2024-01-03'],
                    ['id' => $summer->id, 'name' => 'Verano', 'start_date' => '2025-07-01', 'end_date' => '2025-07-10'],
                    ['id' => $spring->id, 'name' => 'Primavera', 'start_date' => '2026-03-01', 'end_date' => '2026-03-31'],
                ])
                ->has('flights', 1)
                ->where('flights.0', $this->expectedFlight($flight->id, 'IB3', null, '2026-03-10T08:00:00.000000Z', '2026-03-10T09:20:00.000000Z', 1246, 80, [
                    'code' => 'MAD', 'airport' => 'Adolfo Suárez Madrid-Barajas', 'city' => 'Madrid', 'latitude' => 40.4719, 'longitude' => -3.5626,
                ], [
                    'code' => 'LHR', 'airport' => 'Heathrow', 'city' => 'London', 'latitude' => 51.47, 'longitude' => -0.4543,
                ]))
            );
    }

    public function test_passport_flights_follow_the_flight_history_order(): void
    {
        $trip = $this->trip($this->user, '2026-01-01', '2026-12-31');
        $first = $this->flight($trip, $this->mad, $this->bcn, '2026-02-01 08:00:00', '2026-02-01 09:00:00', 'IB1');
        $last = $this->flight($trip, $this->bcn, $this->mad, '2026-09-01 08:00:00', '2026-09-01 09:00:00', 'IB2');
        $middle = $this->flight($trip, $this->mad, $this->lhr, '2026-05-01 08:00:00', '2026-05-01 10:00:00', 'IB3');

        $this->actingAs($this->user)
            ->get('/passport')
            ->assertInertia(fn ($page) => $page
                ->where('flights', fn ($flights) => collect($flights)->pluck('id')->all() === [$last->id, $middle->id, $first->id])
                ->where('years', [2026])
            );
    }

    public function test_passport_is_empty_for_a_user_without_travel_data(): void
    {
        $foreignTrip = $this->trip($this->other, '2026-03-01', '2026-03-31');
        $this->visit($foreignTrip, $this->mad->city, '2026-03-02', '2026-03-03');
        $this->flight($foreignTrip, $this->mad, $this->bcn, '2026-03-10 08:00:00', '2026-03-10 09:20:00', 'XX1');

        $this->actingAs($this->user)
            ->get('/passport')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Passport')
                ->where('totalCountries', 193)
                ->where('years', [])
                ->where('visits', [])
                ->where('trips', [])
                ->where('flights', [])
            );
    }

    private function expectedFlight(int $id, string $number, ?string $airline, string $departure, string $arrival, int $distance, int $duration, array $origin, array $destination): array
    {
        return [
            'id' => $id,
            'flight_number' => $number,
            'airline' => $airline,
            'departure' => $departure,
            'arrival' => $arrival,
            'distance_km' => $distance,
            'duration_minutes' => $duration,
            'origin' => $origin,
            'destination' => $destination,
        ];
    }

    private function expectedVisit(Visit $visit, City $city, string $country, string $iso, ?string $from, ?string $until): array
    {
        return [
            'id' => $visit->id,
            'city_id' => $city->id,
            'city' => $city->name,
            'country' => $country,
            'iso_code' => $iso,
            'is_sovereign' => true,
            'visited_from' => $from,
            'visited_until' => $until,
        ];
    }

    private function city(Country $country, string $name): City
    {
        return City::create(['country_id' => $country->id, 'name' => $name]);
    }

    private function airport(City $city, string $name, string $iata, string $icao, float $latitude, float $longitude, string $timezone): Airport
    {
        return Airport::create([
            'city_id' => $city->id,
            'name' => $name,
            'iata_code' => $iata,
            'icao_code' => $icao,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timezone' => $timezone,
        ]);
    }

    private function trip(User $user, string $start, string $end, string $name = 'Trip'): Trip
    {
        return Trip::create(['user_id' => $user->id, 'name' => $name, 'start_date' => $start, 'end_date' => $end]);
    }

    private function visit(Trip $trip, City $city, ?string $from, ?string $until): Visit
    {
        return Visit::create(['user_id' => $trip->user_id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => $from, 'visited_until' => $until]);
    }

    private function flight(Trip $trip, Airport $origin, Airport $destination, string $departure, string $arrival, string $number, ?string $airline = null): Flight
    {
        return Flight::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'flight_number' => $number,
            'departure_at' => $departure,
            'arrival_at' => $arrival,
            'airline' => $airline,
        ]);
    }
}
