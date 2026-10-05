<?php

namespace App\Console\Commands;

use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCountries extends Command
{
    protected $signature = 'countries:import';

    protected $description = 'Import the VyaMap country catalog from CSV and overrides';

    public function handle(): int
    {
        $countriesPath = storage_path('app/data/countries.csv');
        $overridesPath = storage_path('app/data/countries_overrides.csv');

        if (!file_exists($countriesPath)) {
            $this->error("CSV not found: {$countriesPath}");

            return self::FAILURE;
        }

        if (!file_exists($overridesPath)) {
            $this->error("Overrides CSV not found: {$overridesPath}");

            return self::FAILURE;
        }

        $created = 0;
        $existing = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $countriesPath,
            $overridesPath,
            &$created,
            &$existing,
            &$skipped
        ) {
            $this->importFile(
                $countriesPath,
                ['code', 'name'],
                $created,
                $existing,
                $skipped
            );

            $this->importFile(
                $overridesPath,
                ['code', 'name'],
                $created,
                $existing,
                $skipped
            );
        });

        $this->newLine();
        $this->info('Countries import completed.');
        $this->line("Created: {$created}");
        $this->line("Existing: {$existing}");
        $this->line("Skipped: {$skipped}");
        $this->line('Countries in database: ' . Country::count());

        return self::SUCCESS;
    }

    private function importFile(
        string $path,
        array $requiredHeaders,
        int &$created,
        int &$existing,
        int &$skipped
    ): void {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV: {$path}");
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            throw new \RuntimeException("CSV is empty: {$path}");
        }

        $headers = array_map(
            fn ($header) => trim($header),
            $headers
        );

        foreach ($requiredHeaders as $header) {
            if (!in_array($header, $headers, true)) {
                fclose($handle);

                throw new \RuntimeException(
                    "Missing required column '{$header}' in {$path}"
                );
            }
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                $skipped++;

                continue;
            }

            $row = array_combine($headers, $row);

            $name = trim($row['name'] ?? '');
            $isoCode = strtoupper(trim($row['code'] ?? ''));

            if ($name === '' || $isoCode === '') {
                $skipped++;

                continue;
            }

            $country = Country::firstOrCreate(
                ['iso_code' => $isoCode],
                ['name' => $name]
            );

            if ($country->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        fclose($handle);
    }
}