<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirportSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_airports_using_a_learned_city_alias(): void
    {
        $user = User::factory()->create();

        $country = Country::query()->create([
            'name' => 'Italy',
            'iso_code' => 'IT',
        ]);

        $city = City::query()->create([
            'country_id' => $country->id,
            'name' => 'Rome',
            'latitude' => 41.9028,
            'longitude' => 12.4964,
        ]);

        CityAlias::query()->create([
            'city_id' => $city->id,
            'alias' => 'Romulus',
            'normalized_alias' => 'romulus',
            'language' => null,
        ]);

        $airport = Airport::query()->create([
            'city_id' => $city->id,
            'name' => 'Rome Fiumicino Airport',
            'iata_code' => 'FCO',
            'icao_code' => 'LIRF',
            'latitude' => 41.8003,
            'longitude' => 12.2389,
        ]);

        $response = $this->actingAs($user)
            //->getJson(route('airports.search', ['q' => 'Roma']));
            ->getJson(route('airports.search', ['q' => 'Romulus']));

        $response->assertOk()
            ->assertJsonPath('results.0.id', $airport->id)
            ->assertJsonPath('results.0.city', 'Rome')
            ->assertJsonPath('results.0.iata_code', 'FCO');
    }

    public function test_exact_iata_match_has_priority_over_city_alias_matches(): void
    {
        $user = User::factory()->create();

        $italy = Country::query()->create([
            'name' => 'Italy',
            'iso_code' => 'IT',
        ]);

        $spain = Country::query()->create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $rome = City::query()->create([
            'country_id' => $italy->id,
            'name' => 'Rome',
            'latitude' => 41.9028,
            'longitude' => 12.4964,
        ]);

        $madrid = City::query()->create([
            'country_id' => $spain->id,
            'name' => 'Madrid',
            'latitude' => 40.4168,
            'longitude' => -3.7038,
        ]);

        CityAlias::query()->create([
            'city_id' => $rome->id,
            'alias' => 'Roma',
            'normalized_alias' => 'roma',
            'language' => null,
        ]);

        $romeAirport = Airport::query()->create([
            'city_id' => $rome->id,
            'name' => 'Rome Fiumicino Airport',
            'iata_code' => 'FCO',
            'icao_code' => 'LIRF',
        ]);

        $madridAirport = Airport::query()->create([
            'city_id' => $madrid->id,
            'name' => 'Madrid-Barajas Airport',
            'iata_code' => 'MAD',
            'icao_code' => 'LEMD',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('airports.search', ['q' => 'MAD']));

        $response->assertOk()
            ->assertJsonPath('results.0.id', $madridAirport->id);

        $this->assertNotEquals(
            $romeAirport->id,
            $response->json('results.0.id')
        );
    }

    public function test_airport_search_returns_at_most_twenty_results(): void
    {
        $user = User::factory()->create();

        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Testville']);

        for ($i = 0; $i < 30; $i++) {
            Airport::create([
                'city_id' => $city->id,
                'name' => "Test Airport {$i}",
                'iata_code' => sprintf('T%02d', $i),
                'timezone' => 'Europe/Madrid',
            ]);
        }

        $this->actingAs($user)
            ->getJson('/airports/search?q=Test')
            ->assertOk()
            ->assertJsonCount(20, 'results');
    }
}
