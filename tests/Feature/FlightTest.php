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
            'timezone' => 'Europe/Madrid',
        ]);

        $barcelonaAirport = Airport::create([
            'city_id' => $barcelona->id,
            'name' => 'Barcelona Airport',
            'iata_code' => 'BCN',
            'timezone' => 'Europe/Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user2)
            ->postJson("/trips/{$trip->id}/flights", [
                'origin_airport_id' => $madridAirport->id,
                'destination_airport_id' => $barcelonaAirport->id,
                'flight_number' => 'VY1234',
                'departure_at' => '2026-09-10T08:00',
                'arrival_at' => '2026-09-10T09:15',
                'airline' => 'Vueling',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('flights', [
            'trip_id' => $trip->id,
            'flight_number' => 'VY1234',
        ]);
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
            'timezone' => 'Europe/Madrid',
        ]);

        $barcelonaAirport = Airport::create([
            'city_id' => $barcelona->id,
            'name' => 'Barcelona Airport',
            'iata_code' => 'BCN',
            'timezone' => 'Europe/Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'Test Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson("/trips/{$trip->id}/flights", [
                'origin_airport_id' => $madridAirport->id,
                'destination_airport_id' => $barcelonaAirport->id,
                'flight_number' => 'VY1234',
                'departure_at' => '2026-09-10T10:00',
                'arrival_at' => '2026-09-10T09:00',
                'airline' => 'Vueling',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['arrival_at']);

        $this->assertDatabaseMissing('flights', [
            'trip_id' => $trip->id,
            'flight_number' => 'VY1234',
        ]);
    }

    public function test_flight_times_are_stored_in_utc_using_each_airports_timezone(): void
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

        $skopje = City::create([
            'country_id' => $country->id,
            'name' => 'Skopje',
        ]);

        $madridAirport = Airport::create([
            'city_id' => $madrid->id,
            'name' => 'Madrid Airport',
            'iata_code' => 'MAD',
            'timezone' => 'Europe/Madrid',
        ]);

        $skopjeAirport = Airport::create([
            'city_id' => $skopje->id,
            'name' => 'Skopje International Airport',
            'iata_code' => 'SKP',
            'timezone' => 'Europe/Skopje',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'Timezone Test Trip',
            'start_date' => '2026-08-26',
            'end_date' => '2026-08-29',
        ]);

        $this->actingAs($user)
            ->post("/trips/{$trip->id}/flights", [
                'origin_airport_id' => $madridAirport->id,
                'destination_airport_id' => $skopjeAirport->id,
                'flight_number' => 'W64816',
                'departure_at' => '2026-08-26T21:25',
                'arrival_at' => '2026-08-27T00:50',
                'airline' => 'Wizz Air',
            ])
            ->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('flights', [
            'trip_id' => $trip->id,
            'flight_number' => 'W64816',
            'departure_at' => '2026-08-26 19:25:00',
            'arrival_at' => '2026-08-26 22:50:00',
        ]);
    }

    public function test_flight_timezone_conversion_respects_daylight_saving_time(): void
    {
        $user = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $airport = Airport::create([
            'city_id' => $city->id,
            'name' => 'Madrid Airport',
            'iata_code' => 'MAD',
            'timezone' => 'Europe/Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'DST Test Trip',
            'start_date' => '2026-03-29',
            'end_date' => '2026-03-29',
        ]);

        $this->actingAs($user)
            ->post("/trips/{$trip->id}/flights", [
                'origin_airport_id' => $airport->id,
                'destination_airport_id' => Airport::create([
                    'city_id' => $city->id,
                    'name' => 'Barcelona Airport',
                    'iata_code' => 'BCN',
                    'timezone' => 'Europe/Madrid',
                ])->id,
                'flight_number' => 'DST123',
                'departure_at' => '2026-03-29T01:30',
                'arrival_at' => '2026-03-29T03:30',
                'airline' => 'Test Airline',
            ])
            ->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('flights', [
            'trip_id' => $trip->id,
            'flight_number' => 'DST123',
            'departure_at' => '2026-03-29 00:30:00',
            'arrival_at' => '2026-03-29 01:30:00',
        ]);
    }

    public function test_flight_rejects_nonexistent_local_time_during_daylight_saving_transition(): void
    {
        $user = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $origin = Airport::create([
            'city_id' => $city->id,
            'name' => 'Madrid Airport',
            'iata_code' => 'MAD',
            'timezone' => 'Europe/Madrid',
        ]);

        $destination = Airport::create([
            'city_id' => $city->id,
            'name' => 'Barcelona Airport',
            'iata_code' => 'BCN',
            'timezone' => 'Europe/Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'DST Invalid Time Test',
            'start_date' => '2026-03-29',
            'end_date' => '2026-03-29',
        ]);

        $this->actingAs($user)
            ->post("/trips/{$trip->id}/flights", [
                'origin_airport_id' => $origin->id,
                'destination_airport_id' => $destination->id,
                'flight_number' => 'DST456',
                'departure_at' => '2026-03-29T02:30',
                'arrival_at' => '2026-03-29T04:00',
                'airline' => 'Test Airline',
            ])
            ->assertSessionHasErrors('departure_at');

        $this->assertDatabaseMissing('flights', [
            'trip_id' => $trip->id,
            'flight_number' => 'DST456',
        ]);
    }



}