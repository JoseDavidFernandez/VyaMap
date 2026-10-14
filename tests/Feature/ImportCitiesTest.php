<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * cities:import (Fase 05, P1.1): no crea duplicados (coordenadas corregidas,
 * acentos, alias, ciudades fusionadas), nunca modifica ciudades existentes
 * ni relaciones y es repetible.
 *
 * Cada prueba usa un storage temporal con su propio CSV: el catálogo real
 * (storage/app/data) nunca se lee ni se modifica.
 */
class ImportCitiesTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'id_original,iata_code,icao_code,airport_name,city_name,country_name,country_iso_code,city_latitude,city_longitude';

    private string $storage;

    /** @var array<string, Country> */
    private array $countries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . '/vyamap-import-cities-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists("{$this->storage}/app/data");
        $this->app->useStoragePath($this->storage);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->storage));

        $this->assertStringStartsWith(sys_get_temp_dir(), storage_path('app/data'));

        foreach (['ES' => 'Spain', 'US' => 'United States', 'UA' => 'Ukraine', 'BR' => 'Brazil', 'MA' => 'Morocco', 'JP' => 'Japan'] as $iso => $name) {
            $this->countries[$iso] = Country::create(['name' => $name, 'iso_code' => $iso]);
        }
    }

    public function test_new_cities_are_created_once_even_when_several_airports_share_them(): void
    {
        $this->csv([
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('TOJ', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('BCN', 'Barcelona', 'ES', '41.3900', '2.1700'),
        ]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('+ Madrid (ES)')
            ->expectsOutputToContain('+ Barcelona (ES)')
            ->assertSuccessful();

        $this->assertSame(2, City::count());

        $madrid = City::where('name', 'Madrid')->firstOrFail();
        $this->assertSame($this->countries['ES']->id, $madrid->country_id);
        $this->assertEquals(40.42, (float) $madrid->latitude);
        $this->assertEquals(-3.70, (float) $madrid->longitude);
        $this->assertNull($madrid->region_code);
        $this->assertNull($madrid->external_id);
        $this->assertNull($madrid->merged_into_id);
    }

    public function test_running_the_same_csv_twice_writes_nothing_the_second_time(): void
    {
        $this->csv([
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('ALB', 'Albany', 'US', '42.6500', '-73.7500'),
            $this->row('ABY', 'Albany', 'US', '31.5800', '-84.1600'),
        ]);

        $this->artisan('cities:import')->assertSuccessful();
        $afterFirst = $this->cities();

        $this->travel(1)->hours();
        $writes = $this->countWrites(fn () => $this->artisan('cities:import')->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($afterFirst, $this->cities());
        $this->assertSame(3, City::count());
    }

    public function test_corrected_coordinates_match_the_existing_city_without_overwriting_it(): void
    {
        $madrid = $this->city('Madrid', 'ES', 40.4168, -3.7038, [
            'region_code' => 'MD',
            'external_provider' => 'geoapify',
            'external_id' => 'geo-madrid',
        ]);
        $before = $madrid->fresh()->toArray();

        $this->csv([$this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('coordenadas distintas')
            ->assertSuccessful();

        $this->assertSame(1, City::count());
        $this->assertSame($before, $madrid->fresh()->toArray());
    }

    public function test_homonyms_far_apart_are_separate_cities(): void
    {
        $this->city('Albany', 'US', 42.6500, -73.7500, ['region_code' => 'NY']);

        $this->csv([
            $this->row('ALB', 'Albany', 'US', '42.6500', '-73.7500'),
            $this->row('ABY', 'Albany', 'US', '31.5800', '-84.1600'),
        ]);

        $this->artisan('cities:import')->assertSuccessful();

        $this->assertSame(2, City::where('name', 'Albany')->count());
        $this->assertSame(1, City::where('name', 'Albany')->where('latitude', 31.58)->count());
    }

    public function test_a_merged_city_resolves_to_its_canonical_city_and_creates_nothing(): void
    {
        $canonical = $this->city('Tangier', 'MA', 35.7673, -5.7997);
        $duplicate = $this->city('Tangier', 'MA', 35.7595, -5.8340, ['merged_into_id' => $canonical->id]);

        $this->csv([
            // Coordenadas exactas del duplicado fusionado y otras cercanas.
            $this->row('TNG', 'Tangier', 'MA', '35.7595', '-5.8340'),
            $this->row('XTN', 'Tangier', 'MA', '35.7700', '-5.8100'),
        ]);

        $before = $this->cities();

        $this->artisan('cities:import')
            ->expectsOutputToContain("fusionada en #{$canonical->id}")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
        $this->assertSame($canonical->id, $duplicate->fresh()->merged_into_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unresolvableMerges(): array
    {
        return [
            'ciclo' => ['cycle'],
            'destino en otro país' => ['other-country'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unresolvableMerges')]
    public function test_unresolvable_merges_are_skipped_without_creating_cities(string $case): void
    {
        $a = $this->city('Tangier', 'MA', 35.7673, -5.7997);

        if ($case === 'cycle') {
            $b = $this->city('Tangier', 'MA', 35.7595, -5.8340, ['merged_into_id' => $a->id]);
            DB::table('cities')->where('id', $a->id)->update(['merged_into_id' => $b->id]);
        } else {
            $elsewhere = $this->city('Tangier', 'ES', 40.0, -3.0);
            DB::table('cities')->where('id', $a->id)->update(['merged_into_id' => $elsewhere->id]);
        }

        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7673', '-5.7997')]);
        $before = $this->cities();

        $this->artisan('cities:import')
            ->expectsOutputToContain('fusión no resoluble')
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_ambiguous_matches_within_25_km_are_skipped(): void
    {
        $this->city('Tangier', 'MA', 35.7673, -5.7997);
        $this->city('Tangier', 'MA', 35.7595, -5.8340);
        $before = $this->cities();

        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7700', '-5.8100')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('coincidencia ambigua')
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_accent_case_and_alias_variants_match_the_existing_city(): void
    {
        $saoPaulo = $this->city('São Paulo', 'BR', -23.5505, -46.6333);
        $kyiv = $this->city('Kyiv', 'UA', 50.4501, 30.5234);
        CityAlias::create(['city_id' => $kyiv->id, 'alias' => 'Kiev', 'normalized_alias' => 'kiev']);
        $before = $this->cities();

        $this->csv([
            $this->row('GRU', 'Sao Paulo', 'BR', '-23.5500', '-46.6300'),
            $this->row('CGH', 'SÃO PAULO', 'BR', '-23.5500', '-46.6300'),
            $this->row('KBP', 'Kiev', 'UA', '50.4500', '30.5200'),
        ]);

        $this->artisan('cities:import')->assertSuccessful();

        $this->assertSame($before, $this->cities());
        $this->assertSame(1, CityAlias::count());
        $this->assertNotNull($saoPaulo->fresh());
    }

    public function test_possible_rename_within_2_km_is_reported_and_not_created(): void
    {
        $kyiv = $this->city('Kyiv', 'UA', 50.4501, 30.5234);

        $this->csv([
            // ~0,3 km de Kyiv, con otro nombre y sin alias: posible renombrado.
            $this->row('KBP', 'Kiev', 'UA', '50.4500', '30.5200'),
            // ~11 km: otra ciudad legítima.
            $this->row('IEV', 'Brovary', 'UA', '50.5100', '30.6400'),
        ]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('posible renombrado de «Kyiv»')
            ->assertSuccessful();

        $this->assertSame(0, City::where('name', 'Kiev')->count());
        $this->assertSame(1, City::where('name', 'Brovary')->count());

        // Solo una advertencia: ni alias ni fusiones automáticas.
        $this->assertSame(0, CityAlias::count());
        $this->assertNull($kyiv->fresh()->merged_into_id);
    }

    public function test_existing_relations_and_enriched_fields_are_untouched(): void
    {
        $madrid = $this->city('Madrid', 'ES', 40.4168, -3.7038, ['region_code' => 'MD', 'external_provider' => 'geoapify', 'external_id' => 'geo-mad']);
        $user = User::factory()->create();
        $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05']);
        Visit::create(['user_id' => $user->id, 'trip_id' => $trip->id, 'city_id' => $madrid->id, 'visited_from' => '2026-01-01', 'visited_until' => '2026-01-02']);
        Airport::create(['city_id' => $madrid->id, 'name' => 'Barajas', 'iata_code' => 'MAD', 'timezone' => 'Europe/Madrid']);
        CityAlias::create(['city_id' => $madrid->id, 'alias' => 'Madriz', 'normalized_alias' => 'madriz']);
        Place::create(['city_id' => $madrid->id, 'name' => 'Prado']);

        $before = $this->relations();

        $this->csv([
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('BCN', 'Barcelona', 'ES', '41.3900', '2.1700'),
        ]);

        $this->artisan('cities:import')->assertSuccessful();

        // Barcelona es un alta legítima; todo lo que ya existía sigue igual.
        $barcelona = City::where('name', 'Barcelona')->sole();
        $after = $this->relations();
        $after['cities'] = array_values(array_filter($after['cities'], fn ($city) => $city['id'] !== $barcelona->id));

        $this->assertSame($before, $after);
    }

    public function test_invalid_rows_and_missing_countries_are_skipped_while_valid_rows_are_imported(): void
    {
        $this->csv([
            $this->row('AAA', '', 'ES', '40.0000', '-3.0000'),
            $this->row('BBB', 'Nowhere', '', '40.0000', '-3.0000'),
            $this->row('CCC', 'Badlat', 'ES', 'abc', '-3.0000'),
            $this->row('DDD', 'Northpole', 'ES', '95.0000', '-3.0000'),
            $this->row('EEE', 'Farwest', 'ES', '40.0000', '-200.0000'),
            $this->row('FFF', str_repeat('x', 151), 'ES', '40.0000', '-3.0000'),
            '7,GGG,,Short row,Shortcity,Spain',
            $this->row('HHH', 'Atlantis', 'ZZ', '10.0000', '10.0000'),
            $this->row('BCN', 'Barcelona', 'ES', '41.3900', '2.1700'),
        ]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('línea 2: nombre vacío')
            ->expectsOutputToContain('línea 3: país vacío')
            ->expectsOutputToContain('línea 4: coordenadas no válidas')
            ->expectsOutputToContain('línea 5: coordenadas no válidas')
            ->expectsOutputToContain('línea 6: coordenadas no válidas')
            ->expectsOutputToContain('línea 7: nombre demasiado largo')
            ->expectsOutputToContain('línea 8: número de columnas incorrecto')
            ->expectsOutputToContain('Países no encontrados: ZZ')
            ->assertSuccessful();

        $this->assertSame(['Barcelona'], City::pluck('name')->all());
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function structuralErrors(): array
    {
        return [
            'sin archivo' => [null, 'CSV not found'],
            'vacío' => ['', 'CSV is empty'],
            'sin columna obligatoria' => ["city_name,country_iso_code,city_latitude\nMadrid,ES,40.42", 'Missing required column: city_longitude'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('structuralErrors')]
    public function test_structural_errors_abort_before_any_write(?string $contents, string $message): void
    {
        $this->city('Madrid', 'ES', 40.4168, -3.7038);
        $before = $this->cities();

        if ($contents !== null) {
            file_put_contents($this->csvPath(), $contents);
        }

        $writes = $this->countWrites(fn () => $this->artisan('cities:import')
            ->expectsOutputToContain($message)
            ->assertFailed());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->cities());
    }

    public function test_same_name_city_without_coordinates_blocks_creation(): void
    {
        $madrid = $this->city('Madrid', 'ES', null, null, ['region_code' => 'MD']);
        $before = $this->cities();

        $this->csv([$this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("ciudad existente sin coordenadas (#{$madrid->id})")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_alias_city_without_coordinates_blocks_creation(): void
    {
        $kyiv = $this->city('Kyiv', 'UA', null, null);
        CityAlias::create(['city_id' => $kyiv->id, 'alias' => 'Kiev', 'normalized_alias' => 'kiev']);
        $before = $this->cities();

        $this->csv([$this->row('KBP', 'Kiev', 'UA', '50.4500', '30.5200')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("ciudad existente sin coordenadas (#{$kyiv->id})")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_indirect_location_evidence_never_authorizes_creation(): void
    {
        // Albany sin coordenadas con un aeropuerto en Georgia (~1500 km de la
        // fila del CSV). La asociación podría ser errónea (confusión de
        // homónimos): solo se muestra como diagnóstico.
        $albany = $this->city('Albany', 'US', null, null);
        Airport::create(['city_id' => $albany->id, 'name' => 'Southwest Georgia', 'iata_code' => 'ABY', 'latitude' => 31.5355, 'longitude' => -84.1945]);

        $springfield = $this->city('Springfield', 'US', null, null);
        Place::create(['city_id' => $springfield->id, 'name' => 'Somewhere', 'latitude' => 40.70, 'longitude' => -90.50]);
        $before = $this->cities();

        $this->csv([
            $this->row('ALB', 'Albany', 'US', '42.6500', '-73.7500'),
            $this->row('SPI', 'Springfield', 'US', '39.8000', '-89.6500'),
        ]);

        $this->artisan('cities:import')
            // Una sola expectativa por línea: la evidencia va en el mismo aviso.
            ->expectsOutputToContain("ciudad existente sin coordenadas (#{$albany->id}); no se puede comparar [solo diagnóstico: aeropuerto ABY de #{$albany->id} a")
            ->expectsOutputToContain("ciudad existente sin coordenadas (#{$springfield->id})")
            ->doesntExpectOutputToContain('+ Albany')
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_city_without_coordinates_merged_into_a_located_canonical_is_compared_through_the_canonical(): void
    {
        // Kiev (sin coordenadas) fusionada en Kyiv (con coordenadas).
        $kyiv = $this->city('Kyiv', 'UA', 50.4501, 30.5234);
        $kiev = $this->city('Kiev', 'UA', null, null, ['merged_into_id' => $kyiv->id]);

        // Albany sin coordenadas fusionada en Albany (Nueva York).
        $newYork = $this->city('Albany', 'US', 42.6526, -73.7562);
        $this->city('Albany', 'US', null, null, ['merged_into_id' => $newYork->id]);
        $before = $this->cities();

        $this->csv([
            // Cerca de la canónica: es la misma ciudad.
            $this->row('KBP', 'Kiev', 'UA', '50.4500', '30.5200'),
            $this->row('ALB', 'Albany', 'US', '42.6500', '-73.7500'),
            // A ~1500 km de la canónica: homónimo legítimo.
            $this->row('ABY', 'Albany', 'US', '31.5800', '-84.1600'),
        ]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("#{$kiev->id} está fusionada en #{$kyiv->id}")
            ->expectsOutputToContain('+ Albany (US)')
            ->doesntExpectOutputToContain('ciudad existente sin coordenadas (#')
            ->assertSuccessful();

        $created = City::query()->whereNotIn('id', array_column($before, 'id'))->get();
        $this->assertCount(1, $created);
        $this->assertSame('Albany', $created->first()->name);
        $this->assertEquals(31.58, (float) $created->first()->latitude);
        $this->assertSame($before, City::query()->whereIn('id', array_column($before, 'id'))->orderBy('id')->get()->toArray());
    }

    public function test_nearby_merged_city_with_a_distant_canonical_is_skipped(): void
    {
        $canonical = $this->city('Tangier', 'MA', 35.20, -5.80);
        $merged = $this->city('Tangier', 'MA', 35.7595, -5.8340, ['merged_into_id' => $canonical->id]);
        $before = $this->cities();

        // Junto a la fusionada (~1 km), pero a ~60 km de su canónica.
        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7673', '-5.7997')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("posiciones incoherentes: #{$merged->id}")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_names_that_normalize_to_empty_are_skipped(): void
    {
        $osaka = $this->city('大阪', 'JP', 34.6937, 135.5023);
        $before = $this->cities();

        $this->csv([
            // Otro nombre no latino, junto a Osaka: no puede compararse.
            $this->row('ITM', '東京', 'JP', '34.6900', '135.5000'),
            $this->row('CTS', 'Sapporo', 'JP', '43.0618', '141.3545'),
        ]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('línea 2: nombre no normalizable')
            ->expectsOutputToContain('+ Sapporo (JP)')
            ->assertSuccessful();

        $this->assertSame(0, City::where('name', '東京')->count());
        $this->assertSame($before[0], $osaka->fresh()->toArray());
        $this->assertSame(2, City::count());
    }

    public function test_merge_chain_longer_than_the_maximum_is_not_reported_as_a_cycle(): void
    {
        // Cadena lineal de 12 ciudades, sin ciclo: 1 → 2 → … → 12.
        $chain = [];
        for ($i = 0; $i < 12; $i++) {
            $chain[] = $this->city('Tangier', 'MA', 35.7673 + $i / 10000, -5.7997);
        }
        foreach (array_slice($chain, 0, -1) as $i => $city) {
            DB::table('cities')->where('id', $city->id)->update(['merged_into_id' => $chain[$i + 1]->id]);
        }
        $before = $this->cities();

        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7673', '-5.7997')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('cadena de fusiones demasiado larga')
            ->doesntExpectOutputToContain('ciclo de fusiones')
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_alias_of_a_city_in_another_country_is_ignored(): void
    {
        $kyiv = $this->city('Kyiv', 'UA', 50.4501, 30.5234);
        CityAlias::create(['city_id' => $kyiv->id, 'alias' => 'Kiev', 'normalized_alias' => 'kiev']);

        // Un "Kiev" en España (inventado) no es Kyiv.
        $this->csv([$this->row('XKV', 'Kiev', 'ES', '40.0000', '-4.0000')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('+ Kiev (ES)')
            ->assertSuccessful();

        $this->assertSame($this->countries['ES']->id, City::where('name', 'Kiev')->sole()->country_id);
        $this->assertSame(1, CityAlias::count());
    }

    public function test_summary_counts_rows_and_grouped_cities_separately(): void
    {
        $this->csv([
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('TOJ', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('XMD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            // Misma ciudad (≈3 km) con otras coordenadas: no es una ciudad existente.
            $this->row('YMD', 'Madrid', 'ES', '40.4500', '-3.6900'),
            $this->row('BCN', 'Barcelona', 'ES', '41.3900', '2.1700'),
            $this->row('BAD', 'Badlat', 'ES', 'abc', '-3.0000'),
            $this->row('AT1', 'Atlantis', 'ZZ', '10.0000', '10.0000'),
            $this->row('AT2', 'Atlantis', 'ZZ', '10.0000', '10.0000'),
        ]);

        $this->artisan('cities:import')
            ->expectsTable(['filas', 'número'], [
                ['leídas', 8],
                ['no válidas', 1],
                ['válidas', 7],
                ['de países no encontrados', 2],
            ])
            ->expectsTable(['ciudades (filas agrupadas)', 'número'], [
                ['total', 4],
                ['creadas', 2],
                ['ya existentes (sin cambios)', 0],
                ['misma ciudad que otra alta del CSV', 1],
                ['omitidas: coincidencia ambigua', 0],
                ['omitidas: fusión no resoluble', 0],
                ['omitidas: ciudad existente sin coordenadas', 0],
                ['omitidas: posible renombrado', 0],
                ['omitidas: país no encontrado', 1],
            ])
            ->assertSuccessful();

        $this->assertSame(2, City::count());
    }

    public function test_diagnostic_uses_the_nearest_candidate_when_several_resolve_to_the_same_city(): void
    {
        $canonical = $this->city('Tangier', 'MA', 35.7673, -5.7997);
        $this->city('Tangier', 'MA', 35.7595, -5.8340, ['merged_into_id' => $canonical->id]);

        // Coordenadas exactas de la canónica: ni fusión ni desviación que citar.
        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7673', '-5.7997')]);

        $this->artisan('cities:import')
            ->doesntExpectOutputToContain('está fusionada en')
            ->doesntExpectOutputToContain('coordenadas distintas')
            ->assertSuccessful();

        $this->assertSame(2, City::count());
    }

    public function test_near_canonical_with_another_candidate_without_coordinates_is_ambiguous(): void
    {
        $located = $this->city('Madrid', 'ES', 40.4168, -3.7038);
        $unlocated = $this->city('Madrid', 'ES', null, null);
        $before = $this->cities();

        $this->csv([$this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("coincidencia ambigua entre #{$located->id}, #{$unlocated->id} (sin coordenadas: #{$unlocated->id})")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_unresolvable_chain_is_reported_together_with_the_clear_match(): void
    {
        $tangier = $this->city('Tangier', 'MA', 35.7673, -5.7997);
        $elsewhere = $this->city('Tangier', 'ES', 40.0, -3.0);
        $broken = $this->city('Tangier', 'MA', 33.0, -7.0, ['merged_into_id' => $elsewhere->id]);
        $before = $this->cities();

        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7700', '-5.8000')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("fusión no resoluble (#{$broken->id} está fusionada en #{$elsewhere->id}, de otro país; coincidiría con #{$tangier->id})")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_far_valid_candidate_plus_unresolvable_chain_creates_nothing(): void
    {
        $this->city('Albany', 'US', 42.6526, -73.7562);
        $this->city('Albany', 'US', 31.0, -84.0, ['merged_into_id' => $this->city('Albany', 'ES', 40.0, -3.0)->id]);
        $before = $this->cities();

        $this->csv([$this->row('ABY', 'Albany', 'US', '31.5800', '-84.1600')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain('fusión no resoluble')
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_far_valid_candidate_plus_candidate_without_coordinates_creates_nothing(): void
    {
        $this->city('Albany', 'US', 42.6526, -73.7562);
        $unlocated = $this->city('Albany', 'US', null, null);
        $before = $this->cities();

        $this->csv([$this->row('ABY', 'Albany', 'US', '31.5800', '-84.1600')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("ciudad existente sin coordenadas (#{$unlocated->id})")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_near_canonical_plus_incoherent_merged_city_of_another_canonical_is_ambiguous(): void
    {
        $near = $this->city('Tangier', 'MA', 35.7673, -5.7997);
        $far = $this->city('Tangier', 'MA', 35.20, -5.80);
        // Cerca de la fila, pero fusionada en la canónica lejana.
        $this->city('Tangier', 'MA', 35.7595, -5.8340, ['merged_into_id' => $far->id]);
        $before = $this->cities();

        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7700', '-5.8000')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("coincidencia ambigua entre #{$near->id}, #{$far->id}")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_nearby_merged_city_whose_canonical_has_no_coordinates_is_undecidable(): void
    {
        $canonical = $this->city('Tangier', 'MA', null, null);
        $this->city('Tangier', 'MA', 35.7595, -5.8340, ['merged_into_id' => $canonical->id]);
        $before = $this->cities();

        $this->csv([$this->row('TNG', 'Tangier', 'MA', '35.7600', '-5.8300')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("ciudad existente sin coordenadas (#{$canonical->id})")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
    }

    public function test_alias_of_a_merged_city_resolves_to_its_canonical(): void
    {
        $kyiv = $this->city('Kyiv', 'UA', 50.4501, 30.5234);
        $old = $this->city('Kyiv (old)', 'UA', 50.4400, 30.5100, ['merged_into_id' => $kyiv->id]);
        CityAlias::create(['city_id' => $old->id, 'alias' => 'Kiev', 'normalized_alias' => 'kiev']);
        $before = $this->cities();

        $this->csv([$this->row('KBP', 'Kiev', 'UA', '50.4450', '30.5150')]);

        $this->artisan('cities:import')
            ->expectsOutputToContain("#{$old->id} está fusionada en #{$kyiv->id}")
            ->assertSuccessful();

        $this->assertSame($before, $this->cities());
        $this->assertSame(1, CityAlias::count());
    }

    public function test_dry_run_reports_the_real_plan_without_any_write(): void
    {
        $this->city('Madrid', 'ES', 40.4168, -3.7038);
        $this->city('Tangier', 'MA', 35.7673, -5.7997);
        $this->city('Tangier', 'MA', 35.7595, -5.8340);
        $before = $this->cities();

        $this->csv([
            $this->row('MAD', 'Madrid', 'ES', '40.4200', '-3.7000'),
            $this->row('BCN', 'Barcelona', 'ES', '41.3900', '2.1700'),
            $this->row('TNG', 'Tangier', 'MA', '35.7700', '-5.8100'),
        ]);

        $writes = $this->countWrites(fn () => $this->artisan('cities:import', ['--dry-run' => true])
            ->expectsOutputToContain('+ Barcelona (ES)')
            ->expectsOutputToContain('coordenadas distintas')
            ->expectsOutputToContain('coincidencia ambigua')
            ->expectsOutputToContain('Simulación (--dry-run): no se ha modificado nada.')
            ->assertSuccessful());

        $this->assertSame(0, $writes);
        $this->assertSame($before, $this->cities());
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

    private function row(string $iata, string $city, string $iso, string $latitude, string $longitude): string
    {
        return implode(',', ['1', $iata, '', "{$iata} Airport", $city, 'Country', $iso, $latitude, $longitude]);
    }

    private function csv(array $lines): void
    {
        file_put_contents($this->csvPath(), implode("\n", [self::HEADER, ...$lines]) . "\n");
    }

    private function csvPath(): string
    {
        return storage_path('app/data/vyamap_airport_city_mapping.csv');
    }

    private function cities(): array
    {
        return City::query()->orderBy('id')->get()->toArray();
    }

    /**
     * Ciudades y todas las claves que apuntan a ellas.
     */
    private function relations(): array
    {
        $rows = fn (string $table) => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        return [
            'cities' => $this->cities(),
            'visits' => $rows('visits'),
            'airports' => $rows('airports'),
            'city_aliases' => $rows('city_aliases'),
            'places' => $rows('places'),
        ];
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
