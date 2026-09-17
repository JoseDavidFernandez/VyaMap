<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_flight_using_another_users_trip(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $madrid = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $barcelona = City::create([
            'country_id' => $country->id,
            'name' => 'Barcelona',
        ]);

        $madridAirport = Airport::create([
            'city_id' => $madrid->id,
            'name' => 'Madrid Airport',
            'iata_code' => 'MAD',
        ]);

        $barcelonaAirport = Airport::create([
            'city_id' => $barcelona->id,
            'name' => 'Barcelona Airport',
            'iata_code' => 'BCN',
        ]);

        $trip = Trip::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user2)
            ->postJson('/flights', [
                'trip_id' => $trip->id,
                'origin_airport_id' => $madridAirport->id,
                'destination_airport_id' => $barcelonaAirport->id,
                'flight_number' => 'VY1234',
                'departure_at' => '2026-09-10 08:00:00',
                'arrival_at' => '2026-09-10 09:15:00',
                'airline' => 'Vueling',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_id']);
    }

    public function test_flight_cannot_arrive_before_it_departed(): void
    {
        $user = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $madrid = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $barcelona = City::create([
            'country_id' => $country->id,
            'name' => 'Barcelona',
        ]);

        $madridAirport = Airport::create([
            'city_id' => $madrid->id,
            'name' => 'Madrid Airport',
            'iata_code' => 'MAD',
        ]);

        $barcelonaAirport = Airport::create([
            'city_id' => $barcelona->id,
            'name' => 'Barcelona Airport',
            'iata_code' => 'BCN',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/flights', [
                'origin_airport_id' => $madridAirport->id,
                'destination_airport_id' => $barcelonaAirport->id,
                'flight_number' => 'VY1234',
                'departure_at' => '2026-09-10 10:00:00',
                'arrival_at' => '2026-09-10 09:00:00',
                'airline' => 'Vueling',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['arrival_at']);
    }
}