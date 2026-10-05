<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-airports')]
#[Description('Import VyaMap airports from the airport-city mapping CSV')]
class ImportAirports extends Command
{
    public function handle(): int
    {
        $path = storage_path(
            'app/data/vyamap_airport_city_mapping.csv'
        );

        if (! is_file($path)) {
            $this->error(
                "Airport dataset not found: {$path}"
            );

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error(
                'Unable to open airport dataset.'
            );

            return self::FAILURE;
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            $this->error(
                'Unable to read CSV headers.'
            );

            return self::FAILURE;
        }

        $headers = array_map(
            fn ($header) => trim($header),
            $headers
        );

        $indexes = array_flip($headers);

        $requiredColumns = [
            'id_original',
            'iata_code',
            'icao_code',
            'airport_name',
        ];

        foreach ($requiredColumns as $column) {
            if (! array_key_exists($column, $indexes)) {
                fclose($handle);

                $this->error(
                    "Missing required CSV column: {$column}"
                );

                return self::FAILURE;
            }
        }

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $iataCode = trim(
                $row[$indexes['iata_code']] ?? ''
            );

            if ($iataCode === '') {
                $skipped++;

                continue;
            }

            $processed++;

            $name = trim(
                $row[$indexes['airport_name']] ?? ''
            );

            $icaoCode = trim(
                $row[$indexes['icao_code']] ?? ''
            );

            $airport = Airport::query()->updateOrCreate(
                [
                    'iata_code' => $iataCode,
                ],
                [
                    'city_id' => null,
                    'name' => $name,
                    'icao_code' => $icaoCode !== ''
                        ? $icaoCode
                        : null,
                    'latitude' => null,
                    'longitude' => null,
                ],
            );

            if ($airport->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        $this->newLine();

        $this->info(
            "Processed: {$processed}"
        );

        $this->info(
            "Created: {$created}"
        );

        $this->info(
            "Updated: {$updated}"
        );

        $this->info(
            "Skipped: {$skipped}"
        );

        $this->info(
            'Airports in database: ' .
            Airport::count()
        );

        return self::SUCCESS;
    }
}