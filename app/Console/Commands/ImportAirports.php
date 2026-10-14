<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa el catálogo de aeropuertos desde vyamap_airport_city_mapping.csv.
 *
 * El CSV solo es la fuente de iata_code (clave), icao_code y name. Los demás
 * campos se enriquecen después con otros comandos y este nunca los escribe:
 *
 *   city_id               airports:map-cities
 *   latitude, longitude   airports:enrich-coordinates (city_latitude/longitude
 *                         del CSV son de la ciudad, no del aeropuerto)
 *   timezone              airports:import-timezones
 *
 * Reglas:
 * - Aeropuerto nuevo: se crea con iata_code, name e icao_code.
 * - Existente: name e icao_code se actualizan si el CSV trae un valor válido
 *   y distinto; un valor vacío nunca borra el existente.
 * - Filas no válidas, IATA repetidos en el CSV o un ICAO que ya pertenece a
 *   otro aeropuerto: la fila se omite y se informa.
 * - Idempotente: repetirlo con el mismo CSV no cambia nada.
 * - --dry-run muestra las operaciones previstas sin escribir.
 */
#[Signature('app:import-airports {--dry-run : Muestra las operaciones previstas sin modificar la base de datos}')]
#[Description('Import VyaMap airports from the airport-city mapping CSV')]
class ImportAirports extends Command
{
    private const REQUIRED_COLUMNS = ['iata_code', 'icao_code', 'airport_name'];

    private const NAME_MAX_LENGTH = 200;

    private const DETAILS_SHOWN = 20;

    public function handle(): int
    {
        $path = storage_path('app/data/vyamap_airport_city_mapping.csv');
        $dryRun = (bool) $this->option('dry-run');

        $rows = $this->readRows($path);

        if ($rows === null) {
            return self::FAILURE;
        }

        $existing = Airport::query()->get(['id', 'iata_code', 'icao_code', 'name'])->keyBy('iata_code');
        $icaoOwners = $existing->filter(fn ($airport) => $airport->icao_code !== null)->mapWithKeys(fn ($airport) => [$airport->icao_code => $airport->iata_code]);

        $plan = ['create' => [], 'update' => [], 'unchanged' => 0, 'skipped' => []];
        $seen = [];

        foreach ($rows as $line => $row) {
            $iata = strtoupper(trim($row['iata_code'] ?? ''));
            $icao = strtoupper(trim($row['icao_code'] ?? ''));
            $name = trim($row['airport_name'] ?? '');

            $reason = match (true) {
                ! preg_match('/^[A-Z]{3}$/', $iata) => 'código IATA no válido',
                isset($seen[$iata]) => "IATA repetido en el CSV (primera aparición en la línea {$seen[$iata]})",
                $icao !== '' && ! preg_match('/^[A-Z0-9]{4}$/', $icao) => 'código ICAO no válido',
                mb_strlen($name) > self::NAME_MAX_LENGTH => 'nombre demasiado largo',
                $name === '' && ! $existing->has($iata) => 'aeropuerto nuevo sin nombre',
                $icao !== '' && isset($icaoOwners[$icao]) && $icaoOwners[$icao] !== $iata => "el ICAO {$icao} ya pertenece a {$icaoOwners[$icao]}",
                default => null,
            };

            if ($reason !== null) {
                $plan['skipped'][] = ['line' => $line, 'iata' => $iata, 'reason' => $reason];

                continue;
            }

            $seen[$iata] = $line;

            if ($icao !== '') {
                $icaoOwners[$icao] = $iata;
            }

            $airport = $existing->get($iata);

            if ($airport === null) {
                $plan['create'][] = ['iata_code' => $iata, 'name' => $name, 'icao_code' => $icao !== '' ? $icao : null];

                continue;
            }

            // Solo valores válidos y distintos; nunca se vacía un campo.
            $changes = array_filter([
                'name' => $name !== '' && $name !== $airport->name ? $name : null,
                'icao_code' => $icao !== '' && $icao !== $airport->icao_code ? $icao : null,
            ], fn ($value) => $value !== null);

            if ($changes === []) {
                $plan['unchanged']++;
            } else {
                $plan['update'][] = ['airport' => $airport, 'changes' => $changes];
            }
        }

        $this->report($plan, $dryRun);

        if ($dryRun) {
            $this->warn('Simulación (--dry-run): no se ha modificado nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan['create'] as $attributes) {
                Airport::query()->create($attributes);
            }

            foreach ($plan['update'] as ['airport' => $airport, 'changes' => $changes]) {
                $airport->update($changes);
            }
        });

        $this->info('Airports in database: ' . Airport::count());

        return self::SUCCESS;
    }

    /**
     * Filas del CSV indexadas por número de línea, o null si la entrada no es válida.
     *
     * @return array<int, array<string, string>>|null
     */
    private function readRows(string $path): ?array
    {
        if (! is_file($path) || ($handle = @fopen($path, 'r')) === false) {
            $this->error("Airport dataset not found or unreadable: {$path}");

            return null;
        }

        try {
            $headers = fgetcsv($handle, escape: '\\');

            if ($headers === false || $headers === [null]) {
                $this->error('Unable to read CSV headers.');

                return null;
            }

            $headers = array_map(fn ($header) => trim((string) $header, " \t\n\r\0\x0B\u{FEFF}"), $headers);

            foreach (self::REQUIRED_COLUMNS as $column) {
                if (! in_array($column, $headers, true)) {
                    $this->error("Missing required CSV column: {$column}");

                    return null;
                }
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, escape: '\\')) !== false) {
                $line++;

                if ($values === [null]) {
                    continue; // línea en blanco
                }

                // Columnas que faltan se tratan como vacías; las sobrantes se ignoran.
                $rows[$line] = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function report(array $plan, bool $dryRun): void
    {
        $this->table(['operación', 'aeropuertos'], [
            [$dryRun ? 'crear' : 'creados', count($plan['create'])],
            [$dryRun ? 'actualizar' : 'actualizados', count($plan['update'])],
            ['sin cambios', $plan['unchanged']],
            ['filas omitidas', count($plan['skipped'])],
        ]);

        foreach (array_slice($plan['create'], 0, self::DETAILS_SHOWN) as $airport) {
            $this->line("  + {$airport['iata_code']} {$airport['name']}");
        }

        foreach (array_slice($plan['update'], 0, self::DETAILS_SHOWN) as ['airport' => $airport, 'changes' => $changes]) {
            $fields = collect($changes)->map(fn ($value, $field) => "{$field}: «{$airport->{$field}}» → «{$value}»")->implode(', ');
            $this->line("  ~ {$airport->iata_code} {$fields}");
        }

        foreach (array_slice($plan['skipped'], 0, self::DETAILS_SHOWN) as $skipped) {
            $this->warn("  ! línea {$skipped['line']} ({$skipped['iata']}): {$skipped['reason']}");
        }

        $hidden = max(0, count($plan['create']) - self::DETAILS_SHOWN)
            + max(0, count($plan['update']) - self::DETAILS_SHOWN)
            + max(0, count($plan['skipped']) - self::DETAILS_SHOWN);

        if ($hidden > 0) {
            $this->line("  … y {$hidden} más.");
        }

        $this->line('Campos enriquecidos (city_id, latitude, longitude, timezone): nunca se modifican.');
    }
}
