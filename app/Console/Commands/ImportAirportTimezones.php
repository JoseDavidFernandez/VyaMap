<?php

namespace App\Console\Commands;

use App\Models\Airport;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa zonas horarias IANA de aeropuertos desde airport_timezones.csv.
 *
 * Sin --apply solo simula. Con --apply escribe el plan en una transacción.
 *
 * - Identificación: por ICAO; si el ICAO no existe en VyaMap, por IATA solo
 *   si ese aeropuerto no tiene otro ICAO. Sin ICAO en la fila, por IATA.
 * - Zona vacía o no IANA: la fila se omite y la zona actual se conserva.
 * - Aeropuerto con vuelos (origen o destino) y zona distinta: conflicto, no se
 *   cambia. Los vuelos guardan la hora en UTC calculada con la zona del
 *   aeropuerto y la muestran en ella: cambiarla desplazaría su hora local.
 * - Varias filas para el mismo aeropuerto con zonas distintas: conflicto.
 * - Errores estructurales del CSV: FAILURE antes de escribir. Filas omitidas,
 *   no encontradas o en conflicto no impiden aplicar el resto (SUCCESS).
 */
#[Signature('airports:import-timezones {--apply : Aplica las zonas horarias previstas (sin esta opción solo se simula)}')]
#[Description('Import IANA timezones for VyaMap airports from CSV')]
class ImportAirportTimezones extends Command
{
    private const REQUIRED_COLUMNS = ['iata_code', 'icao_code', 'timezone'];

    private const DETAILS_SHOWN = 20;

    public function handle(): int
    {
        $rows = $this->readCsv(storage_path('app/data/airport_timezones.csv'));

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

            foreach ($plan['update'] as ['airport' => $airport, 'timezone' => $timezone]) {
                $airport->timezone = $timezone;
                $airport->save();
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
            $this->error("Timezone dataset not found or unreadable: {$path}");

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

                if ($values !== [null]) {
                    $rows[$line] = count($values) === count($headers) ? array_combine($headers, $values) : null;
                }
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
        $plan = ['rows' => count($rows), 'update' => [], 'unchanged' => 0, 'conflict' => [], 'skipped' => [], 'not_found' => []];

        $validTimezones = array_flip(DateTimeZone::listIdentifiers());
        $airports = Airport::query()->withCount(['departureFlights', 'arrivalFlights'])->get();
        $byIcao = $airports->filter(fn ($airport) => $airport->icao_code !== null)->keyBy(fn ($airport) => strtoupper($airport->icao_code));
        $byIata = $airports->filter(fn ($airport) => $airport->iata_code !== null)->keyBy(fn ($airport) => strtoupper($airport->iata_code));

        // Zona propuesta por cada aeropuerto: detecta filas contradictorias.
        $proposals = [];

        foreach ($rows as $line => $row) {
            if ($row === null) {
                $plan['skipped'][] = ['line' => $line, 'code' => '?', 'reason' => 'número de columnas incorrecto'];

                continue;
            }

            $iata = strtoupper(trim($row['iata_code']));
            $icao = strtoupper(trim($row['icao_code']));
            $timezone = trim($row['timezone']);
            $code = $icao !== '' ? $icao : ($iata !== '' ? $iata : '?');

            if ($iata === '' && $icao === '') {
                $plan['skipped'][] = ['line' => $line, 'code' => $code, 'reason' => 'sin IATA ni ICAO'];

                continue;
            }

            if ($timezone === '' || ! isset($validTimezones[$timezone])) {
                $plan['skipped'][] = ['line' => $line, 'code' => $code, 'reason' => $timezone === '' ? 'zona horaria vacía' : "zona horaria no válida «{$timezone}»"];

                continue;
            }

            $airport = $this->findAirport($icao, $iata, $byIcao, $byIata);

            if (is_string($airport)) {
                $plan['skipped'][] = ['line' => $line, 'code' => $code, 'reason' => $airport];

                continue;
            }

            if ($airport === null) {
                $plan['not_found'][] = ['line' => $line, 'code' => $code];

                continue;
            }

            $proposals[$airport->id][] = ['line' => $line, 'airport' => $airport, 'timezone' => $timezone];
        }

        foreach ($proposals as $items) {
            $airport = $items[0]['airport'];
            $timezones = array_values(array_unique(array_column($items, 'timezone')));

            if (count($timezones) > 1) {
                $plan['conflict'][] = ['airport' => $airport, 'reason' => 'filas con zonas distintas (' . implode(', ', $timezones) . ')'];

                continue;
            }

            $timezone = $timezones[0];

            if ($airport->timezone === $timezone) {
                $plan['unchanged']++;

                continue;
            }

            $flights = $airport->departure_flights_count + $airport->arrival_flights_count;

            if ($flights > 0) {
                $plan['conflict'][] = ['airport' => $airport, 'reason' => sprintf(
                    'tiene %d vuelo(s); cambiar %s → %s desplazaría su hora local',
                    $flights,
                    $airport->timezone ?? 'sin zona',
                    $timezone,
                )];

                continue;
            }

            $plan['update'][] = ['airport' => $airport, 'timezone' => $timezone, 'from' => $airport->timezone];
        }

        return $plan;
    }

    /**
     * Aeropuerto de la fila, null si no existe, o el motivo para no asociarlo.
     */
    private function findAirport(string $icao, string $iata, $byIcao, $byIata): Airport|string|null
    {
        if ($icao === '') {
            return $byIata->get($iata);
        }

        if ($airport = $byIcao->get($icao)) {
            return $airport;
        }

        // ICAO desconocido: IATA solo si no contradice el ICAO guardado.
        $airport = $iata === '' ? null : $byIata->get($iata);

        if ($airport !== null && $airport->icao_code !== null && strtoupper($airport->icao_code) !== $icao) {
            return "el ICAO {$icao} no existe y el IATA {$iata} corresponde a otro ICAO ({$airport->icao_code})";
        }

        return $airport;
    }

    private function report(array $plan): void
    {
        $apply = (bool) $this->option('apply');

        $this->table(['resultado', 'número'], [
            ['filas leídas', $plan['rows']],
            [$apply ? 'aeropuertos actualizados' : 'aeropuertos por actualizar', count($plan['update'])],
            ['aeropuertos sin cambios', $plan['unchanged']],
            ['conflictos (no se cambian)', count($plan['conflict'])],
            ['filas omitidas', count($plan['skipped'])],
            ['filas sin aeropuerto en VyaMap', count($plan['not_found'])],
        ]);

        $sections = [
            'update' => fn ($e) => sprintf('  + %s: %s → %s', $this->label($e['airport']), $e['from'] ?? 'sin zona', $e['timezone']),
            'conflict' => fn ($e) => sprintf('  ! %s: conflicto, %s', $this->label($e['airport']), $e['reason']),
            'skipped' => fn ($e) => "  ? línea {$e['line']} {$e['code']}: {$e['reason']}",
            'not_found' => fn ($e) => "  ? línea {$e['line']} {$e['code']}: aeropuerto no encontrado",
        ];

        foreach ($sections as $key => $format) {
            foreach (array_slice($plan[$key], 0, self::DETAILS_SHOWN) as $entry) {
                $key === 'update' ? $this->line($format($entry)) : $this->warn($format($entry));
            }

            if (count($plan[$key]) > self::DETAILS_SHOWN) {
                $this->line('    … y ' . (count($plan[$key]) - self::DETAILS_SHOWN) . ' más.');
            }
        }
    }

    private function label(Airport $airport): string
    {
        return $airport->iata_code ?? $airport->icao_code ?? "#{$airport->id}";
    }
}
