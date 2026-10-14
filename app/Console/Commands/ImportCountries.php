<?php

namespace App\Console\Commands;

use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Añade al catálogo los países de countries.csv y countries_overrides.csv que
 * aún no existan. Nunca renombra ni borra países existentes.
 *
 * Sin --apply solo simula. Con --apply escribe el plan en una transacción.
 *
 * - Ambos archivos se validan por completo antes de escribir: archivo
 *   inexistente, vacío o sin columnas obligatorias es un error estructural.
 * - Fila mal formada, ISO que no sean dos letras, nombre vacío o demasiado
 *   largo: la fila se omite y se informa.
 * - Un mismo ISO con nombres distintos (en un archivo o entre los dos): plan
 *   ambiguo, FAILURE sin escribir. Con el mismo nombre solo se deduplica.
 * - País ya existente con otro nombre: se conserva el nombre actual.
 */
class ImportCountries extends Command
{
    protected $signature = 'countries:import {--apply : Añade los países previstos (sin esta opción solo se simula)}';

    protected $description = 'Import the VyaMap country catalog from CSV and overrides';

    private const FILES = ['countries.csv', 'countries_overrides.csv'];

    private const REQUIRED_COLUMNS = ['code', 'name'];

    private const NAME_MAX_LENGTH = 100;

    private const DETAILS_SHOWN = 20;

    public function handle(): int
    {
        $entries = [];
        $skipped = [];
        $rowsRead = 0;

        foreach (self::FILES as $file) {
            $rows = $this->readCsv(storage_path("app/data/{$file}"), $file);

            if ($rows === null) {
                return self::FAILURE;
            }

            $rowsRead += count($rows);

            foreach ($rows as $line => $row) {
                $reason = $this->validate($row);

                if ($reason !== null) {
                    $skipped[] = ['file' => $file, 'line' => $line, 'reason' => $reason];

                    continue;
                }

                $entries[] = ['file' => $file, 'line' => $line, 'iso' => strtoupper(trim($row['code'])), 'name' => trim($row['name'])];
            }
        }

        $conflicts = $this->conflicts($entries);

        if ($conflicts !== []) {
            foreach ($conflicts as $conflict) {
                $this->error($conflict);
            }

            $this->error('Ambiguous plan: no database records were modified.');

            return self::FAILURE;
        }

        $plan = $this->plan($entries);
        $plan['rows'] = $rowsRead;
        $plan['skipped'] = $skipped;

        $this->report($plan);

        if (! $this->option('apply')) {
            $this->info('DRY RUN: no database records were modified.');

            return self::SUCCESS;
        }

        $created = $plan['create'] === [] ? 0 : DB::transaction(function () use ($plan) {
            $created = 0;

            foreach ($plan['create'] as $entry) {
                // Nunca renombra: solo crea si el ISO sigue sin existir.
                $country = Country::query()->firstOrCreate(['iso_code' => $entry['iso']], ['name' => $entry['name']]);
                $created += (int) $country->wasRecentlyCreated;
            }

            return $created;
        });

        $this->info("CREATED: {$created}");
        $this->line('Countries in database: ' . Country::count());

        return self::SUCCESS;
    }

    /**
     * Lectura y validación estructural. Null si el archivo no es utilizable.
     *
     * @return array<int, array<string, string>|null>|null Filas por línea (null si está mal formada).
     */
    private function readCsv(string $path, string $file): ?array
    {
        if (! is_file($path) || ($handle = @fopen($path, 'r')) === false) {
            $this->error("CSV not found or unreadable: {$file}");

            return null;
        }

        try {
            $headers = fgetcsv($handle, escape: '\\');

            if ($headers === false || $headers === [null]) {
                $this->error("CSV is empty: {$file}");

                return null;
            }

            $headers = array_map(fn ($header) => trim((string) $header, " \t\n\r\0\x0B\u{FEFF}"), $headers);

            foreach (self::REQUIRED_COLUMNS as $column) {
                if (! in_array($column, $headers, true)) {
                    $this->error("Missing required column '{$column}' in {$file}");

                    return null;
                }
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, escape: '\\')) !== false) {
                $line++;

                if ($values !== [null]) {
                    $rows[$line] = count($values) === count($headers) ? array_combine($headers, $values) : null;
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function validate(?array $row): ?string
    {
        if ($row === null) {
            return 'número de columnas incorrecto';
        }

        $iso = strtoupper(trim($row['code']));
        $name = trim($row['name']);

        return match (true) {
            ! preg_match('/^[A-Z]{2}$/', $iso) => "código ISO no válido «{$iso}»",
            $name === '' => 'nombre vacío',
            mb_strlen($name) > self::NAME_MAX_LENGTH => 'nombre demasiado largo',
            default => null,
        };
    }

    /**
     * Mismo ISO con nombres distintos, dentro de un archivo o entre ambos.
     *
     * @return list<string>
     */
    private function conflicts(array $entries): array
    {
        $conflicts = [];

        foreach (collect($entries)->groupBy('iso') as $iso => $group) {
            if ($group->pluck('name')->unique()->count() > 1) {
                $conflicts[] = "Conflicting names for {$iso}: " . $group->map(fn ($entry) => "«{$entry['name']}» ({$entry['file']}:{$entry['line']})")->implode(', ');
            }
        }

        return $conflicts;
    }

    private function plan(array $entries): array
    {
        $plan = ['create' => [], 'existing' => 0, 'different_name' => [], 'duplicates' => 0];
        $existing = Country::query()->get(['iso_code', 'name'])->keyBy(fn ($country) => strtoupper($country->iso_code));
        $seen = [];

        foreach ($entries as $entry) {
            if (isset($seen[$entry['iso']])) {
                $plan['duplicates']++;

                continue;
            }

            $seen[$entry['iso']] = true;
            $country = $existing->get($entry['iso']);

            if ($country === null) {
                $plan['create'][] = $entry;

                continue;
            }

            $plan['existing']++;

            if ($country->name !== $entry['name']) {
                $plan['different_name'][] = $entry + ['current' => $country->name];
            }
        }

        return $plan;
    }

    private function report(array $plan): void
    {
        $apply = (bool) $this->option('apply');

        $this->table(['resultado', 'número'], [
            ['filas leídas (ambos archivos)', $plan['rows']],
            [$apply ? 'países añadidos' : 'países por añadir', count($plan['create'])],
            ['países ya existentes (sin cambios)', $plan['existing']],
            ['  de ellos con otro nombre en el CSV (se conserva el actual)', count($plan['different_name'])],
            ['filas repetidas (mismo ISO y nombre)', $plan['duplicates']],
            ['filas omitidas', count($plan['skipped'])],
        ]);

        foreach (array_slice($plan['create'], 0, self::DETAILS_SHOWN) as $entry) {
            $this->line("  + {$entry['iso']} {$entry['name']} ({$entry['file']})");
        }

        foreach (array_slice($plan['different_name'], 0, self::DETAILS_SHOWN) as $entry) {
            $this->line("  = {$entry['iso']}: se conserva «{$entry['current']}» (el CSV dice «{$entry['name']}»)");
        }

        foreach (array_slice($plan['skipped'], 0, self::DETAILS_SHOWN) as $entry) {
            $this->warn("  ? {$entry['file']} línea {$entry['line']}: {$entry['reason']}");
        }
    }
}
