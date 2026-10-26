<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Instalación desde cero del catálogo con los CSV versionados en
 * database/data/catalog (los mismos pasos que docs/deployment.md): se copian a
 * un storage temporal y se ejecutan los seis comandos de importación en orden.
 * No lee ni escribe storage/app/data del proyecto.
 */
class CatalogDataImportTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = sys_get_temp_dir() . '/vyamap-catalog-' . bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->storage . '/app/data');
        File::copyDirectory(base_path('database/data/catalog'), $this->storage . '/app/data');
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_the_versioned_catalog_files_build_the_full_catalog(): void
    {
        $this->assertSame($this->storage . '/app/data/countries.csv', storage_path('app/data/countries.csv'));

        foreach ([
            ['countries:import', ['--apply' => true]],
            ['cities:import', []],
            ['app:import-airports', []],
            ['airports:map-cities', ['--apply' => true]],
            ['airports:enrich-coordinates', ['--apply' => true]],
            ['airports:import-timezones', ['--apply' => true]],
        ] as [$command, $options]) {
            $this->assertSame(0, $this->artisan($command, $options)->run(), "{$command} ha fallado");
        }

        $this->assertSame(251, Country::count());
        $this->assertGreaterThan(3000, City::count());
        $this->assertSame(3244, Airport::count());
        $this->assertSame(3244, Airport::whereNotNull('city_id')->count());
        $this->assertSame(3244, Airport::whereNotNull('latitude')->whereNotNull('longitude')->count());
        $this->assertSame(3244, Airport::whereNotNull('timezone')->count());

        $madrid = Airport::where('iata_code', 'MAD')->with('city.country')->sole();
        $this->assertSame(['ES', 'Europe/Madrid'], [$madrid->city->country->iso_code, $madrid->timezone]);
    }
}
