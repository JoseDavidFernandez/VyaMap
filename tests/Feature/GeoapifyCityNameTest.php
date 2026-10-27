<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Services\CityPersistenceService;
use App\Services\CitySearchService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Nombre de una ciudad que llega de Geoapify (CitySearchService).
 *
 * Geoapify devuelve a veces etiquetas de OSM en el campo city: para la Ciudad
 * del Vaticano, city = "yes" y name = "Vatican City" (respuesta real del
 * 17-10-2026). Se toma el primer nombre válido (city y después name) y, si no
 * hay ninguno, el resultado se descarta: nunca se ofrece ni se guarda.
 *
 * Sin base de datos: no usa RefreshDatabase (no vacía vyamap_testing). La
 * persistencia se sustituye por un mock y Geoapify se simula con Http::fake();
 * TestCase ya bloquea cualquier petición HTTP real.
 */
class GeoapifyCityNameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    public function test_an_osm_tag_in_the_city_field_falls_back_to_the_name_field(): void
    {
        $this->fakeGeoapify([$this->geoapifyResult(city: 'yes', name: 'Vatican City', country: 'Vatican City', code: 'va', id: 'vatican')]);

        $result = app(CitySearchService::class)->verifyGeoapifyCity('Ciudad del Vaticano', 'vatican');

        $this->assertSame('Vatican City', $result['name']);
        $this->assertSame('VA', $result['iso_code']);
        Http::assertSentCount(1);
    }

    public function test_a_valid_city_field_is_still_preferred(): void
    {
        $this->fakeGeoapify([$this->geoapifyResult(city: 'Bled', name: 'Občina Bled', country: 'Slovenia', code: 'si', id: 'bled')]);

        $result = app(CitySearchService::class)->verifyGeoapifyCity('Bled', 'bled');

        $this->assertSame('Bled', $result['name']);
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    public static function namesWithoutAValidCandidate(): array
    {
        return [
            'etiqueta y sin name' => ['yes', null],
            'dos etiquetas' => ['yes', 'No'],
            'sin letras' => ['123', ' '],
            'vacíos' => ['', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('namesWithoutAValidCandidate')]
    public function test_a_result_without_a_valid_name_is_never_offered_or_verified(mixed $city, mixed $name): void
    {
        $this->fakeGeoapify([
            $this->geoapifyResult(city: $city, name: $name, country: 'Vatican City', code: 'va', id: 'invalid'),
            $this->geoapifyResult(city: 'Rome', name: 'Roma', country: 'Italy', code: 'it', id: 'rome'),
        ]);

        $service = app(CitySearchService::class);

        // El resultado inválido no está entre los que se pueden elegir; el válido, sí.
        $this->assertNull($service->verifyGeoapifyCity('Vatican City', 'invalid'));
        $this->assertSame('Rome', $service->verifyGeoapifyCity('Vatican City', 'rome')['name']);
    }

    public function test_creating_the_city_never_saves_an_osm_tag_as_its_name(): void
    {
        $this->fakeGeoapify([$this->geoapifyResult(city: 'yes', name: 'Vatican City', country: 'Vatican City', code: 'va', id: 'vatican')]);

        $persistence = Mockery::mock(CityPersistenceService::class);
        $persistence->shouldReceive('persist')
            ->once()
            ->withArgs(fn (array $data) => $data['name'] === 'Vatican City' && $data['iso_code'] === 'VA')
            ->andReturn((new City(['name' => 'Vatican City', 'country_id' => 1]))->forceFill(['id' => 1]));
        $this->app->instance(CityPersistenceService::class, $persistence);

        $this->actingAs((new User(['name' => 'Tester', 'email' => 'tester@example.test']))->forceFill(['id' => 1, 'email_verified_at' => now()]))
            ->postJson('/cities', ['external_provider' => 'geoapify', 'external_id' => 'vatican', 'search_query' => 'Ciudad del Vaticano'])
            ->assertCreated()
            ->assertJsonPath('city.name', 'Vatican City');
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function fakeGeoapify(array $results): void
    {
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => $results])]);
    }

    private function geoapifyResult(mixed $city, mixed $name, string $country, string $code, string $id): array
    {
        return array_filter([
            'result_type' => 'city',
            'city' => $city,
            'name' => $name,
            'country' => $country,
            'country_code' => $code,
            'lat' => 41.9,
            'lon' => 12.45,
            'place_id' => $id,
            'rank' => ['importance' => 0.5],
        ], fn ($value) => $value !== null);
    }
}
