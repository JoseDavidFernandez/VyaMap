<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Services\CityPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\CityAlias;
use App\Support\CityAliasNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function test_it_learns_an_alias_when_persisting_a_city(): void
    {
        $city = app(CityPersistenceService::class)->persist([
            'name' => 'Rome',
            'country' => 'Italy',
            'iso_code' => 'IT',
            'latitude' => 41.9028,
            'longitude' => 12.4964,
            'external_provider' => 'geoapify',
            'external_id' => 'rome-test',
            'search_query' => 'Roma',
        ]);

        $this->assertDatabaseHas('city_aliases', [
            'city_id' => $city->id,
            'alias' => 'Roma',
            'normalized_alias' => 'roma',
        ]);
    }

    public function test_it_does_not_duplicate_an_existing_alias_for_the_same_city(): void
    {
        $service = app(CityPersistenceService::class);

        $data = [
            'name' => 'Rome',
            'country' => 'Italy',
            'iso_code' => 'IT',
            'latitude' => 41.9028,
            'longitude' => 12.4964,
            'external_provider' => 'geoapify',
            'external_id' => 'rome-test',
            'search_query' => 'Roma',
        ];

        $firstCity = $service->persist($data);
        $secondCity = $service->persist($data);

        $this->assertSame($firstCity->id, $secondCity->id);

        $this->assertSame(
            1,
            CityAlias::query()
                ->where('normalized_alias', 'roma')
                ->count()
        );
    }

    public function test_it_does_not_reassign_an_alias_owned_by_another_city(): void
    {
        $service = app(CityPersistenceService::class);

        $firstCity = $service->persist([
            'name' => 'Rome',
            'country' => 'Italy',
            'iso_code' => 'IT',
            'latitude' => 41.9028,
            'longitude' => 12.4964,
            'external_provider' => 'geoapify',
            'external_id' => 'rome-test',
            'search_query' => 'Roma',
        ]);

        $secondCity = $service->persist([
            'name' => 'Milan',
            'country' => 'Italy',
            'iso_code' => 'IT',
            'latitude' => 45.4642,
            'longitude' => 9.1900,
            'external_provider' => 'geoapify',
            'external_id' => 'milan-test',
            'search_query' => 'Roma',
        ]);

        $this->assertDatabaseHas('city_aliases', [
            'city_id' => $firstCity->id,
            'normalized_alias' => 'roma',
        ]);

        $this->assertDatabaseMissing('city_aliases', [
            'city_id' => $secondCity->id,
            'normalized_alias' => 'roma',
        ]);
    }

    #[DataProvider('cityAliasNormalizationCases')]
    public function test_it_normalizes_city_aliases_consistently(
        string $input,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            CityAliasNormalizer::normalize($input)
        );
    }

    public static function cityAliasNormalizationCases(): array
    {
        return [
            'uppercase' => ['ROMA', 'roma'],
            'surrounding whitespace' => ['  Roma  ', 'roma'],
            'accented characters' => ['Múnich', 'munich'],
            'mixed case and accents' => [' SÃO PAULO ', 'sao paulo'],
            'empty string' => ['', ''],
        ];
    }

    public function test_it_does_not_learn_an_alias_when_search_matches_city_name_after_normalization(): void
    {
        $city = app(CityPersistenceService::class)->persist([
            'name' => 'Munich',
            'country' => 'Germany',
            'iso_code' => 'DE',
            'latitude' => 48.1372,
            'longitude' => 11.5756,
            'external_provider' => 'geoapify',
            'external_id' => 'munich-test',
            'search_query' => 'MUNICH',
        ]);

        $this->assertDatabaseMissing('city_aliases', [
            'city_id' => $city->id,
            'normalized_alias' => 'munich',
        ]);
    }


}