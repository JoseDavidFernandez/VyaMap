<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Models\User;
use App\Services\CityPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_exact_match_is_never_cut_off_by_the_result_limit(): void
    {
        $peru = $this->country('Peru', 'PE');

        // 12 ciudades que contienen "ica" y van antes alfabéticamente.
        foreach (['Africana', 'America', 'Amica', 'Angelica', 'Basilica', 'Bica', 'Chica', 'Dica', 'Erica', 'Fica', 'Gica', 'Hica'] as $name) {
            $this->city($peru, $name);
        }
        $ica = $this->city($peru, 'Ica');

        $this->actingAs($this->user)
            ->getJson('/cities/search?q=Ica')
            ->assertOk()
            ->assertJsonPath('results.0.id', $ica->id)
            ->assertJsonPath('external_searched', false);
    }

    public function test_exact_name_and_alias_matches_come_before_partial_matches(): void
    {
        $italy = $this->country('Italy', 'IT');
        $australia = $this->country('Australia', 'AU');
        $russia = $this->country('Russia', 'RU');

        $kostroma = $this->city($russia, 'Kostroma');
        $romaAu = $this->city($australia, 'Roma');
        $rome = $this->city($italy, 'Rome');
        CityAlias::create(['city_id' => $rome->id, 'alias' => 'Roma', 'normalized_alias' => 'roma']);

        $results = $this->actingAs($this->user)->getJson('/cities/search?q=Roma')->assertOk()->json('results');
        $ids = array_column($results, 'id');

        $this->assertEqualsCanonicalizing([$romaAu->id, $rome->id], array_slice($ids, 0, 2));
        $this->assertSame($kostroma->id, $ids[2]);
    }

    public function test_exact_local_match_skips_geoapify_unless_worldwide_search_is_requested(): void
    {
        $usa = $this->country('United States', 'US');
        $oregon = $this->city($usa, 'Portland', 'OR', 45.52, -122.68);

        Http::fake([
            'https://api.geoapify.com/*' => Http::response(['results' => [
                // La misma Portland (OR) que ya está en el catálogo: no debe repetirse.
                $this->geoapify('Portland', 'us', 'OR', 45.51, -122.67, 'geo-portland-or'),
                // Otra Portland que no está en el catálogo.
                $this->geoapify('Portland', 'us', 'ME', 43.66, -70.26, 'geo-portland-me'),
            ]]),
        ]);

        $this->actingAs($this->user)
            ->getJson('/cities/search?q=Portland')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $oregon->id)
            ->assertJsonPath('external_searched', false);
        Http::assertNothingSent();

        $this->actingAs($this->user)
            ->getJson('/cities/search?q=Portland&external=1')
            ->assertOk()
            ->assertJsonPath('external_searched', true)
            ->assertJsonPath('external_unavailable', false)
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.id', $oregon->id)
            ->assertJsonPath('results.1.external_id', 'geo-portland-me')
            ->assertJsonPath('results.1.region_code', 'ME');
    }

    public function test_worldwide_search_reports_when_geoapify_is_unavailable(): void
    {
        $this->city($this->country('Spain', 'ES'), 'Madrid');

        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($this->user)
            ->getJson('/cities/search?q=Madrid&external=1')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('external_searched', true)
            ->assertJsonPath('external_unavailable', true);
    }

    public function test_like_wildcards_are_searched_literally(): void
    {
        $spain = $this->country('Spain', 'ES');
        $this->city($spain, 'Madrid');
        $this->city($spain, 'Malaga');
        Http::fake(['https://api.geoapify.com/*' => Http::response(['results' => []])]);

        $this->actingAs($this->user)
            ->getJson('/cities/search?q=' . urlencode('M%'))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $this->actingAs($this->user)
            ->getJson('/cities/search?q=' . urlencode('Ma_rid'))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        Airport::create(['city_id' => City::first()->id, 'name' => 'Barajas', 'iata_code' => 'MAD', 'timezone' => 'Europe/Madrid']);

        $this->actingAs($this->user)
            ->getJson('/airports/search?q=' . urlencode('%%'))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $this->actingAs($this->user)
            ->getJson('/airports/search?q=' . str_repeat('a', 101))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function aliasCases(): array
    {
        return [
            'nombre en otro idioma' => ['Tanger', 'Tangier', true],
            'termina en límite de palabra' => ['Quebec', 'Quebec City', true],
            'a medio escribir' => ['Tang', 'Tangier', false],
            'demasiado corto' => ['Rom', 'Rome', false],
            'con paréntesis' => ['Paris (Francia)', 'Paris', false],
            'con números' => ['Paris 2', 'Paris', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('aliasCases')]
    public function test_alias_learning_rules(string $query, string $cityName, bool $learned): void
    {
        $city = app(CityPersistenceService::class)->persist([
            'name' => $cityName,
            'country' => 'Testland',
            'iso_code' => 'TT',
            'latitude' => 10,
            'longitude' => 10,
            'external_provider' => 'geoapify',
            'external_id' => 'geo-' . md5($cityName),
            'search_query' => $query,
        ]);

        $this->assertSame(
            $learned,
            CityAlias::query()->where('city_id', $city->id)->exists(),
            "Alias \"{$query}\" para {$cityName}"
        );
    }

    public function test_alias_audit_lists_invalid_aliases_and_deletes_them_only_when_confirmed(): void
    {
        $rome = $this->city($this->country('Italy', 'IT'), 'Rome');
        $valid = CityAlias::create(['city_id' => $rome->id, 'alias' => 'Roma', 'normalized_alias' => 'roma']);
        $short = CityAlias::create(['city_id' => $rome->id, 'alias' => 'Rom', 'normalized_alias' => 'rom']);
        $junk = CityAlias::create(['city_id' => $rome->id, 'alias' => 'Roma (Italia)', 'normalized_alias' => 'roma (italia)']);

        $this->artisan('cities:audit-aliases')
            ->expectsOutputToContain('2 alias no cumplen las reglas')
            ->assertSuccessful();
        $this->assertDatabaseCount('city_aliases', 3);

        $this->artisan('cities:audit-aliases', ['--delete' => true])
            ->expectsConfirmation('¿Borrar 2 alias?', 'no')
            ->assertFailed();
        $this->assertDatabaseCount('city_aliases', 3);

        $this->artisan('cities:audit-aliases', ['--delete' => true])
            ->expectsConfirmation('¿Borrar 2 alias?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseHas('city_aliases', ['id' => $valid->id]);
        $this->assertDatabaseMissing('city_aliases', ['id' => $short->id]);
        $this->assertDatabaseMissing('city_aliases', ['id' => $junk->id]);
    }

    private function country(string $name, string $iso): Country
    {
        return Country::create(['name' => $name, 'iso_code' => $iso]);
    }

    private function city(Country $country, string $name, ?string $region = null, float $lat = 10, float $lon = 10): City
    {
        return City::create([
            'country_id' => $country->id,
            'name' => $name,
            'region_code' => $region,
            'latitude' => $lat,
            'longitude' => $lon,
        ]);
    }

    private function geoapify(string $name, string $countryCode, string $state, float $lat, float $lon, string $placeId): array
    {
        return [
            'name' => $name,
            'city' => $name,
            'country' => 'United States',
            'country_code' => $countryCode,
            'state_code' => $state,
            'lat' => $lat,
            'lon' => $lon,
            'place_id' => $placeId,
            'result_type' => 'city',
            'rank' => ['importance' => 0.5],
        ];
    }
}
