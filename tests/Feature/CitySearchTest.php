<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\CityAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
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
            'region_code' => 'MD',
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
            ->assertJsonPath('results.0.iso_code', 'ES')
            ->assertJsonPath('results.0.region_code', 'MD');

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
                        'state_code' => 'MK',
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
            ->assertJsonPath('results.0.external_id', 'test-geoapify-id')
            ->assertJsonPath('results.0.region_code', 'MK');

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

    public function test_authenticated_user_can_create_a_city_verified_by_geoapify(): void
    {
        $user = User::factory()->create();

        $this->fakeGeoapifySkopje();

        $response = $this
            ->actingAs($user)
            ->postJson('/cities', [
                'external_provider' => 'geoapify',
                'external_id' => 'test-geoapify-skopje',
                'search_query' => 'Skopje',
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
            'external_id' => 'test-geoapify-skopje',
            'region_code' => 'SK',
        ]);
    }

    public function test_city_creation_ignores_client_supplied_catalog_fields(): void
    {
        $user = User::factory()->create();

        $this->fakeGeoapifySkopje();

        $this
            ->actingAs($user)
            ->postJson('/cities', [
                'external_provider' => 'geoapify',
                'external_id' => 'test-geoapify-skopje',
                'search_query' => 'Skopje',
                'name' => 'Fake City',
                'country' => 'Fake Country',
                'iso_code' => 'ZZ',
                'latitude' => 0,
                'longitude' => 0,
            ])
            ->assertCreated();

        $city = City::firstOrFail();

        $this->assertSame('Skopje', $city->name);
        $this->assertEqualsWithDelta(41.9962164, (float) $city->latitude, 0.000001);
        $this->assertDatabaseMissing('countries', ['iso_code' => 'ZZ']);
        $this->assertDatabaseMissing('countries', ['name' => 'Fake Country']);
    }

    public function test_city_that_geoapify_does_not_return_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->fakeGeoapifySkopje();

        $this
            ->actingAs($user)
            ->postJson('/cities', [
                'external_provider' => 'geoapify',
                'external_id' => 'made-up-id',
                'search_query' => 'Skopje',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id']);

        $this->assertDatabaseCount('cities', 0);
        $this->assertDatabaseCount('countries', 0);
        $this->assertDatabaseCount('city_aliases', 0);
    }

    public function test_creating_the_same_external_city_does_not_create_a_duplicate(): void
    {
        $user = User::factory()->create();

        $this->fakeGeoapifySkopje();

        $data = [
            'external_provider' => 'geoapify',
            'external_id' => 'test-geoapify-skopje',
            'search_query' => 'Skopje',
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
                ->where('external_id', 'test-geoapify-skopje')
                ->count()
        );

        // La segunda verificación reutiliza el resultado en caché.
        Http::assertSentCount(1);
    }

    public function test_creating_city_requires_the_search_query(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->postJson('/cities', [
                'external_provider' => 'geoapify',
                'external_id' => 'test-geoapify-skopje',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search_query']);
    }

    public function test_city_creation_returns_503_when_geoapify_is_unreachable(): void
    {
        $user = User::factory()->create();

        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this
            ->actingAs($user)
            ->postJson('/cities', [
                'external_provider' => 'geoapify',
                'external_id' => 'test-geoapify-skopje',
                'search_query' => 'Skopje',
            ])
            ->assertStatus(503)
            ->assertJsonMissingPath('errors');

        $this->assertDatabaseCount('cities', 0);
    }

    public function test_city_creation_returns_503_without_an_api_key(): void
    {
        $user = User::factory()->create();

        config(['services.geoapify.key' => null]);
        Http::fake();

        $this
            ->actingAs($user)
            ->postJson('/cities', [
                'external_provider' => 'geoapify',
                'external_id' => 'test-geoapify-skopje',
                'search_query' => 'Skopje',
            ])
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_city_search_degrades_gracefully_when_geoapify_is_unreachable(): void
    {
        $user = User::factory()->create();

        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        City::create([
            'country_id' => $country->id,
            'name' => 'Madrigal',
            'latitude' => 40,
            'longitude' => -3,
        ]);

        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->actingAs($user)
            ->getJson('/cities/search?q=Madri')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.source', 'local');
    }

    public function test_city_search_degrades_gracefully_when_geoapify_returns_an_error(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'https://api.geoapify.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->actingAs($user)
            ->getJson('/cities/search?q=Nowhere')
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_city_search_is_rate_limited(): void
    {
        $user = User::factory()->create();

        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        City::create(['country_id' => $country->id, 'name' => 'Madrid', 'latitude' => 40, 'longitude' => -3]);

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user)->getJson('/cities/search?q=Madrid')->assertOk();
        }

        $this->actingAs($user)
            ->getJson('/cities/search?q=Madrid')
            ->assertStatus(429);
    }

    private function fakeGeoapifySkopje(): void
    {
        Http::fake([
            'https://api.geoapify.com/v1/geocode/search*' => Http::response([
                'results' => [
                    [
                        'name' => 'Skopje',
                        'city' => 'Skopje',
                        'country' => 'North Macedonia',
                        'country_code' => 'mk',
                        'state_code' => 'SK',
                        'lat' => 41.9962164,
                        'lon' => 21.4318935,
                        'place_id' => 'test-geoapify-skopje',
                        'result_type' => 'city',
                        'rank' => ['importance' => 0.8],
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_city_search_finds_a_city_using_a_learned_alias(): void
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
            'alias' => 'Roma',
            'normalized_alias' => 'roma',
            'language' => null,
        ]);

        $this->actingAs($user)
            ->getJson('/cities/search?q=Roma')
            ->assertOk()
            ->assertJsonPath('results.0.source', 'local')
            ->assertJsonPath('results.0.id', $city->id)
            ->assertJsonPath('results.0.name', 'Rome');
    }

    public function test_city_search_uses_geoapify_when_no_local_city_matches(): void
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
                        'place_id' => 'skopje-fallback-test',
                        'result_type' => 'city',
                        'rank' => ['importance' => 0.8],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->getJson('/cities/search?q=Skopje')
            ->assertOk()
            ->assertJsonPath('results.0.source', 'geoapify')
            ->assertJsonPath('results.0.name', 'Skopje');

        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'api.geoapify.com/v1/geocode/search')
        );
    }


}