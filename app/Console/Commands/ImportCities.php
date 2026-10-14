<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Models\Place;
use App\Support\CityAliasNormalizer;
use App\Support\GeoDistance;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Importa el catálogo de ciudades desde vyamap_airport_city_mapping.csv.
 *
 * Nunca modifica ciudades existentes (ni coordenadas, ni region_code,
 * external_*, merged_into_id) ni sus relaciones: solo crea ciudades nuevas.
 *
 * Para cada ciudad del CSV (país, nombre y coordenadas):
 * - Candidatas: ciudades del mismo país con el mismo nombre (sin distinguir
 *   mayúsculas ni acentos) o con un alias que coincide. Los nombres que no
 *   pueden normalizarse (quedan vacíos, p. ej. 東京) no se comparan nunca.
 * - Cada candidata se resuelve primero hasta su ciudad canónica siguiendo
 *   merged_into_id (ciclos, cadenas demasiado largas, destinos inexistentes
 *   o en otro país: la fila se omite). Se compara la posición de la canónica.
 * - Canónica a 25 km o menos: es la misma ciudad; no se crea nada. Varias
 *   canónicas distintas: coincidencia ambigua, se omite (cities:reconcile).
 * - Fusionada cercana cuya canónica está a más de 25 km: posiciones
 *   incoherentes, se omite.
 * - Canónica sin coordenadas: no se puede comparar y la fila se omite. Sus
 *   aeropuertos y lugares se muestran solo como diagnóstico; nunca autorizan
 *   un alta.
 * - Todas las canónicas a más de 25 km: homónimo; se crea, salvo que otra
 *   ciudad del país esté a 2 km o menos con otro nombre: posible renombrado,
 *   solo se informa (ni alias ni fusión automáticos).
 * - Filas no válidas y países inexistentes se omiten e informan.
 *   Los errores estructurales del CSV abortan antes de escribir.
 * - --dry-run muestra el plan sin escribir.
 */
class ImportCities extends Command
{
    protected $signature = 'cities:import {--dry-run : Muestra las operaciones previstas sin modificar la base de datos}';

    protected $description = 'Import the VyaMap city catalog from airport-city mapping CSV';

    public const MATCH_RADIUS_KM = 25.0;

    public const RENAME_RADIUS_KM = 2.0;

    private const REQUIRED_COLUMNS = ['city_name', 'country_iso_code', 'city_latitude', 'city_longitude'];

    private const NAME_MAX_LENGTH = 150;

    private const MAX_MERGE_HOPS = 10;

    private const DETAILS_SHOWN = 20;

    public function handle(): int
    {
        $path = storage_path('app/data/vyamap_airport_city_mapping.csv');

        [$headers, $rows] = $this->readCsv($path) ?? [null, null];

        if ($headers === null) {
            return self::FAILURE;
        }

        [$entries, $invalid] = $this->validateRows($headers, $rows);
        $countries = Country::query()->whereIn('iso_code', array_unique(array_column($entries, 'iso')))->pluck('id', 'iso_code');

        $plan = $this->plan($this->group($entries), $countries);
        $plan['rows'] = [
            'read' => count($rows),
            'invalid' => count($invalid),
            'valid' => count($entries),
            'missing_country' => count(array_filter($entries, fn ($entry) => ! isset($countries[$entry['iso']]))),
        ];
        $plan['invalid'] = $invalid;

        $this->report($plan);

        if ($this->option('dry-run')) {
            $this->warn('Simulación (--dry-run): no se ha modificado nada.');

            return self::SUCCESS;
        }

        $this->write($plan['create']);
        $this->line('Cities in database: ' . City::count());

        return self::SUCCESS;
    }

    /**
     * Lectura y validación estructural. Null si el archivo no es utilizable.
     *
     * @return array{list<string>, array<int, list<string>>}|null
     */
    private function readCsv(string $path): ?array
    {
        if (! is_file($path) || ($handle = @fopen($path, 'r')) === false) {
            $this->error("CSV not found or unreadable: {$path}");

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
                    $this->error("Missing required column: {$column}");

                    return null;
                }
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, escape: '\\')) !== false) {
                $line++;

                if ($values !== [null]) {
                    $rows[$line] = $values;
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Validación individual de filas.
     *
     * @return array{list<array{line: int, name: string, normalized: string, iso: string, latitude: float, longitude: float}>, list<array{line: int, reason: string}>}
     */
    private function validateRows(array $headers, array $rows): array
    {
        $entries = [];
        $invalid = [];

        foreach ($rows as $line => $values) {
            if (count($values) !== count($headers)) {
                $invalid[] = ['line' => $line, 'reason' => 'número de columnas incorrecto'];

                continue;
            }

            $row = array_combine($headers, $values);
            $name = trim($row['city_name']);
            $iso = strtoupper(trim($row['country_iso_code']));
            $latitude = trim($row['city_latitude']);
            $longitude = trim($row['city_longitude']);
            $normalized = CityAliasNormalizer::normalize($name);

            $reason = match (true) {
                $name === '' => 'nombre vacío',
                $iso === '' => 'país vacío',
                ! is_numeric($latitude) || ! is_numeric($longitude)
                    || abs((float) $latitude) > 90 || abs((float) $longitude) > 180 => 'coordenadas no válidas',
                mb_strlen($name) > self::NAME_MAX_LENGTH => 'nombre demasiado largo',
                $normalized === '' => 'nombre no normalizable (no se puede comparar con el catálogo)',
                default => null,
            };

            if ($reason !== null) {
                $invalid[] = ['line' => $line, 'reason' => $reason];

                continue;
            }

            $entries[] = [
                'line' => $line, 'name' => $name, 'normalized' => $normalized, 'iso' => $iso,
                'latitude' => (float) $latitude, 'longitude' => (float) $longitude,
            ];
        }

        return [$entries, $invalid];
    }

    /**
     * Agrupa las filas de la misma ciudad (varios aeropuertos por ciudad).
     */
    private function group(array $entries): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            $groups[implode('|', [$entry['iso'], $entry['normalized'], $entry['latitude'], $entry['longitude']])] ??= $entry;
        }

        return array_values($groups);
    }

    /**
     * Planificación: altas, coincidencias, desviaciones, fusiones resueltas,
     * ambigüedades, fusiones no resolubles, ciudades sin coordenadas y
     * posibles renombrados.
     */
    private function plan(array $entries, Collection $countries): array
    {
        $plan = [
            'total' => count($entries), 'create' => [], 'matched' => 0, 'csv_duplicate' => 0,
            'drift' => [], 'merged' => [], 'ambiguous' => [], 'unresolvable' => [],
            'no_coordinates' => [], 'rename' => [], 'missing_countries' => [], 'missing_cities' => 0,
        ];

        $cities = City::query()
            ->whereIn('country_id', $countries->values())
            ->orderBy('id')
            ->get(['id', 'country_id', 'name', 'latitude', 'longitude', 'merged_into_id'])
            ->keyBy('id');

        // Los nombres que no pueden normalizarse nunca sirven como clave.
        $byName = $cities
            ->groupBy(fn (City $city) => $city->country_id . '|' . CityAliasNormalizer::normalize($city->name))
            ->reject(fn ($group, string $key) => str_ends_with($key, '|'));

        $aliases = CityAlias::query()
            ->whereIn('city_id', $cities->keys())
            ->where('normalized_alias', '!=', '')
            ->get(['city_id', 'normalized_alias'])
            ->groupBy('normalized_alias');

        $byCountry = $cities->groupBy('country_id');
        $evidence = $this->locationEvidence($cities->filter(fn (City $city) => ! $this->hasCoordinates($city))->keys());
        $planned = [];

        foreach ($entries as $entry) {
            $countryId = $countries[$entry['iso']] ?? null;

            if ($countryId === null) {
                $plan['missing_countries'][$entry['iso']] = true;
                $plan['missing_cities']++;

                continue;
            }

            $candidates = $byName->get($countryId . '|' . $entry['normalized'], collect())
                ->merge($aliases->get($entry['normalized'], collect())->map(fn ($alias) => $cities->get($alias->city_id))->filter(fn ($city) => $city?->country_id === $countryId))
                ->unique('id')
                ->sortBy('id')
                ->values();

            if ($candidates->isNotEmpty() && ! $this->classifyCandidates($plan, $entry, $candidates, $cities, $countryId, $evidence)) {
                continue;
            }

            // Otra fila del CSV ya prevista para la misma ciudad.
            if (collect($planned[$countryId][$entry['normalized']] ?? [])->contains(fn ($other) => $this->distance($entry, $other) <= self::MATCH_RADIUS_KM)) {
                $plan['csv_duplicate']++;

                continue;
            }

            $nearby = $byCountry->get($countryId, collect())
                ->map(fn (City $city) => ['city' => $city, 'km' => $this->distance($entry, $city)])
                ->filter(fn ($item) => $item['km'] !== null && $item['km'] <= self::RENAME_RADIUS_KM)
                ->sortBy([['km', 'asc'], [fn ($a, $b) => $a['city']->id <=> $b['city']->id]])
                ->first();

            if ($nearby !== null) {
                $plan['rename'][] = $entry + ['other' => $nearby['city'], 'km' => $nearby['km']];

                continue;
            }

            $planned[$countryId][$entry['normalized']][] = $entry;
            $plan['create'][] = $entry + ['country_id' => $countryId];
        }

        return $plan;
    }

    /**
     * Decide qué hacer con una ciudad del CSV que tiene candidatas por nombre
     * o alias. Devuelve true solo si todas son homónimos lejanos y la fila
     * puede seguir hacia el alta; en los demás casos la anota en el plan.
     */
    private function classifyCandidates(array &$plan, array $entry, Collection $candidates, Collection $cities, int $countryId, Collection $evidence): bool
    {
        $items = [];
        $unresolvable = [];

        // Se resuelven todas las candidatas antes de decidir (en orden de id).
        foreach ($candidates as $candidate) {
            $canonical = $this->canonical($candidate, $cities, $countryId);

            if (is_string($canonical)) {
                $unresolvable[] = ['city' => $candidate, 'reason' => $canonical];

                continue;
            }

            $items[] = [
                'from' => $candidate,
                'canonical' => $canonical,
                // La posición que cuenta es la de la canónica.
                'km' => $this->distance($entry, $canonical),
                // Solo para elegir la candidata del diagnóstico y detectar incoherencias.
                'own_km' => $this->distance($entry, $candidate),
            ];
        }

        $near = array_filter($items, fn ($item) => $item['km'] !== null && $item['km'] <= self::MATCH_RADIUS_KM);
        $nearIds = array_values(array_unique(array_map(fn ($item) => $item['canonical']->id, $near)));
        sort($nearIds);

        // Una cadena de fusiones rota siempre impide decidir: la fila se omite,
        // indicando con qué ciudad coincidiría y cuántas cadenas más fallan.
        if ($unresolvable !== []) {
            $first = $unresolvable[0];
            $context = array_filter([
                $nearIds !== [] ? 'coincidiría con #' . implode(', #', $nearIds) : null,
                count($unresolvable) > 1 ? 'y ' . (count($unresolvable) - 1) . ' cadenas más no resolubles' : null,
            ]);

            $plan['unresolvable'][] = $entry + [
                'city' => $first['city'],
                'reason' => $first['reason'] . ($context === [] ? '' : '; ' . implode('; ', $context)),
            ];

            return false;
        }

        $incoherent = array_filter($items, fn ($item) => $item['km'] !== null && $item['km'] > self::MATCH_RADIUS_KM
            && $item['from']->id !== $item['canonical']->id
            && $item['own_km'] !== null && $item['own_km'] <= self::MATCH_RADIUS_KM);

        // Canónicas sin coordenadas: no se puede descartar que sea la misma ciudad.
        $unlocatedIds = array_values(array_unique(array_map(fn ($item) => $item['canonical']->id, array_filter($items, fn ($item) => $item['km'] === null))));
        sort($unlocatedIds);

        if ($near !== []) {
            // Otras canónicas que podrían ser esta ciudad: las de fusionadas
            // incoherentes y las que no tienen coordenadas.
            $canonicalIds = array_values(array_unique([...$nearIds, ...array_map(fn ($item) => $item['canonical']->id, $incoherent), ...$unlocatedIds]));
            sort($canonicalIds);

            if (count($canonicalIds) > 1) {
                $plan['ambiguous'][] = $entry + ['ids' => $canonicalIds, 'unlocated' => $unlocatedIds];

                return false;
            }

            // Diagnóstico determinista: la candidata más cercana por sus propias
            // coordenadas (o por las de la canónica) y, a igualdad, la de menor id.
            usort($near, fn ($a, $b) => [$a['own_km'] ?? $a['km'], $a['from']->id] <=> [$b['own_km'] ?? $b['km'], $b['from']->id]);
            ['from' => $from, 'canonical' => $canonical, 'km' => $km] = $near[0];

            if ($from->id !== $canonical->id) {
                $plan['merged'][] = $entry + ['from' => $from, 'canonical' => $canonical];
            }

            if ($km > 0.001) {
                $plan['drift'][] = $entry + ['city' => $canonical, 'km' => $km];
            }

            $plan['matched']++;

            return false;
        }

        if ($incoherent !== []) {
            $item = reset($incoherent);
            $plan['unresolvable'][] = $entry + ['city' => $item['from'], 'reason' => sprintf(
                'posiciones incoherentes: #%d está a %.2f km pero su canónica #%d está a %.2f km',
                $item['from']->id, $item['own_km'], $item['canonical']->id, $item['km'],
            )];

            return false;
        }

        $undecidable = collect($items)->filter(fn ($item) => $item['km'] === null)->pluck('canonical')->unique('id')->sortBy('id')->values();

        if ($undecidable->isNotEmpty()) {
            $plan['no_coordinates'][] = $entry + [
                'ids' => $undecidable->pluck('id')->all(),
                'evidence' => $undecidable->flatMap(fn (City $city) => $evidence->get($city->id, collect())
                    ->map(fn ($point) => $point + ['city_id' => $city->id, 'km' => $this->distance($entry, $point)]))
                    ->sortBy('km')->values()->all(),
            ];

            return false;
        }

        return true; // Todas las canónicas están a más de 25 km: homónimos.
    }

    /**
     * Sigue merged_into_id hasta la ciudad canónica, o devuelve el motivo
     * por el que no se puede resolver con seguridad.
     */
    private function canonical(City $city, Collection $cities, int $countryId): City|string
    {
        $start = $city->id;
        $visited = [$city->id => true];

        for ($hops = 0; $city->merged_into_id !== null; $hops++) {
            if ($hops >= self::MAX_MERGE_HOPS) {
                return 'cadena de fusiones demasiado larga (más de ' . self::MAX_MERGE_HOPS . " saltos) desde #{$start}";
            }

            $next = $cities->get($city->merged_into_id) ?? City::query()->find($city->merged_into_id);

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
     * Aeropuertos y lugares con coordenadas de las ciudades sin coordenadas.
     * Solo se usan como diagnóstico: nunca autorizan un alta.
     *
     * @return Collection<int, Collection<int, array{label: string, latitude: float, longitude: float}>>
     */
    private function locationEvidence(Collection $cityIds): Collection
    {
        if ($cityIds->isEmpty()) {
            return collect();
        }

        $airports = Airport::query()->whereIn('city_id', $cityIds)->whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['city_id', 'iata_code', 'name', 'latitude', 'longitude'])
            ->map(fn ($airport) => ['city_id' => $airport->city_id, 'label' => 'aeropuerto ' . ($airport->iata_code ?? $airport->name), 'latitude' => (float) $airport->latitude, 'longitude' => (float) $airport->longitude]);

        $places = Place::query()->whereIn('city_id', $cityIds)->whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['city_id', 'name', 'latitude', 'longitude'])
            ->map(fn ($place) => ['city_id' => $place->city_id, 'label' => "lugar {$place->name}", 'latitude' => (float) $place->latitude, 'longitude' => (float) $place->longitude]);

        return $airports->concat($places)
            ->groupBy('city_id')
            ->map(fn ($group) => $group->map(fn ($point) => ['label' => $point['label'], 'latitude' => $point['latitude'], 'longitude' => $point['longitude']])->values());
    }

    private function hasCoordinates(City $city): bool
    {
        return $city->latitude !== null && $city->longitude !== null;
    }

    /**
     * Distancia en km, o null si el otro punto no tiene coordenadas.
     */
    private function distance(array $entry, City|array $other): ?float
    {
        $latitude = $other instanceof City ? $other->latitude : $other['latitude'];
        $longitude = $other instanceof City ? $other->longitude : $other['longitude'];

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return GeoDistance::km($entry['latitude'], $entry['longitude'], (float) $latitude, (float) $longitude);
    }

    /**
     * Escritura transaccional: solo altas de ciudades nuevas.
     */
    private function write(array $creates): void
    {
        DB::transaction(function () use ($creates) {
            foreach ($creates as $entry) {
                City::query()->create([
                    'country_id' => $entry['country_id'],
                    'name' => $entry['name'],
                    'latitude' => $entry['latitude'],
                    'longitude' => $entry['longitude'],
                ]);
            }
        });
    }

    private function report(array $plan): void
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->table(['filas', 'número'], [
            ['leídas', $plan['rows']['read']],
            ['no válidas', $plan['rows']['invalid']],
            ['válidas', $plan['rows']['valid']],
            ['de países no encontrados', $plan['rows']['missing_country']],
        ]);

        $this->table(['ciudades (filas agrupadas)', 'número'], [
            ['total', $plan['total']],
            [$dryRun ? 'crear' : 'creadas', count($plan['create'])],
            ['ya existentes (sin cambios)', $plan['matched']],
            ['misma ciudad que otra alta del CSV', $plan['csv_duplicate']],
            ['omitidas: coincidencia ambigua', count($plan['ambiguous'])],
            ['omitidas: fusión no resoluble', count($plan['unresolvable'])],
            ['omitidas: ciudad existente sin coordenadas', count($plan['no_coordinates'])],
            ['omitidas: posible renombrado', count($plan['rename'])],
            ['omitidas: país no encontrado', $plan['missing_cities']],
        ]);

        $sections = [
            'create' => fn ($e) => "  + {$e['name']} ({$e['iso']})",
            'drift' => fn ($e) => sprintf('  ~ %s (%s): coordenadas distintas (%.2f km) de #%d; no se modifican', $e['name'], $e['iso'], $e['km'], $e['city']->id),
            'merged' => fn ($e) => "  » {$e['name']} ({$e['iso']}): #{$e['from']->id} está fusionada en #{$e['canonical']->id}",
            'ambiguous' => fn ($e) => "  ! línea {$e['line']} {$e['name']} ({$e['iso']}): coincidencia ambigua entre #" . implode(', #', $e['ids'])
                . ($e['unlocated'] === [] ? '' : ' (sin coordenadas: #' . implode(', #', $e['unlocated']) . ')')
                . ' (usar cities:reconcile)',
            'unresolvable' => fn ($e) => "  ! línea {$e['line']} {$e['name']} ({$e['iso']}): fusión no resoluble ({$e['reason']})",
            'no_coordinates' => fn ($e) => "  ! línea {$e['line']} {$e['name']} ({$e['iso']}): ciudad existente sin coordenadas (#" . implode(', #', $e['ids']) . '); no se puede comparar'
                . ($e['evidence'] === [] ? '' : ' [solo diagnóstico: ' . implode('; ', array_map(fn ($p) => sprintf('%s de #%d a %.0f km', $p['label'], $p['city_id'], $p['km']), array_slice($e['evidence'], 0, 3))) . ']'),
            'rename' => fn ($e) => sprintf('  ? línea %d %s (%s): posible renombrado de «%s» (#%d, %.2f km); no se crea', $e['line'], $e['name'], $e['iso'], $e['other']->name, $e['other']->id, $e['km']),
            'invalid' => fn ($e) => "  ! línea {$e['line']}: {$e['reason']}",
        ];

        foreach ($sections as $key => $format) {
            foreach (array_slice($plan[$key], 0, self::DETAILS_SHOWN) as $entry) {
                in_array($key, ['create', 'drift', 'merged'], true) ? $this->line($format($entry)) : $this->warn($format($entry));
            }

            if (count($plan[$key]) > self::DETAILS_SHOWN) {
                $this->line('    … y ' . (count($plan[$key]) - self::DETAILS_SHOWN) . ' más.');
            }
        }

        if ($plan['missing_countries'] !== []) {
            $this->warn('Países no encontrados: ' . implode(', ', array_keys($plan['missing_countries'])));
        }

        $this->line('Ciudades existentes, coordenadas y campos enriquecidos: nunca se modifican.');
    }
}
