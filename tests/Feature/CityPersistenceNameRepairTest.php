<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Services\CityPersistenceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CityPersistenceService con una ciudad que ya existe por su identificador de
 * Geoapify: si su nombre guardado no es válido (p. ej. "yes", guardado antes
 * de validar los nombres) recibe el nombre verificado, siempre que este sea
 * válido; un nombre válido nunca se sobrescribe. Es el mismo registro.
 *
 * Aislada de MySQL: no usa RefreshDatabase ni toca vyamap_testing. Cada test
 * crea en una base SQLite en memoria solo las tablas que usa el servicio
 * (sin migraciones) y la conexión por defecto apunta a ella.
 */
class CityPersistenceNameRepairTest extends TestCase
{
    private Country $vatican;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.isolated_sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'database.default' => 'isolated_sqlite',
        ]);
        DB::purge('isolated_sqlite');

        $this->assertSame('sqlite', DB::connection()->getDriverName());

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('iso_code', 2)->unique();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries');
            $table->string('name');
            $table->string('region_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('external_provider')->nullable();
            $table->string('external_id')->nullable();
            $table->foreignId('merged_into_id')->nullable();
            $table->timestamps();
        });

        Schema::create('city_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities');
            $table->string('alias');
            $table->string('normalized_alias')->unique();
            $table->string('language')->nullable();
            $table->timestamps();
        });

        // Solo para comprobar que las relaciones siguen apuntando a la misma ciudad.
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities');
        });

        $this->vatican = Country::create(['name' => 'Vatican City', 'iso_code' => 'VA']);
    }

    public function test_an_invalid_stored_name_is_replaced_by_the_verified_name_on_the_same_city(): void
    {
        $city = $this->city('yes');
        $city->aliases()->create(['alias' => 'Vaticano', 'normalized_alias' => 'vaticano']);
        $visitId = DB::table('visits')->insertGetId(['city_id' => $city->id]);

        $result = $this->persist('Vatican City');

        $this->assertSame($city->id, $result->id);
        $this->assertSame('Vatican City', $result->fresh()->name);
        $this->assertSame(1, City::count());

        // El resto de datos y las relaciones no cambian.
        $stored = $result->fresh();
        $this->assertSame([$this->vatican->id, 'geoapify', 'vatican-place', '41.9034000', '12.4529000'], [$stored->country_id, $stored->external_provider, $stored->external_id, (string) $stored->latitude, (string) $stored->longitude]);
        $this->assertSame([$city->id], $stored->aliases()->pluck('city_id')->unique()->all());
        $this->assertSame($city->id, DB::table('visits')->where('id', $visitId)->value('city_id'));
    }

    public function test_a_valid_stored_name_is_never_overwritten(): void
    {
        $city = $this->city('Città del Vaticano');

        $result = $this->persist('Vatican City');

        $this->assertSame($city->id, $result->id);
        $this->assertSame('Città del Vaticano', $result->fresh()->name);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidVerifiedNames(): array
    {
        return [
            'etiqueta de OSM' => ['no'],
            'vacío' => ['  '],
            'sin letras' => ['123'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidVerifiedNames')]
    public function test_an_invalid_verified_name_never_replaces_the_stored_one(mixed $verifiedName): void
    {
        $city = $this->city('yes');

        $result = $this->persist($verifiedName);

        $this->assertSame($city->id, $result->id);
        $this->assertSame('yes', $result->fresh()->name);
    }

    public function test_a_merged_city_still_resolves_to_its_canonical_city_unchanged(): void
    {
        $canonical = $this->city('Vatican City', externalId: null);
        $duplicate = $this->city('yes');
        $duplicate->forceFill(['merged_into_id' => $canonical->id])->save();

        $result = $this->persist('Vatican City');

        $this->assertSame($canonical->id, $result->id);
        $this->assertSame('yes', $duplicate->fresh()->name);
    }

    private function city(string $name, ?string $externalId = 'vatican-place'): City
    {
        return City::create([
            'country_id' => $this->vatican->id,
            'name' => $name,
            'latitude' => 41.9034,
            'longitude' => 12.4529,
            'external_provider' => $externalId ? 'geoapify' : null,
            'external_id' => $externalId,
        ]);
    }

    private function persist(mixed $name): City
    {
        return app(CityPersistenceService::class)->persist([
            'name' => $name,
            'country' => 'Vatican City',
            'iso_code' => 'VA',
            'region_code' => null,
            'latitude' => 41.9034,
            'longitude' => 12.4529,
            'external_provider' => 'geoapify',
            'external_id' => 'vatican-place',
            'search_query' => 'Vaticano',
        ]);
    }
}
