<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCities extends Command
{
    protected $signature = 'cities:import';

    protected $description = 'Import the VyaMap city catalog from airport-city mapping CSV';

    public function handle(): int
    {
        $path = storage_path('app/data/vyamap_airport_city_mapping.csv');

        if (!file_exists($path)) {
            $this->error("CSV not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error('Unable to open CSV.');

            return self::FAILURE;
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            $this->error('CSV is empty.');

            return self::FAILURE;
        }

        $headers = array_map(
            fn ($header) => trim($header),
            $headers
        );

        $requiredHeaders = [
            'city_name',
            'country_iso_code',
            'city_latitude',
            'city_longitude',
        ];

        foreach ($requiredHeaders as $header) {
            if (!in_array($header, $headers, true)) {
                fclose($handle);

                $this->error("Missing required column: {$header}");

                return self::FAILURE;
            }
        }

        $created = 0;
        $existing = 0;
        $skipped = 0;
        $missingCountries = [];

        DB::transaction(function () use (
            $handle,
            $headers,
            &$created,
            &$existing,
            &$skipped,
            &$missingCountries
        ) {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    $skipped++;

                    continue;
                }

                $row = array_combine($headers, $row);

                $cityName = trim($row['city_name'] ?? '');
                $countryIso = strtoupper(trim($row['country_iso_code'] ?? ''));
                $latitude = trim($row['city_latitude'] ?? '');
                $longitude = trim($row['city_longitude'] ?? '');

                if (
                    $cityName === '' ||
                    $countryIso === '' ||
                    $latitude === '' ||
                    $longitude === ''
                ) {
                    $skipped++;

                    continue;
                }

                $country = Country::where('iso_code', $countryIso)->first();

                if (!$country) {
                    $missingCountries[$countryIso] = true;
                    $skipped++;

                    continue;
                }

                $city = City::firstOrCreate(
                    [
                        'country_id' => $country->id,
                        'name' => $cityName,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ],
                    [
                        'region_code' => null,
                        'external_provider' => null,
                        'external_id' => null,
                    ]
                );

                if ($city->wasRecentlyCreated) {
                    $created++;
                } else {
                    $existing++;
                }
            }
        });

        fclose($handle);

        $this->newLine();
        $this->info('Cities import completed.');
        $this->line("Created: {$created}");
        $this->line("Existing: {$existing}");
        $this->line("Skipped: {$skipped}");

        if (!empty($missingCountries)) {
            $this->newLine();
            $this->warn('Countries not found:');

            foreach (array_keys($missingCountries) as $isoCode) {
                $this->line("- {$isoCode}");
            }
        }

        $this->line('Cities in database: ' . City::count());

        return empty($missingCountries)
            ? self::SUCCESS
            : self::FAILURE;
    }
}