<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;


class CitySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_search_local_cities(): void
    {
        $user = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
            'latitude' => 40.4168,
            'longitude' => -3.7038,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/cities/search?q=Madrid');

        $response
            ->assertOk()
            ->assertJsonPath('results.0.source', 'local')
            ->assertJsonPath('results.0.id', $city->id)
            ->assertJsonPath('results.0.name', 'Madrid')
            ->assertJsonPath('results.0.country', 'Spain')
            ->assertJsonPath('results.0.iso_code', 'ES');
    }

    public function test_city_search_requires_at_least_two_characters(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/cities/search?q=M');

        $response->assertUnprocessable();
    }

    public function test_city_search_uses_geoapify_when_city_is_not_local(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://api.geoapify.com/v1/geocode/search*' => Http::response([
                'results' => [
                    [
                        'name' => 'Skopje',
                        'city' => 'Skopje',
                        'country' => 'North Macedonia',
                        'country_code' => 'mk',
                        'lat' => 41.9962164,
                        'lon' => 21.4318935,
                        'place_id' => 'test-geoapify-id',
                        'result_type' => 'city',
                        'rank' => [
                            'importance' => 0.8,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/cities/search?q=Skopje');

        $response
            ->assertOk()
            ->assertJsonPath('results.0.source', 'geoapify')
            ->assertJsonPath('results.0.id', null)
            ->assertJsonPath('results.0.name', 'Skopje')
            ->assertJsonPath('results.0.country', 'North Macedonia')
            ->assertJsonPath('results.0.iso_code', 'MK')
            ->assertJsonPath('results.0.latitude', 41.9962164)
            ->assertJsonPath('results.0.longitude', 21.4318935)
            ->assertJsonPath('results.0.external_id', 'test-geoapify-id');

        Http::assertSent(function ($request) {
            return str_contains(
                $request->url(),
                'api.geoapify.com/v1/geocode/search'
            );
        });
    }

    public function test_city_search_filters_non_city_results_and_prefers_exact_city_matches(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://api.geoapify.com/v1/geocode/search*' => Http::response([
                'results' => [
                    [
                        'name' => 'Tirana Municipality',
                        'city' => 'Tirana Municipality',
                        'country' => 'Albania',
                        'country_code' => 'al',
                        'lat' => 41.3,
                        'lon' => 19.8,
                        'place_id' => 'municipality-id',
                        'result_type' => 'city',
                        'rank' => [
                            'importance' => 0.25,
                        ],
                    ],
                    [
                        'name' => 'Tirana',
                        'city' => 'Tirana',
                        'country' => 'Albania',
                        'country_code' => 'al',
                        'lat' => 41.9962164,
                        'lon' => 19.8,
                        'place_id' => 'tirana-id',
                        'result_type' => 'city',
                        'rank' => [
                            'importance' => 0.67,
                        ],
                    ],
                    [
                        'name' => 'Tirana',
                        'city' => 'Tirana',
                        'country' => 'Albania',
                        'country_code' => 'al',
                        'lat' => 41.9,
                        'lon' => 19.7,
                        'place_id' => 'suburb-id',
                        'result_type' => 'suburb',
                        'rank' => [
                            'importance' => 0.9,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/cities/search?q=Tirana');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Tirana')
            ->assertJsonPath('results.0.country', 'Albania')
            ->assertJsonPath('results.0.source', 'geoapify');
    }

    public function test_authenticated_user_can_create_city(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/cities', [
                'name' => 'Skopje',
                'country' => 'North Macedonia',
                'iso_code' => 'MK',
                'latitude' => 41.9962164,
                'longitude' => 21.4318935,
                'external_provider' => 'geoapify',
                'external_id' => 'test-geoapify-skpoje',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('city.name', 'Skopje')
            ->assertJsonPath('city.country_id', Country::where('iso_code', 'MK')->first()->id);

        $this->assertDatabaseHas('countries', [
            'name' => 'North Macedonia',
            'iso_code' => 'MK',
        ]);

        $this->assertDatabaseHas('cities', [
            'name' => 'Skopje',
            'external_provider' => 'geoapify',
            'external_id' => 'test-geoapify-skpoje',
        ]);
    }

    public function test_creating_the_same_external_city_does_not_create_a_duplicate(): void
    {
        $user = User::factory()->create();

        $data = [
            'name' => 'Skopje',
            'country' => 'North Macedonia',
            'iso_code' => 'MK',
            'latitude' => 41.9962164,
            'longitude' => 21.4318935,
            'external_provider' => 'geoapify',
            'external_id' => 'test-geoapify-skpoje',
        ];

        $this
            ->actingAs($user)
            ->postJson('/cities', $data)
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson('/cities', $data)
            ->assertCreated();

        $this->assertSame(
            1,
            City::where('external_provider', 'geoapify')
                ->where('external_id', 'test-geoapify-skpoje')
                ->count()
        );
    }

    public function test_creating_city_requires_valid_coordinates(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/cities', [
                'name' => 'Skopje',
                'country' => 'North Macedonia',
                'iso_code' => 'MK',
                'latitude' => 200,
                'longitude' => 21.4318935,
                'external_provider' => 'geoapify',
                'external_id' => 'test-geoapify-skpoje',
            ]);

        $response->assertUnprocessable();
    }

}