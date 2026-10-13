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

class TripUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Trip $trip;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $country = Country::create(['name' => 'Japan', 'iso_code' => 'JP']);
        $this->city = City::create(['country_id' => $country->id, 'name' => 'Tokyo']);

        $this->trip = Trip::create([
            'user_id' => $this->user->id,
            'name' => 'Japan',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-20',
        ]);
    }

    public function test_owner_can_update_trip_dates_that_still_contain_visits_and_flights(): void
    {
        $this->createVisit('2026-09-12', '2026-09-14');

        $this->actingAs($this->user)
            ->put("/trips/{$this->trip->id}", $this->payload('2026-09-11', '2026-09-15'))
            ->assertRedirect("/trips/{$this->trip->id}")
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-11', $this->trip->fresh()->start_date->format('Y-m-d'));
    }

    public function test_new_dates_cannot_leave_visits_outside_the_trip(): void
    {
        $this->createVisit('2026-09-10', '2026-09-12');

        $this->actingAs($this->user)
            ->putJson("/trips/{$this->trip->id}", $this->payload('2026-09-11', '2026-09-20'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        $this->assertSame('2026-09-10', $this->trip->fresh()->start_date->format('Y-m-d'));
    }

    public function test_new_dates_cannot_leave_flights_outside_the_trip(): void
    {
        $tokyo = Airport::create([
            'city_id' => $this->city->id,
            'name' => 'Haneda',
            'iata_code' => 'HND',
            'timezone' => 'Asia/Tokyo',
        ]);

        $osaka = Airport::create([
            'city_id' => $this->city->id,
            'name' => 'Itami',
            'iata_code' => 'ITM',
            'timezone' => 'Asia/Tokyo',
        ]);

        // 2026-09-19 20:00 en Tokio (11:00 UTC).
        Flight::create([
            'user_id' => $this->user->id,
            'trip_id' => $this->trip->id,
            'origin_airport_id' => $tokyo->id,
            'destination_airport_id' => $osaka->id,
            'flight_number' => 'JL123',
            'departure_at' => '2026-09-19 11:00:00',
            'arrival_at' => '2026-09-19 12:10:00',
        ]);

        $this->actingAs($this->user)
            ->putJson("/trips/{$this->trip->id}", $this->payload('2026-09-10', '2026-09-18'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        // El mismo día local sí es válido.
        $this->actingAs($this->user)
            ->putJson("/trips/{$this->trip->id}", $this->payload('2026-09-10', '2026-09-19'))
            ->assertRedirect();
    }

    public function test_other_user_cannot_update_the_trip(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->putJson("/trips/{$this->trip->id}", $this->payload('2026-09-11', '2026-09-12'))
            ->assertForbidden();

        $this->assertSame('Japan', $this->trip->fresh()->name);
    }

    public function test_visit_dates_are_sent_to_the_edit_form_as_plain_dates(): void
    {
        $this->createVisit('2026-09-12', '2026-09-14');

        $this->actingAs($this->user)
            ->get("/trips/{$this->trip->id}")
            ->assertInertia(fn ($page) => $page
                ->where('visits.0.visited_from', '2026-09-12')
                ->where('visits.0.visited_until', '2026-09-14')
            );
    }

    private function payload(string $start, string $end): array
    {
        return [
            'name' => 'Japan',
            'description' => null,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    private function createVisit(string $from, string $until): Visit
    {
        return Visit::create([
            'user_id' => $this->user->id,
            'trip_id' => $this->trip->id,
            'city_id' => $this->city->id,
            'visited_from' => $from,
            'visited_until' => $until,
        ]);
    }
}
