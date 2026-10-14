<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Support\GeoDistance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Asigna a cada aeropuerto su ciudad a partir de vyamap_airport_city_mapping.csv.
 *
 * Sin --apply solo simula. Con --apply escribe el plan en una transacción.
 *
 * Para cada fila (IATA, ciudad, país y coordenadas de la ciudad):
 * - Candidatas: ciudades del país con ese nombre. Cada una se resuelve hasta
 *   su canónica (merged_into_id); ciclos, cadenas demasiado largas, destinos
 *   inexistentes o de otro país: revisión.
 * - Se compara la posición de la canónica con las coordenadas del CSV. Sin
 *   coordenadas válidas (de la canónica o del CSV): revisión; nunca se
 *   tratan como (0, 0).
 * - Una canónica a 5 km o menos: es la ciudad. Varias: ambigua, revisión.
 *   Ninguna: revisión con la distancia de la más cercana.
 * - Aeropuerto sin ciudad: se asigna. Ya en esa canónica: nada. En una
 *   ciudad fusionada en esa canónica: se normaliza. En otra ciudad: conflicto,
 *   no se sobrescribe.
 */
class MapAirportCities extends Command
{
    protected $signature = 'airports:map-cities {--apply : Apply the mappings to the database}';

    protected $description = 'Map VyaMap airports to existing cities using the airport-city mapping CSV';

    public const MATCH_RADIUS_KM = 5.0;

    private const MAX_MERGE_HOPS = 10;

    private const DETAILS_SHOWN = 20;

    private const REQUIRED_COLUMNS = [
        'id_original', 'iata_code', 'icao_code', 'airport_name', 'city_name',
        'country_name', 'country_iso_code', 'city_latitude', 'city_longitude',
    ];

    public function handle(): int
    {
        $rows = $this->readCsv(storage_path('app/data/vyamap_airport_city_mapping.csv'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $plan = $this->plan($rows);

        $this->report($plan);

        if (! $this->option('apply')) {
            $this->info('DRY RUN: no database records were modified.');
            $this->info('Use --apply only after reviewing the result.');

            return self::SUCCESS;
        }

        $writes = [...$plan['assign'], ...$plan['normalize']];

        DB::transaction(function () use ($writes) {
            foreach ($writes as $item) {
                $item['airport']->update(['city_id' => $item['city']->id]);
            }
        });

        $this->info('UPDATED: ' . count($writes));

        return self::SUCCESS;
    }

    /**
     * Lectura y validación estructural. Null si el archivo no es utilizable.
     *
     * @return array<int, array<string, string>|null>|null Filas por número de línea (null si la fila está mal formada).
     */
    private function readCsv(string $path): ?array
    {
        if (! is_file($path) || ($handle = @fopen($path, 'r')) === false) {
            $this->error("Mapping file not found or unreadable: {$path}");

            return null;
        }

        try {
            $headers = fgetcsv($handle, escape: '\\');

            if ($headers === false || $headers === [null]) {
                $this->error('CSV is empty.');

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
        $plan = [
            'rows' => count($rows), 'assign' => [], 'normalize' => [], 'already' => 0,
            'conflict' => [], 'ambiguous' => [], 'review' => [],
            'airport_not_found' => [], 'country_not_found' => [], 'city_not_found' => [],
        ];

        $iataCounts = array_count_values(array_map(
            fn ($row) => $row === null ? '' : strtoupper(trim($row['iata_code'])),
            $rows
        ));
        $airports = Airport::query()->whereNotNull('iata_code')->get()->keyBy(fn ($airport) => strtoupper($airport->iata_code));
        $countries = Country::query()->pluck('id', 'iso_code')->mapWithKeys(fn ($id, $iso) => [strtoupper($iso) => $id]);

        foreach ($rows as $line => $row) {
            if ($row === null) {
                $plan['review'][] = ['line' => $line, 'iata' => '?', 'reason' => 'número de columnas incorrecto'];

                continue;
            }

            $iata = strtoupper(trim($row['iata_code']));
            $cityName = trim($row['city_name']);
            $iso = strtoupper(trim($row['country_iso_code']));
            $entry = ['line' => $line, 'iata' => $iata === '' ? '?' : $iata, 'city_name' => $cityName, 'iso' => $iso];

            if ($iata !== '' && $iataCounts[$iata] > 1) {
                $plan['review'][] = $entry + ['reason' => 'IATA repetido en el CSV'];

                continue;
            }

            $airport = $iata === '' ? null : $airports->get($iata);

            if ($airport === null) {
                $plan['airport_not_found'][] = $entry;

                continue;
            }

            $latitude = $this->coordinate($row['city_latitude'], 90);
            $longitude = $this->coordinate($row['city_longitude'], 180);

            if ($latitude === null || $longitude === null) {
                $plan['review'][] = $entry + ['reason' => 'coordenadas de la ciudad del CSV ausentes o no válidas'];

                continue;
            }

            $countryId = $countries->get($iso);

            if ($countryId === null) {
                $plan['country_not_found'][] = $entry;

                continue;
            }

            $candidates = City::query()
                ->where('country_id', $countryId)
                ->where('name', $cityName)
                ->orderBy('id')
                ->get();

            if ($candidates->isEmpty()) {
                $plan['city_not_found'][] = $entry;

                continue;
            }

            $this->planRow($plan, $entry + ['airport' => $airport], $candidates, $latitude, $longitude, $countryId);
        }

        return $plan;
    }

    private function planRow(array &$plan, array $entry, $candidates, float $latitude, float $longitude, int $countryId): void
    {
        // Canónicas de todas las candidatas; una cadena rota impide decidir.
        $canonicals = [];

        foreach ($candidates as $candidate) {
            $canonical = $this->canonical($candidate, $countryId);

            if (is_string($canonical)) {
                $plan['review'][] = $entry + ['reason' => "fusión no resoluble ({$canonical})"];

                return;
            }

            $canonicals[$canonical->id] = $canonical;
        }

        ksort($canonicals);
        $distances = [];

        foreach ($canonicals as $id => $canonical) {
            $cityLatitude = $this->coordinate($canonical->latitude, 90);
            $cityLongitude = $this->coordinate($canonical->longitude, 180);

            if ($cityLatitude === null || $cityLongitude === null) {
                $plan['review'][] = $entry + ['reason' => "la ciudad candidata #{$id} no tiene coordenadas válidas"];

                return;
            }

            $distances[$id] = GeoDistance::km($latitude, $longitude, $cityLatitude, $cityLongitude);
        }

        $near = array_keys(array_filter($distances, fn ($km) => $km <= self::MATCH_RADIUS_KM));

        if (count($near) > 1) {
            $plan['ambiguous'][] = $entry + ['ids' => $near];

            return;
        }

        if ($near === []) {
            asort($distances);
            $plan['review'][] = $entry + ['reason' => sprintf('ninguna candidata a %.0f km o menos (la más cercana, #%d, a %.2f km)', self::MATCH_RADIUS_KM, array_key_first($distances), reset($distances))];

            return;
        }

        $city = $canonicals[$near[0]];
        $airport = $entry['airport'];
        $item = $entry + ['city' => $city, 'km' => $distances[$city->id]];

        if ($airport->city_id === $city->id) {
            $plan['already']++;

            return;
        }

        if ($airport->city_id === null) {
            $plan['assign'][] = $item;

            return;
        }

        // Ciudad actual fusionada que se resuelve a la elegida: se normaliza.
        $current = City::query()->find($airport->city_id);
        $currentCanonical = $current === null ? null : $this->canonical($current, $countryId);

        if ($current?->merged_into_id !== null && $currentCanonical instanceof City && $currentCanonical->id === $city->id) {
            $plan['normalize'][] = $item + ['from' => $current];

            return;
        }

        $plan['conflict'][] = $item + ['current_id' => $airport->city_id];
    }

    /**
     * Sigue merged_into_id hasta la ciudad canónica (misma convención que
     * cities:import), o devuelve el motivo por el que no se puede resolver.
     */
    private function canonical(City $city, int $countryId): City|string
    {
        $start = $city->id;
        $visited = [$city->id => true];

        for ($hops = 0; $city->merged_into_id !== null; $hops++) {
            if ($hops >= self::MAX_MERGE_HOPS) {
                return 'cadena de fusiones demasiado larga (más de ' . self::MAX_MERGE_HOPS . " saltos) desde #{$start}";
            }

            $next = City::query()->find($city->merged_into_id);

            if ($next === null) {
                return "#{$city->id} apunta a una ciudad inexistente";
            }

            if (isset($visited[$next->id])) {
                return "ciclo de fusiones desde #{$start}";
            }

            if ($next->country_id !== $countryId) {
                return "#{$city->id} está fusionada en #{$next->id}, de otro país";
            }

            $visited[$next->id] = true;
            $city = $next;
        }

        return $city;
    }

    /**
     * Coordenada numérica dentro de rango, o null. Nunca convierte vacíos a 0.
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

    private function report(array $plan): void
    {
        $apply = (bool) $this->option('apply');

        $this->table(['resultado', 'filas'], [
            ['leídas', $plan['rows']],
            [$apply ? 'asignadas' : 'por asignar', count($plan['assign'])],
            [$apply ? 'normalizadas (fusionada → canónica)' : 'por normalizar (fusionada → canónica)', count($plan['normalize'])],
            ['ya asignadas', $plan['already']],
            ['conflictos (no se sobrescriben)', count($plan['conflict'])],
            ['ambiguas', count($plan['ambiguous'])],
            ['para revisión', count($plan['review'])],
            ['aeropuerto no encontrado', count($plan['airport_not_found'])],
            ['país no encontrado', count($plan['country_not_found'])],
            ['ciudad no encontrada', count($plan['city_not_found'])],
        ]);

        $sections = [
            'assign' => fn ($e) => sprintf('  + %s → #%d %s (%s) %.2f km', $e['iata'], $e['city']->id, $e['city']->name, $e['iso'], $e['km']),
            'normalize' => fn ($e) => sprintf('  » %s: #%d (fusionada) → #%d %s', $e['iata'], $e['from']->id, $e['city']->id, $e['city']->name),
            'conflict' => fn ($e) => sprintf('  ! %s: ya asignado a #%d; el CSV indica #%d %s (no se sobrescribe)', $e['iata'], $e['current_id'], $e['city']->id, $e['city']->name),
            'ambiguous' => fn ($e) => "  ! línea {$e['line']} {$e['iata']} {$e['city_name']} ({$e['iso']}): ambigua entre #" . implode(', #', $e['ids']),
            'review' => fn ($e) => "  ? línea {$e['line']} {$e['iata']}: {$e['reason']}",
            'airport_not_found' => fn ($e) => "  ? línea {$e['line']} {$e['iata']}: aeropuerto no encontrado",
            'country_not_found' => fn ($e) => "  ? línea {$e['line']} {$e['iata']}: país {$e['iso']} no encontrado",
            'city_not_found' => fn ($e) => "  ? línea {$e['line']} {$e['iata']}: ciudad {$e['city_name']} ({$e['iso']}) no encontrada",
        ];

        foreach ($sections as $key => $format) {
            foreach (array_slice($plan[$key], 0, self::DETAILS_SHOWN) as $entry) {
                in_array($key, ['assign', 'normalize'], true) ? $this->line($format($entry)) : $this->warn($format($entry));
            }

            if (count($plan[$key]) > self::DETAILS_SHOWN) {
                $this->line('    … y ' . (count($plan[$key]) - self::DETAILS_SHOWN) . ' más.');
            }
        }
    }
}
