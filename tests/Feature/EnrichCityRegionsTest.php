<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * cities:enrich-regions (Fase 05): simulación por defecto, ciudades fusionadas
 * y sin coordenadas sin consultas, sin sobrescrituras ni escrituras parciales.
 *
 * Geoapify siempre simulado con Http::fake() (el TestCase bloquea cualquier
 * petición real) y solo vyamap_testing.
 */
class EnrichCityRegionsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Country> */
    private array $countries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        foreach (['US' => 'United States', 'MA' => 'Morocco'] as $iso => $name) {
            $this->countries[$iso] = Country::create(['name' => $name, 'iso_code' => $iso]);
        }
    }

    public function test_dry_run_plans_matches_without_writing(): void
    {
        [$newYork, $georgia] = $this->albanyPair();
        $this->fakeGeoapify(['Albany, US' => $this->albanyFeatures()]);
        $before = $this->cities();

        $writes = $this->countWrites(fn () => $this->artisan('cities:enrich-regions')
            ->expectsOutputToContain("+ #{$newYork->id} Albany (US) → NY")
            ->expectsOutputToContain('DRY RUN: no database records were modified.')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->cities());
        $this->assertNull($georgia->fresh()->region_code);
    }

    public function test_apply_writes_matches_within_10_km_with_a_single_request_per_group(): void
    {
        [$newYork, $georgia] = $this->albanyPair();
        $this->fakeGeoapify(['Albany, US' => $this->albanyFeatures()]);

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 2')
            ->assertSuccessful();

        $this->assertSame('NY', $newYork->fresh()->region_code);
        $this->assertSame('GA', $georgia->fresh()->region_code);
        Http::assertSentCount(1);
    }

    public function test_results_farther_than_10_km_are_not_applied(): void
    {
        [$newYork, $georgia] = $this->albanyPair();
        // Solo un resultado, a ~20 km de Albany (NY) y lejísimos de Georgia.
        $this->fakeGeoapify(['Albany, US' => [$this->feature(42.83, -73.76, 'NY')]]);

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain("#{$newYork->id} Albany: revisión, el resultado más cercano está a")
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        $this->assertNull($newYork->fresh()->region_code);
        $this->assertNull($georgia->fresh()->region_code);
    }

    public function test_existing_region_codes_are_never_overwritten(): void
    {
        [$newYork, $georgia] = $this->albanyPair(['region_code' => 'XX']);
        $this->fakeGeoapify(['Albany, US' => $this->albanyFeatures()]);

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();

        $this->assertSame('XX', $newYork->fresh()->region_code);
        $this->assertSame('GA', $georgia->fresh()->region_code);
    }

    public function test_merged_cities_never_form_groups_query_the_api_or_receive_codes(): void
    {
        // Tánger: #637 canónica y #3139 fusionada en ella.
        $canonical = $this->city('Tangier', 'MA', 35.7600, -5.8300);
        $merged = $this->city('Tangier', 'MA', 35.7696, -5.8034, ['merged_into_id' => $canonical->id]);
        Http::fake();
        $before = $this->cities();

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame($before, $this->cities());
        $this->assertNull($merged->fresh()->region_code);
    }

    public function test_merged_city_inside_a_real_homonym_group_is_excluded(): void
    {
        [$newYork, $georgia] = $this->albanyPair();
        $merged = $this->city('Albany', 'US', 42.65, -73.75, ['merged_into_id' => $newYork->id]);
        $this->fakeGeoapify(['Albany, US' => $this->albanyFeatures()]);

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 2')
            ->assertSuccessful();

        $this->assertNull($merged->fresh()->region_code);
        $this->assertSame('NY', $newYork->fresh()->region_code);
    }

    /**
     * @return array<string, array{?float, ?float}>
     */
    public static function invalidCoordinates(): array
    {
        return [
            'sin coordenadas' => [null, null],
            'latitud fuera de rango' => [95.0, -73.75],
            'longitud fuera de rango' => [42.65, -190.0],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCoordinates')]
    public function test_cities_without_valid_coordinates_do_not_query_the_api(?float $latitude, ?float $longitude): void
    {
        $first = $this->city('Springfield', 'US', $latitude, $longitude);
        $second = $this->city('Springfield', 'US', $latitude, $longitude);
        Http::fake();

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain("#{$first->id} Springfield: coordenadas no válidas, no se consulta la API")
            ->expectsOutputToContain("#{$second->id} Springfield: coordenadas no válidas, no se consulta la API")
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{callable, string}>
     */
    public static function apiFailures(): array
    {
        return [
            'error HTTP' => [fn () => Http::response(['message' => 'quota'], 429), 'HTTP 429'],
            'respuesta sin features' => [fn () => Http::response(['results' => []], 200), 'invalid response'],
            'conexión' => [fn () => throw new ConnectionException('timeout'), 'request failed'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('apiFailures')]
    public function test_api_failures_end_without_any_partial_write(callable $failure, string $reason): void
    {
        // Albany responde bien; Springfield (procesado después) falla.
        $this->albanyPair();
        $this->city('Springfield', 'US', 39.80, -89.65);
        $this->city('Springfield', 'US', 37.21, -93.29);

        Http::fake(function (Request $request) use ($failure) {
            return str_contains($request->url(), 'Albany')
                ? Http::response(['features' => $this->albanyFeatures()])
                : $failure();
        });

        $before = $this->cities();

        $writes = $this->countWrites(fn () => $this->artisan('cities:enrich-regions', ['--apply' => true])
            // Una sola expectativa por línea: el motivo va en el mismo mensaje.
            ->expectsOutputToContain("Geoapify error: {$reason}")
            ->assertFailed());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->cities());
    }

    public function test_running_twice_is_idempotent(): void
    {
        $this->albanyPair();
        $this->fakeGeoapify(['Albany, US' => $this->albanyFeatures()]);

        $this->artisan('cities:enrich-regions', ['--apply' => true])->assertSuccessful();
        $after = $this->cities();

        $writes = $this->countWrites(fn () => $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain('UPDATED: 0')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($after, $this->cities());
    }

    public function test_summary_distinguishes_every_outcome(): void
    {
        // Albany: una actualizada y otra con region_code previo.
        $this->albanyPair(['region_code' => 'NY']);
        // Springfield: sin coincidencia.
        $this->city('Springfield', 'US', 39.80, -89.65);
        $this->city('Springfield', 'US', 37.21, -93.29);
        // Columbus: coincidencia sin código y una sin coordenadas.
        $this->city('Columbus', 'US', 39.96, -83.00);
        $this->city('Columbus', 'US', null, null);

        $this->fakeGeoapify([
            'Albany, US' => $this->albanyFeatures(),
            'Springfield, US' => [],
            'Columbus, US' => [$this->feature(39.96, -83.00, null)],
        ]);

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsTable(['resultado', 'ciudades'], [
                ['grupos de homónimos (sin fusionadas)', 3],
                ['actualizadas', 1],
                ['omitidas: region_code ya asignado', 1],
                ['omitidas: coordenadas no válidas', 1],
                ['sin coincidencia', 2],
                ['revisión (más de 10 km)', 0],
                ['coincidencia sin código de región', 1],
                ['errores de la API', 0],
            ])
            ->expectsOutputToContain('UPDATED: 1')
            ->assertSuccessful();
    }

    public function test_missing_api_key_fails_without_requests(): void
    {
        config(['services.geoapify.key' => null]);
        $this->albanyPair();
        Http::fake();

        $this->artisan('cities:enrich-regions', ['--apply' => true])
            ->expectsOutputToContain('Geoapify API key is not configured.')
            ->assertFailed();

        Http::assertNothingSent();
    }

    /**
     * @return array{City, City} Albany (NY) y Albany (GA).
     */
    private function albanyPair(array $newYorkAttributes = []): array
    {
        return [
            $this->city('Albany', 'US', 42.6526, -73.7562, $newYorkAttributes),
            $this->city('Albany', 'US', 31.5785, -84.1557),
        ];
    }

    private function albanyFeatures(): array
    {
        return [$this->feature(42.6511, -73.7540, 'ny'), $this->feature(31.5800, -84.1600, 'GA')];
    }

    private function feature(float $lat, float $lon, ?string $stateCode): array
    {
        return [
            'type' => 'Feature',
            'properties' => array_filter(['name' => 'X', 'state_code' => $stateCode]),
            'geometry' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
        ];
    }

    /**
     * @param  array<string, array>  $byQuery  Texto de la consulta → features.
     */
    private function fakeGeoapify(array $byQuery): void
    {
        Http::fake(function (Request $request) use ($byQuery) {
            foreach ($byQuery as $text => $features) {
                if (($request->data()['text'] ?? null) === $text) {
                    return Http::response(['type' => 'FeatureCollection', 'features' => $features]);
                }
            }

            return Http::response(['features' => []]);
        });
    }

    private function city(string $name, string $iso, ?float $latitude, ?float $longitude, array $attributes = []): City
    {
        return City::create([
            'country_id' => $this->countries[$iso]->id,
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            ...$attributes,
        ]);
    }

    private function cities(): array
    {
        return City::query()->orderBy('id')->get()->toArray();
    }

    private function countWrites(callable $callback): int
    {
        $writes = 0;

        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes++;
            }
        });

        $callback();

        return $writes;
    }
}
