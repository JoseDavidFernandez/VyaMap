<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('airports:enrich-coordinates')]
#[Description('Enrich existing VyaMap airports with coordinates from OurAirports')]
class EnrichAirportCoordinates extends Command
{
    public function handle(): int
    {
        $path = storage_path('app/data/airports.csv');

        if (! is_file($path)) {
            $this->error("Airport dataset not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error('Unable to open airport dataset.');

            return self::FAILURE;
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            $this->error('Unable to read CSV headers.');

            return self::FAILURE;
        }

        $headers = array_map(
            fn ($header) => trim($header),
            $headers
        );

        $indexes = array_flip($headers);

        foreach ([
            'iata_code',
            'latitude_deg',
            'longitude_deg',
        ] as $column) {
            if (! array_key_exists($column, $indexes)) {
                fclose($handle);

                $this->error(
                    "Missing required CSV column: {$column}"
                );

                return self::FAILURE;
            }
        }

        $found = 0;
        $updated = 0;
        $alreadySet = 0;
        $withoutCoordinates = 0;
        $notFound = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $iataCode = trim(
                $row[$indexes['iata_code']] ?? ''
            );

            if ($iataCode === '') {
                continue;
            }

            $latitude = trim(
                $row[$indexes['latitude_deg']] ?? ''
            );

            $longitude = trim(
                $row[$indexes['longitude_deg']] ?? ''
            );

            if ($latitude === '' || $longitude === '') {
                $withoutCoordinates++;

                continue;
            }

            $airport = Airport::query()
                ->where('iata_code', $iataCode)
                ->first();

            if (! $airport) {
                $notFound++;

                continue;
            }

            $found++;

            if (
                $airport->latitude !== null &&
                $airport->longitude !== null
            ) {
                $alreadySet++;

                continue;
            }

            $airport->update([
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            $updated++;
        }

        fclose($handle);

        $this->newLine();
        $this->line('================================');
        $this->line('AIRPORT COORDINATES SUMMARY');
        $this->line('================================');

        $this->line("Found in VyaMap: {$found}");
        $this->line("Updated: {$updated}");
        $this->line("Already set: {$alreadySet}");
        $this->line("Without coordinates: {$withoutCoordinates}");
        $this->line("Not found in VyaMap: {$notFound}");

        return self::SUCCESS;
    }
}