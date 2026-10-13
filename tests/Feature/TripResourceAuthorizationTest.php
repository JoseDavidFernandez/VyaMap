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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * La autorización sobre el viaje debe resolverse antes de validar: los
 * errores de validación de visitas y vuelos incluyen las fechas del viaje.
 */
class TripResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $intruder;

    private Trip $trip;

    private City $city;

    private Airport $origin;

    private Airport $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->intruder = User::factory()->create();

        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);

        $this->city = City::create(['country_id' => $country->id, 'name' => 'Madrid']);
        $barcelona = City::create(['country_id' => $country->id, 'name' => 'Barcelona']);

        $this->origin = Airport::create([
            'city_id' => $this->city->id,
            'name' => 'Madrid Airport',
            'iata_code' => 'MAD',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->destination = Airport::create([
            'city_id' => $barcelona->id,
            'name' => 'Barcelona Airport',
            'iata_code' => 'BCN',
            'timezone' => 'Europe/Madrid',
        ]);

        $this->trip = Trip::create([
            'user_id' => $this->owner->id,
            'name' => 'Private trip name',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);
    }

    public function test_intruder_gets_403_without_trip_data_when_creating_an_invalid_visit(): void
    {
        $response = $this->actingAs($this->intruder)->postJson(
            "/trips/{$this->trip->id}/visits",
            $this->invalidVisitPayload()
        );

        $this->assertDeniedWithoutLeak($response);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_intruder_gets_403_without_trip_data_when_updating_a_visit(): void
    {
        $visit = $this->createVisit();

        $response = $this->actingAs($this->intruder)->putJson(
            "/trips/{$this->trip->id}/visits/{$visit->id}",
            $this->invalidVisitPayload()
        );

        $this->assertDeniedWithoutLeak($response);
        $this->assertSame('2026-09-10', $visit->fresh()->visited_from->format('Y-m-d'));
    }

    public function test_intruder_cannot_delete_a_visit(): void
    {
        $visit = $this->createVisit();

        $this->actingAs($this->intruder)
            ->deleteJson("/trips/{$this->trip->id}/visits/{$visit->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('visits', ['id' => $visit->id]);
    }

    public function test_intruder_gets_403_without_trip_data_when_creating_an_invalid_flight(): void
    {
        $response = $this->actingAs($this->intruder)->postJson(
            "/trips/{$this->trip->id}/flights",
            $this->invalidFlightPayload()
        );

        $this->assertDeniedWithoutLeak($response);
        $this->assertDatabaseCount('flights', 0);
    }

    public function test_intruder_gets_403_without_trip_data_when_updating_a_flight(): void
    {
        $flight = $this->createFlight();

        $response = $this->actingAs($this->intruder)->putJson(
            "/trips/{$this->trip->id}/flights/{$flight->id}",
            $this->invalidFlightPayload()
        );

        $this->assertDeniedWithoutLeak($response);
        $this->assertSame('VY1000', $flight->fresh()->flight_number);
    }

    public function test_intruder_cannot_delete_a_flight(): void
    {
        $flight = $this->createFlight();

        $this->actingAs($this->intruder)
            ->deleteJson("/trips/{$this->trip->id}/flights/{$flight->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('flights', ['id' => $flight->id]);
    }

    public function test_owner_still_receives_date_validation_errors_for_visits(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/trips/{$this->trip->id}/visits", $this->invalidVisitPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['visited_from']);
    }

    public function test_owner_still_receives_date_validation_errors_for_flights(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/trips/{$this->trip->id}/flights", $this->invalidFlightPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departure_at']);
    }

    public function test_owner_can_update_and_delete_their_flight(): void
    {
        $flight = $this->createFlight();

        $this->actingAs($this->owner)
            ->put("/trips/{$this->trip->id}/flights/{$flight->id}", [
                'origin_airport_id' => $this->origin->id,
                'destination_airport_id' => $this->destination->id,
                'flight_number' => 'VY2000',
                'departure_at' => '2026-09-11T10:00',
                'arrival_at' => '2026-09-11T11:10',
            ])
            ->assertRedirect("/trips/{$this->trip->id}");

        $this->assertSame('VY2000', $flight->fresh()->flight_number);

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}/flights/{$flight->id}")
            ->assertRedirect("/trips/{$this->trip->id}");

        $this->assertDatabaseMissing('flights', ['id' => $flight->id]);
    }

    public function test_owner_can_delete_their_visit(): void
    {
        $visit = $this->createVisit();

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}/visits/{$visit->id}")
            ->assertRedirect("/trips/{$this->trip->id}");

        $this->assertDatabaseMissing('visits', ['id' => $visit->id]);
    }

    private function assertDeniedWithoutLeak(TestResponse $response): void
    {
        $response->assertForbidden();

        $body = $response->getContent();

        foreach (['2026-09-10', '2026-09-12', 'Private trip name', 'errors'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    private function invalidVisitPayload(): array
    {
        return [
            'city_id' => $this->city->id,
            'visited_from' => '2020-01-01',
            'visited_until' => '2020-01-02',
        ];
    }

    private function invalidFlightPayload(): array
    {
        return [
            'origin_airport_id' => $this->origin->id,
            'destination_airport_id' => $this->destination->id,
            'flight_number' => 'VY9999',
            'departure_at' => '2020-01-01T08:00',
            'arrival_at' => '2020-01-01T09:00',
        ];
    }

    private function createVisit(): Visit
    {
        return Visit::create([
            'user_id' => $this->owner->id,
            'trip_id' => $this->trip->id,
            'city_id' => $this->city->id,
            'visited_from' => '2026-09-10',
            'visited_until' => '2026-09-11',
        ]);
    }

    private function createFlight(): Flight
    {
        return Flight::create([
            'user_id' => $this->owner->id,
            'trip_id' => $this->trip->id,
            'origin_airport_id' => $this->origin->id,
            'destination_airport_id' => $this->destination->id,
            'flight_number' => 'VY1000',
            'departure_at' => '2026-09-10 06:00:00',
            'arrival_at' => '2026-09-10 07:10:00',
        ]);
    }
}
