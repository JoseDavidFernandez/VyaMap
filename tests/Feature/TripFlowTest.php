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
use Tests\TestCase;

/**
 * Flujos de viajes, vuelos y visitas no cubiertos por las demás pruebas:
 * lectura entre usuarios, borrado en cascada, vuelos de otro viaje, orden
 * cronológico y fechas locales frente a UTC.
 */
class TripFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Trip $trip;

    private City $madrid;

    private Airport $mad;

    private Airport $bcn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $this->madrid = City::create(['country_id' => $spain->id, 'name' => 'Madrid']);
        $barcelona = City::create(['country_id' => $spain->id, 'name' => 'Barcelona']);

        $this->mad = Airport::create(['city_id' => $this->madrid->id, 'name' => 'Madrid', 'iata_code' => 'MAD', 'timezone' => 'Europe/Madrid']);
        $this->bcn = Airport::create(['city_id' => $barcelona->id, 'name' => 'Barcelona', 'iata_code' => 'BCN', 'timezone' => 'Europe/Madrid']);

        $this->trip = $this->createTrip($this->owner, '2026-09-10', '2026-09-12');
    }

    public function test_other_users_cannot_see_or_edit_a_trip(): void
    {
        $intruder = User::factory()->create();

        foreach (['', '/edit', '/photos'] as $suffix) {
            $this->actingAs($intruder)
                ->get("/trips/{$this->trip->id}{$suffix}")
                ->assertForbidden();
        }
    }

    public function test_trip_list_only_contains_the_users_own_trips(): void
    {
        $other = $this->createTrip(User::factory()->create(), '2026-01-01', '2026-01-02');

        $this->actingAs($this->owner)
            ->get('/trips')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Index')
                ->has('trips', 1)
                ->where('trips.0.id', $this->trip->id)
            );

        $this->assertNotSame($other->id, $this->trip->id);
    }

    public function test_deleting_a_trip_deletes_its_visits_and_flights_but_nothing_else(): void
    {
        $visit = $this->createVisit($this->trip, '2026-09-10', '2026-09-11');
        $flight = $this->createFlight($this->trip, '2026-09-10 06:00:00', '2026-09-10 07:10:00');

        $otherTrip = $this->createTrip($this->owner, '2026-10-01', '2026-10-05');
        $otherVisit = $this->createVisit($otherTrip, '2026-10-01', '2026-10-02');

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}")
            ->assertRedirect('/trips');

        $this->assertDatabaseMissing('trips', ['id' => $this->trip->id]);
        $this->assertDatabaseMissing('visits', ['id' => $visit->id]);
        $this->assertDatabaseMissing('flights', ['id' => $flight->id]);

        $this->assertDatabaseHas('trips', ['id' => $otherTrip->id]);
        $this->assertDatabaseHas('visits', ['id' => $otherVisit->id]);
        $this->assertDatabaseHas('cities', ['id' => $this->madrid->id]);
        $this->assertDatabaseHas('airports', ['id' => $this->mad->id]);
    }

    public function test_a_flight_cannot_be_changed_through_another_trip_of_the_same_user(): void
    {
        $otherTrip = $this->createTrip($this->owner, '2026-09-10', '2026-09-12');
        $flight = $this->createFlight($otherTrip, '2026-09-10 06:00:00', '2026-09-10 07:10:00');

        $this->actingAs($this->owner)
            ->put("/trips/{$this->trip->id}/flights/{$flight->id}", $this->flightPayload('VY9999', '2026-09-11T10:00', '2026-09-11T11:10'))
            ->assertNotFound();

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}/flights/{$flight->id}")
            ->assertNotFound();

        $this->assertSame('VY1000', $flight->fresh()->flight_number);
        $this->assertSame($otherTrip->id, $flight->fresh()->trip_id);
    }

    public function test_trip_page_lists_flights_in_chronological_order(): void
    {
        // El vuelo de vuelta se crea antes que el de ida.
        $return = $this->createFlight($this->trip, '2026-09-12 18:00:00', '2026-09-12 19:10:00', 'VY2000');
        $outbound = $this->createFlight($this->trip, '2026-09-10 06:00:00', '2026-09-10 07:10:00', 'VY1000');

        $this->actingAs($this->owner)
            ->get("/trips/{$this->trip->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('flights.0.id', $outbound->id)
                ->where('flights.1.id', $return->id)
                ->where('flights.0.departure', '2026-09-10T06:00:00.000000Z')
            );
    }

    public function test_trip_page_lists_visits_in_chronological_order(): void
    {
        $second = $this->createVisit($this->trip, '2026-09-11', '2026-09-12');
        $first = $this->createVisit($this->trip, '2026-09-10', '2026-09-11');

        $this->actingAs($this->owner)
            ->get("/trips/{$this->trip->id}")
            ->assertInertia(fn ($page) => $page
                ->where('visits.0.id', $first->id)
                ->where('visits.1.id', $second->id)
            );
    }

    public function test_flight_dates_are_checked_against_the_trip_in_local_airport_time(): void
    {
        // 23:30 en Madrid el último día del viaje (21:30 UTC): dentro del viaje.
        $this->actingAs($this->owner)
            ->postJson("/trips/{$this->trip->id}/flights", $this->flightPayload('VY1', '2026-09-12T22:00', '2026-09-12T23:30'))
            ->assertRedirect();

        // 00:30 en Madrid del día siguiente (22:30 UTC del último día): fuera del viaje.
        $this->actingAs($this->owner)
            ->postJson("/trips/{$this->trip->id}/flights", $this->flightPayload('VY2', '2026-09-13T00:30', '2026-09-13T01:40'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departure_at']);
    }

    public function test_flight_crossing_the_date_line_is_valid_when_it_arrives_after_departing_in_real_time(): void
    {
        $japan = Country::create(['name' => 'Japan', 'iso_code' => 'JP']);
        $usa = Country::create(['name' => 'United States', 'iso_code' => 'US']);
        $hnd = Airport::create(['city_id' => City::create(['country_id' => $japan->id, 'name' => 'Tokyo'])->id, 'name' => 'Haneda', 'iata_code' => 'HND', 'timezone' => 'Asia/Tokyo']);
        $hnl = Airport::create(['city_id' => City::create(['country_id' => $usa->id, 'name' => 'Honolulu'])->id, 'name' => 'Honolulu', 'iata_code' => 'HNL', 'timezone' => 'Pacific/Honolulu']);

        // Sale el 11 a las 21:00 en Tokio y llega el 11 a las 09:00 en Honolulu
        // (hora local anterior, pero 7 h después en tiempo real).
        $this->actingAs($this->owner)
            ->post("/trips/{$this->trip->id}/flights", [
                'origin_airport_id' => $hnd->id,
                'destination_airport_id' => $hnl->id,
                'flight_number' => 'NH186',
                'departure_at' => '2026-09-11T21:00',
                'arrival_at' => '2026-09-11T09:00',
            ])
            ->assertRedirect("/trips/{$this->trip->id}")
            ->assertSessionHasNoErrors();

        $flight = Flight::where('flight_number', 'NH186')->firstOrFail();
        $this->assertSame('2026-09-11 12:00:00', $flight->departure_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 19:00:00', $flight->arrival_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_editing_a_flight_reconverts_times_with_the_new_airports(): void
    {
        $jfk = Airport::create(['city_id' => $this->madrid->id, 'name' => 'New York JFK', 'iata_code' => 'JFK', 'timezone' => 'America/New_York']);
        $flight = $this->createFlight($this->trip, '2026-09-10 06:00:00', '2026-09-10 07:10:00');

        $this->actingAs($this->owner)
            ->put("/trips/{$this->trip->id}/flights/{$flight->id}", [
                'origin_airport_id' => $this->mad->id,
                'destination_airport_id' => $jfk->id,
                'flight_number' => 'IB6251',
                'departure_at' => '2026-09-10T12:00',
                'arrival_at' => '2026-09-10T14:30',
            ])
            ->assertSessionHasNoErrors();

        $flight->refresh();
        $this->assertSame($jfk->id, $flight->destination_airport_id);
        $this->assertSame('2026-09-10 10:00:00', $flight->departure_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 18:30:00', $flight->arrival_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_visit_cannot_be_moved_outside_the_trip_dates(): void
    {
        $visit = $this->createVisit($this->trip, '2026-09-10', '2026-09-11');

        $this->actingAs($this->owner)
            ->putJson("/trips/{$this->trip->id}/visits/{$visit->id}", [
                'city_id' => $this->madrid->id,
                'visited_from' => '2026-09-11',
                'visited_until' => '2026-09-14',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['visited_until']);

        $this->assertSame('2026-09-11', $visit->fresh()->visited_until->format('Y-m-d'));
    }

    private function createTrip(User $user, string $start, string $end): Trip
    {
        return Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => $start, 'end_date' => $end]);
    }

    private function createVisit(Trip $trip, string $from, string $until): Visit
    {
        return Visit::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'city_id' => $this->madrid->id,
            'visited_from' => $from,
            'visited_until' => $until,
        ]);
    }

    private function createFlight(Trip $trip, string $departureUtc, string $arrivalUtc, string $number = 'VY1000'): Flight
    {
        return Flight::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $this->mad->id,
            'destination_airport_id' => $this->bcn->id,
            'flight_number' => $number,
            'departure_at' => $departureUtc,
            'arrival_at' => $arrivalUtc,
        ]);
    }

    private function flightPayload(string $number, string $departure, string $arrival): array
    {
        return [
            'origin_airport_id' => $this->mad->id,
            'destination_airport_id' => $this->bcn->id,
            'flight_number' => $number,
            'departure_at' => $departure,
            'arrival_at' => $arrival,
        ];
    }
}
