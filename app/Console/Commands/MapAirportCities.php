<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MapAirportCities extends Command
{
    protected $signature = 'airports:map-cities {--apply : Apply the mappings to the database}';

    protected $description = 'Map VyaMap airports to existing cities using the airport-city mapping CSV';

    public function handle(): int
    {
        $path = storage_path('app/data/vyamap_airport_city_mapping.csv');

        if (!File::exists($path)) {
            $this->error("Mapping file not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error('Unable to open mapping file.');

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
            'id_original',
            'iata_code',
            'icao_code',
            'airport_name',
            'city_name',
            'country_name',
            'country_iso_code',
            'city_latitude',
            'city_longitude',
        ];

        foreach ($requiredHeaders as $requiredHeader) {
            if (!in_array($requiredHeader, $headers, true)) {
                fclose($handle);

                $this->error(
                    "Missing required CSV column: {$requiredHeader}"
                );

                return self::FAILURE;
            }
        }

        $match = 0;
        $review = 0;
        $noMatch = 0;
        $airportNotFound = 0;
        $countryNotFound = 0;
        $cityNotFound = 0;
        $alreadyMapped = 0;
        $applied = 0;

        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) !== count($headers)) {
                $this->warn(
                    "Row {$rowNumber}: invalid column count."
                );

                $review++;

                continue;
            }

            $data = array_combine($headers, $row);

            $iataCode = trim($data['iata_code']);
            $cityName = trim($data['city_name']);
            $countryIso = strtoupper(trim($data['country_iso_code']));

            $cityLatitude = (float) $data['city_latitude'];
            $cityLongitude = (float) $data['city_longitude'];

            $airport = Airport::where('iata_code', $iataCode)->first();

            if (!$airport) {
                $airportNotFound++;

                $this->warn(
                    "{$iataCode} | AIRPORT_NOT_FOUND"
                );

                continue;
            }

            $country = Country::where(
                'iso_code',
                $countryIso
            )->first();

            if (!$country) {
                $countryNotFound++;

                $this->warn(
                    "{$iataCode} | {$cityName} | " .
                    "{$countryIso} | COUNTRY_NOT_FOUND"
                );

                continue;
            }

            $cities = City::query()
                ->where('country_id', $country->id)
                ->where('name', $cityName)
                ->get();

            if ($cities->isEmpty()) {
                $cityNotFound++;

                $this->warn(
                    "{$iataCode} | {$cityName} | " .
                    "{$countryIso} | CITY_NOT_FOUND"
                );

                continue;
            }

            $candidates = $cities
                ->map(function (City $city) use (
                    $cityLatitude,
                    $cityLongitude
                ) {
                    return [
                        'city' => $city,
                        'distance' => $this->distanceKm(
                            $cityLatitude,
                            $cityLongitude,
                            (float) $city->latitude,
                            (float) $city->longitude
                        ),
                    ];
                })
                ->sortBy('distance')
                ->values();

            $best = $candidates->first();

            if (!$best || $best['distance'] > 5) {
                $review++;

                $this->warn(
                    "{$iataCode} | {$cityName} | {$countryIso} | " .
                    "REVIEW | " .
                    ($best
                        ? number_format($best['distance'], 2) . ' km'
                        : 'no candidate')
                );

                continue;
            }

            /** @var City $city */
            $city = $best['city'];

            $match++;

            $region = $city->region_code
                ? " | {$city->region_code}"
                : '';

            $this->line(
                "{$iataCode} | {$airport->name}" .
                PHP_EOL .
                "    → {$city->name}, {$countryIso}{$region}" .
                " | " .
                number_format($best['distance'], 2) . " km"
            );

            if ($airport->city_id === $city->id) {
                $alreadyMapped++;

                continue;
            }

            if ($this->option('apply')) {
                $airport->update([
                    'city_id' => $city->id,
                ]);

                $applied++;
            }
        }

        fclose($handle);

        $this->newLine();
        $this->line('================================');
        $this->line(
            $this->option('apply')
                ? 'MAPPING SUMMARY — APPLIED'
                : 'MAPPING SUMMARY — DRY RUN'
        );
        $this->line('================================');

        $this->line("MATCH: {$match}");
        $this->line("REVIEW: {$review}");
        $this->line("NO_MATCH: {$noMatch}");
        $this->line("AIRPORT_NOT_FOUND: {$airportNotFound}");
        $this->line("COUNTRY_NOT_FOUND: {$countryNotFound}");
        $this->line("CITY_NOT_FOUND: {$cityNotFound}");
        $this->line("ALREADY_MAPPED: {$alreadyMapped}");

        if ($this->option('apply')) {
            $this->line("UPDATED: {$applied}");
        } else {
            $this->newLine();
            $this->info(
                'DRY RUN: no database records were modified.'
            );
            $this->info(
                'Use --apply only after reviewing the result.'
            );
        }

        return self::SUCCESS;
    }

    private function distanceKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371;

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a =
            sin($deltaLat / 2) ** 2 +
            cos($lat1) *
            cos($lat2) *
            sin($deltaLon / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }
}