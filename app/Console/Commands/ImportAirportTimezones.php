<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use DateTimeZone;

#[Signature('airports:import-timezones')]
#[Description('Import IANA timezones for VyaMap airports from CSV')]
class ImportAirportTimezones extends Command
{
    public function handle(): int
    {
        $path = storage_path('app/data/airport_timezones.csv');

        if (! is_file($path)) {
            $this->error("Timezone dataset not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error('Unable to open timezone dataset.');

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

        foreach (['iata_code', 'icao_code', 'timezone'] as $column) {
            if (! array_key_exists($column, $indexes)) {
                fclose($handle);
                $this->error("Missing required CSV column: {$column}");

                return self::FAILURE;
            }
        }

        $validTimezones = DateTimeZone::listIdentifiers();
        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $missing = 0;

        DB::transaction(function () use (
            $handle,
            $indexes,
            $validTimezones,
            &$processed,
            &$updated,
            &$skipped,
            &$missing
        ): void {
            while (($row = fgetcsv($handle)) !== false) {
                $iata = trim($row[$indexes['iata_code']] ?? '');
                $icao = trim($row[$indexes['icao_code']] ?? '');
                $timezone = trim($row[$indexes['timezone']] ?? '');

                if ($timezone === '' || ! in_array($timezone, $validTimezones, true)) {
                    $skipped++;

                    continue;
                }

                if ($iata === '' && $icao === '') {
                    $skipped++;

                    continue;
                }

                $query = Airport::query();

                if ($icao !== '') {
                    $query->where('icao_code', $icao);
                } else {
                    $query->where('iata_code', $iata);
                }

                $airport = $query->first();

                if (! $airport) {
                    $missing++;

                    continue;
                }

                $processed++;

                if ($airport->timezone !== $timezone) {
                    $airport->timezone = $timezone;
                    $airport->save();
                    $updated++;
                }
            }
        });

        fclose($handle);

        $this->newLine();
        $this->info("Processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Airports not found: {$missing}");
        $this->info(
            'Airports with timezone: ' .
            Airport::query()->whereNotNull('timezone')
                ->where('timezone', '!=', '')
                ->count()
        );

        return $missing > 0 || $skipped > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
