<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Services\CityPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityPersistenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_city_with_region_code(): void
    {
        $city = app(CityPersistenceService::class)->persist([
            'name' => 'Portland',
            'country' => 'United States',
            'iso_code' => 'US',
            'region_code' => 'OR',
            'latitude' => 45.5202471,
            'longitude' => -122.674194,
            'external_provider' => 'geoapify',
            'external_id' => 'portland-oregon-test',
        ]);

        $this->assertDatabaseHas('countries', [
            'iso_code' => 'US',
            'name' => 'United States',
        ]);

        $this->assertDatabaseHas('cities', [
            'id' => $city->id,
            'name' => 'Portland',
            'region_code' => 'OR',
            'latitude' => 45.5202471,
            'longitude' => -122.674194,
            'external_provider' => 'geoapify',
            'external_id' => 'portland-oregon-test',
        ]);
    }

    public function test_it_reuses_a_city_with_the_same_external_identity(): void
    {
        $service = app(CityPersistenceService::class);

        $firstCity = $service->persist([
            'name' => 'Portland',
            'country' => 'United States',
            'iso_code' => 'US',
            'region_code' => 'OR',
            'latitude' => 45.5202471,
            'longitude' => -122.674194,
            'external_provider' => 'geoapify',
            'external_id' => 'portland-oregon-test',
        ]);

        $secondCity = $service->persist([
            'name' => 'Portland',
            'country' => 'United States',
            'iso_code' => 'US',
            'region_code' => 'OR',
            'latitude' => 45.5202471,
            'longitude' => -122.674194,
            'external_provider' => 'geoapify',
            'external_id' => 'portland-oregon-test',
        ]);

        $this->assertSame($firstCity->id, $secondCity->id);
        $this->assertSame(1, City::count());
        $this->assertSame(1, Country::count());
    }

    public function test_it_allows_same_city_name_in_different_regions(): void
    {
        $service = app(CityPersistenceService::class);

        $oregon = $service->persist([
            'name' => 'Portland',
            'country' => 'United States',
            'iso_code' => 'US',
            'region_code' => 'OR',
            'latitude' => 45.5202471,
            'longitude' => -122.674194,
            'external_provider' => 'geoapify',
            'external_id' => 'portland-oregon-test',
        ]);

        $maine = $service->persist([
            'name' => 'Portland',
            'country' => 'United States',
            'iso_code' => 'US',
            'region_code' => 'ME',
            'latitude' => 43.6573605,
            'longitude' => -70.2586618,
            'external_provider' => 'geoapify',
            'external_id' => 'portland-maine-test',
        ]);

        $this->assertNotSame($oregon->id, $maine->id);
        $this->assertSame(2, City::count());

        $this->assertDatabaseHas('cities', [
            'name' => 'Portland',
            'region_code' => 'OR',
        ]);

        $this->assertDatabaseHas('cities', [
            'name' => 'Portland',
            'region_code' => 'ME',
        ]);

        $this->assertSame(1, Country::count());
    }
}