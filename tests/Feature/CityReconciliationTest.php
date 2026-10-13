<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\CityReconciliation;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Services\CityPersistenceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Datos ficticios que reproducen el caso de Tánger: una ciudad importada con
 * aeropuerto y la misma ciudad creada después desde Geoapify con una visita.
 */
class CityReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Country $morocco;

    private City $imported;

    private City $duplicate;

    private Airport $airport;

    private Visit $visit;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->morocco = Country::create(['name' => 'Morocco', 'iso_code' => 'MA']);

        $this->imported = City::create([
            'country_id' => $this->morocco->id,
            'name' => 'Tangier',
            'latitude' => 35.76,
            'longitude' => -5.83,
        ]);

        $this->duplicate = City::create([
            'country_id' => $this->morocco->id,
            'name' => 'Tangier',
            'latitude' => 35.7696,
            'longitude' => -5.8034,
            'external_provider' => 'geoapify',
            'external_id' => 'geoapify-tangier-test',
        ]);

        $this->airport = Airport::create([
            'city_id' => $this->imported->id,
            'name' => 'Test Tangier Airport',
            'iata_code' => 'TNX',
            'timezone' => 'Africa/Casablanca',
        ]);

        $user = User::factory()->create();

        $this->trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'Trip',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-10',
        ]);

        $this->visit = Visit::create([
            'user_id' => $user->id,
            'trip_id' => $this->trip->id,
            'city_id' => $this->duplicate->id,
            'visited_from' => '2026-01-02',
            'visited_until' => '2026-01-03',
        ]);
    }

    public function test_dry_run_is_the_default_and_changes_nothing(): void
    {
        $this->fakeGeoapify();
        $before = $this->snapshot();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
        ])
            ->expectsOutputToContain('Simulación')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('city_reconciliations', 0);
    }

    public function test_reconciliation_moves_the_visit_and_assigns_the_verified_external_id(): void
    {
        $this->fakeGeoapify();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--execute' => true,
        ])
            ->expectsConfirmation('¿Aplicar la fusión?', 'yes')
            ->assertSuccessful();

        $this->assertSame($this->imported->id, $this->visit->fresh()->city_id);
        $this->assertSame($this->imported->id, $this->airport->fresh()->city_id);

        $imported = $this->imported->fresh();
        $this->assertSame('geoapify', $imported->external_provider);
        $this->assertSame('geoapify-tangier-test', $imported->external_id);

        // El duplicado se conserva, marcado como fusionado.
        $duplicate = $this->duplicate->fresh();
        $this->assertNotNull($duplicate);
        $this->assertSame($this->imported->id, $duplicate->merged_into_id);
        $this->assertSame('geoapify-merged', $duplicate->external_provider);
        $this->assertSame('geoapify-tangier-test', $duplicate->external_id);

        $reconciliation = CityReconciliation::sole();
        $this->assertSame([$this->visit->id], $reconciliation->changes['moved']['visits']);
        $this->assertTrue($reconciliation->changes['external_id_assigned']);
    }

    public function test_reconciliation_can_be_reverted(): void
    {
        $this->fakeGeoapify();
        $before = $this->snapshot();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--execute' => true,
        ])->expectsConfirmation('¿Aplicar la fusión?', 'yes')->assertSuccessful();

        $reconciliation = CityReconciliation::sole();

        // La simulación de la reversión tampoco cambia nada.
        $this->artisan('cities:reconcile', ['--revert' => $reconciliation->id])
            ->expectsOutputToContain('Simulación')
            ->assertSuccessful();
        $this->assertSame($this->imported->id, $this->visit->fresh()->city_id);

        $this->artisan('cities:reconcile', ['--revert' => $reconciliation->id, '--execute' => true])
            ->expectsConfirmation('¿Revertir la fusión?', 'yes')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertNotNull($reconciliation->fresh()->reverted_at);

        // Una reconciliación revertida no se puede revertir otra vez.
        $this->artisan('cities:reconcile', ['--revert' => $reconciliation->id, '--execute' => true])
            ->expectsConfirmation('¿Revertir la fusión?', 'yes')
            ->assertFailed();
    }

    public function test_external_id_is_not_assigned_when_geoapify_does_not_confirm_it(): void
    {
        $this->fakeGeoapify(placeId: 'some-other-place');
        $before = $this->snapshot();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--execute' => true,
        ])
            ->expectsOutputToContain('Geoapify no devuelve ese identificador')
            ->assertFailed();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_external_id_in_another_country_is_rejected(): void
    {
        $this->fakeGeoapify(countryCode: 'es');

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--execute' => true,
        ])
            ->expectsOutputToContain('otro país')
            ->assertFailed();

        $this->assertSame($this->duplicate->id, $this->visit->fresh()->city_id);
    }

    public function test_reconciliation_aborts_when_geoapify_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--execute' => true,
        ])
            ->expectsOutputToContain('Geoapify no está disponible')
            ->assertFailed();

        $this->assertSame($this->duplicate->id, $this->visit->fresh()->city_id);
    }

    public function test_reconciliation_without_external_id_does_not_call_geoapify(): void
    {
        Http::fake();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--without-external-id' => true,
            '--execute' => true,
        ])->expectsConfirmation('¿Aplicar la fusión?', 'yes')->assertSuccessful();

        Http::assertNothingSent();

        $this->assertSame($this->imported->id, $this->visit->fresh()->city_id);
        $this->assertNull($this->imported->fresh()->external_id);
        $this->assertSame('geoapify', $this->duplicate->fresh()->external_provider);
    }

    public function test_cities_in_different_countries_are_rejected(): void
    {
        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $this->duplicate->update(['country_id' => $spain->id]);

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--without-external-id' => true,
            '--execute' => true,
        ])
            ->expectsOutputToContain('países distintos')
            ->assertFailed();
    }

    public function test_cities_with_different_names_are_rejected_unless_aliased(): void
    {
        $this->duplicate->update(['name' => 'Tanger']);

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--without-external-id' => true,
        ])
            ->expectsOutputToContain('nombre normalizado')
            ->assertFailed();

        CityAlias::create([
            'city_id' => $this->imported->id,
            'alias' => 'Tanger',
            'normalized_alias' => 'tanger',
        ]);

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--without-external-id' => true,
        ])->assertSuccessful();
    }

    public function test_cities_too_far_apart_are_rejected(): void
    {
        $this->duplicate->update(['latitude' => 34.02, 'longitude' => -6.84]);

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--without-external-id' => true,
        ])
            ->expectsOutputToContain('km (máximo')
            ->assertFailed();
    }

    public function test_conflicting_visits_in_the_same_trip_abort_the_reconciliation(): void
    {
        Visit::create([
            'user_id' => $this->trip->user_id,
            'trip_id' => $this->trip->id,
            'city_id' => $this->imported->id,
            'visited_from' => '2026-01-04',
            'visited_until' => '2026-01-05',
        ]);

        $before = $this->snapshot();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--without-external-id' => true,
            '--execute' => true,
        ])
            ->expectsOutputToContain('Conflicto')
            ->assertFailed();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_already_merged_city_cannot_be_merged_again(): void
    {
        $this->fakeGeoapify();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--execute' => true,
        ])->expectsConfirmation('¿Aplicar la fusión?', 'yes')->assertSuccessful();

        $this->artisan('cities:reconcile', [
            'duplicate' => $this->duplicate->id,
            '--into' => $this->imported->id,
            '--without-external-id' => true,
        ])
            ->expectsOutputToContain('ya está fusionada')
            ->assertFailed();
    }

    public function test_merged_cities_are_excluded_from_search_and_visit_selection(): void
    {
        $this->duplicate->update(['merged_into_id' => $this->imported->id]);
        $user = User::find($this->trip->user_id);

        $this->actingAs($user)
            ->getJson('/cities/search?q=Tangier')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $this->imported->id);

        $this->actingAs($user)
            ->postJson("/trips/{$this->trip->id}/visits", [
                'city_id' => $this->duplicate->id,
                'visited_from' => '2026-01-05',
                'visited_until' => '2026-01-06',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['city_id']);
    }

    public function test_persisting_a_geoapify_city_reuses_a_nearby_city_with_the_same_name(): void
    {
        $city = app(CityPersistenceService::class)->persist([
            'name' => 'Tangier',
            'country' => 'Morocco',
            'iso_code' => 'MA',
            'region_code' => null,
            'latitude' => 35.77,
            'longitude' => -5.80,
            'external_provider' => 'geoapify',
            'external_id' => 'another-geoapify-tangier',
        ]);

        // No crea un tercer Tangier: reutiliza el más cercano que no está fusionado.
        $this->assertSame(2, City::where('name', 'Tangier')->count());
        $this->assertContains($city->id, [$this->imported->id, $this->duplicate->id]);
    }

    public function test_persisting_assigns_the_external_id_to_a_nearby_city_without_one(): void
    {
        $this->duplicate->update(['merged_into_id' => $this->imported->id]);

        $city = app(CityPersistenceService::class)->persist([
            'name' => 'Tangier',
            'country' => 'Morocco',
            'iso_code' => 'MA',
            'latitude' => 35.761,
            'longitude' => -5.831,
            'external_provider' => 'geoapify',
            'external_id' => 'fresh-geoapify-id',
        ]);

        $this->assertSame($this->imported->id, $city->id);
        $this->assertSame('fresh-geoapify-id', $this->imported->fresh()->external_id);
    }

    public function test_persisting_a_merged_external_id_returns_the_canonical_city(): void
    {
        $this->duplicate->update(['merged_into_id' => $this->imported->id]);

        $city = app(CityPersistenceService::class)->persist([
            'name' => 'Tangier',
            'country' => 'Morocco',
            'iso_code' => 'MA',
            'latitude' => 35.7696,
            'longitude' => -5.8034,
            'external_provider' => 'geoapify',
            'external_id' => 'geoapify-tangier-test',
        ]);

        $this->assertSame($this->imported->id, $city->id);
    }

    public function test_the_same_external_id_cannot_be_stored_twice(): void
    {
        $this->expectException(QueryException::class);

        City::create([
            'country_id' => $this->morocco->id,
            'name' => 'Copy',
            'external_provider' => 'geoapify',
            'external_id' => 'geoapify-tangier-test',
        ]);
    }

    private function fakeGeoapify(string $placeId = 'geoapify-tangier-test', string $countryCode = 'ma'): void
    {
        Http::fake([
            'https://api.geoapify.com/v1/geocode/search*' => Http::response([
                'results' => [[
                    'name' => 'Tangier',
                    'city' => 'Tangier',
                    'country' => 'Morocco',
                    'country_code' => $countryCode,
                    'lat' => 35.7696,
                    'lon' => -5.8034,
                    'place_id' => $placeId,
                    'result_type' => 'city',
                    'rank' => ['importance' => 0.7],
                ]],
            ]),
        ]);
    }

    /**
     * Estado de las filas implicadas, para comprobar que nada cambia.
     */
    private function snapshot(): array
    {
        return [
            'cities' => City::query()->orderBy('id')->get(['id', 'external_provider', 'external_id', 'merged_into_id'])->toArray(),
            'visits' => Visit::query()->orderBy('id')->get(['id', 'city_id'])->toArray(),
            'airports' => Airport::query()->orderBy('id')->get(['id', 'city_id'])->toArray(),
        ];
    }
}
