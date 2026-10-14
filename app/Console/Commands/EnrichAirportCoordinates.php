<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa latitud y longitud de aeropuertos desde airports.csv (OurAirports).
 *
 * Sin --apply solo simula. Con --apply escribe el plan en una transacción.
 *
 * - Identificación por IATA (sin cambios).
 * - Solo se completan pares totalmente vacíos. Un aeropuerto con las dos
 *   coordenadas está completo; con una sola, se omite: completarlo
 *   sobrescribiría la que ya tiene.
 * - Coordenadas vacías, no numéricas, infinitas o fuera de rango: fila no
 *   válida; nunca se convierten a 0 ni se guardan a medias.
 * - IATA repetido en el CSV con coordenadas distintas: se omite.
 * - Errores estructurales del CSV: FAILURE antes de escribir. Filas no
 *   válidas, omitidas o sin aeropuerto no impiden aplicar el resto (SUCCESS).
 */
#[Signature('airports:enrich-coordinates {--apply : Aplica las coordenadas previstas (sin esta opción solo se simula)}')]
#[Description('Enrich existing VyaMap airports with coordinates from OurAirports')]
class EnrichAirportCoordinates extends Command
{
    private const REQUIRED_COLUMNS = ['iata_code', 'latitude_deg', 'longitude_deg'];

    private const DETAILS_SHOWN = 20;

    public function handle(): int
    {
        $rows = $this->readCsv(storage_path('app/data/airports.csv'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $plan = $this->plan($rows);

        $this->report($plan);

        if (! $this->option('apply')) {
            $this->info('DRY RUN: no database records were modified.');

            return self::SUCCESS;
        }

        $updated = DB::transaction(function () use ($plan) {
            $updated = 0;

            foreach ($plan['update'] as ['airport' => $airport, 'latitude' => $latitude, 'longitude' => $longitude]) {
                $airport->update(['latitude' => $latitude, 'longitude' => $longitude]);
                $updated++;
            }

            return $updated;
        });

        $this->info("UPDATED: {$updated}");

        return self::SUCCESS;
    }

    /**
     * Lectura y validación estructural. Null si el archivo no es utilizable.
     *
     * @return array<int, array<string, string>|null>|null Filas por línea (null si está mal formada).
     */
    private function readCsv(string $path): ?array
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

            $columns = array_flip($headers);
            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, escape: '\\')) !== false) {
                $line++;

                if ($values === [null]) {
                    continue;
                }

                // Solo las columnas necesarias: el archivo tiene ~86 000 filas.
                $rows[$line] = count($values) === count($headers)
                    ? array_map(fn ($column) => $values[$columns[$column]], array_combine(self::REQUIRED_COLUMNS, self::REQUIRED_COLUMNS))
                    : null;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Plan completo, sin escribir nada.
     */
    private function plan(array $rows): array
    {
        $plan = ['rows' => count($rows), 'without_iata' => 0, 'update' => [], 'complete' => 0, 'partial' => [], 'not_found' => 0, 'invalid' => [], 'skipped' => []];

        $airports = Airport::query()->whereNotNull('iata_code')->get()->keyBy(fn ($airport) => strtoupper($airport->iata_code));
        $proposals = [];

        foreach ($rows as $line => $row) {
            if ($row === null) {
                $plan['skipped'][] = ['line' => $line, 'code' => '?', 'reason' => 'número de columnas incorrecto'];

                continue;
            }

            $iata = strtoupper(trim($row['iata_code']));

            // OurAirports incluye miles de aeródromos sin IATA: no se informan uno a uno.
            if ($iata === '') {
                $plan['without_iata']++;

                continue;
            }

            $latitude = $this->coordinate($row['latitude_deg'], 90);
            $longitude = $this->coordinate($row['longitude_deg'], 180);

            if ($latitude === null || $longitude === null) {
                $plan['invalid'][] = ['line' => $line, 'code' => $iata, 'reason' => sprintf('coordenadas no válidas (%s, %s)', $this->shown($row['latitude_deg']), $this->shown($row['longitude_deg']))];

                continue;
            }

            $airport = $airports->get($iata);

            if ($airport === null) {
                $plan['not_found']++;

                continue;
            }

            $proposals[$airport->id][] = ['line' => $line, 'airport' => $airport, 'latitude' => $latitude, 'longitude' => $longitude];
        }

        foreach ($proposals as $items) {
            $airport = $items[0]['airport'];
            $pairs = array_unique(array_map(fn ($item) => $item['latitude'] . ',' . $item['longitude'], $items));

            if (count($pairs) > 1) {
                $plan['skipped'][] = ['line' => $items[0]['line'], 'code' => $airport->iata_code, 'reason' => 'IATA repetido en el CSV con coordenadas distintas'];

                continue;
            }

            if ($airport->latitude !== null && $airport->longitude !== null) {
                $plan['complete']++;

                continue;
            }

            if ($airport->latitude !== null || $airport->longitude !== null) {
                $plan['partial'][] = $airport;

                continue;
            }

            $plan['update'][] = $items[0];
        }

        return $plan;
    }

    /**
     * Coordenada numérica, finita y dentro de rango, o null. Nunca convierte vacíos a 0.
     */
    private function coordinate(mixed $value, int $limit): ?float
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) && abs($number) <= $limit ? $number : null;
    }

    private function shown(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'vacía' : mb_substr($value, 0, 20);
    }

    private function report(array $plan): void
    {
        $apply = (bool) $this->option('apply');

        $this->table(['resultado', 'número'], [
            ['filas leídas', $plan['rows']],
            ['filas sin IATA (no aplicables)', $plan['without_iata']],
            [$apply ? 'aeropuertos actualizados' : 'aeropuertos por actualizar', count($plan['update'])],
            ['aeropuertos ya completos', $plan['complete']],
            ['omitidos: coordenadas parciales (no se sobrescriben)', count($plan['partial'])],
            ['filas sin aeropuerto en VyaMap', $plan['not_found']],
            ['filas con coordenadas no válidas', count($plan['invalid'])],
            ['filas omitidas por otros motivos', count($plan['skipped'])],
        ]);

        foreach (array_slice($plan['update'], 0, self::DETAILS_SHOWN) as $item) {
            $this->line(sprintf('  + %s: %s, %s', $item['airport']->iata_code, $item['latitude'], $item['longitude']));
        }

        foreach (array_slice($plan['partial'], 0, self::DETAILS_SHOWN) as $airport) {
            $this->warn("  ? {$airport->iata_code}: solo tiene una coordenada; no se completa para no sobrescribirla");
        }

        foreach ([...array_slice($plan['invalid'], 0, self::DETAILS_SHOWN), ...array_slice($plan['skipped'], 0, self::DETAILS_SHOWN)] as $entry) {
            $this->warn("  ? línea {$entry['line']} {$entry['code']}: {$entry['reason']}");
        }

        foreach (['update' => 'actualizar', 'partial' => 'parciales', 'invalid' => 'no válidas', 'skipped' => 'omitidas'] as $key => $label) {
            if (count($plan[$key]) > self::DETAILS_SHOWN) {
                $this->line('    … y ' . (count($plan[$key]) - self::DETAILS_SHOWN) . " más ({$label}).");
            }
        }
    }
}
