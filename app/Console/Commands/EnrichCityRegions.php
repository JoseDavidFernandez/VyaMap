<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Support\GeoDistance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Añade region_code a ciudades homónimas (mismo país y nombre) usando Geoapify.
 *
 * Sin --apply solo simula. Con --apply escribe el plan en una transacción.
 *
 * - Solo ciudades no fusionadas (City::notMerged): una fusionada nunca forma
 *   grupo, no provoca consultas ni recibe region_code.
 * - Nunca sobrescribe un region_code existente.
 * - Ciudad sin coordenadas válidas: se omite sin consultar la API.
 * - Una consulta por nombre y país dentro de una ejecución.
 * - Resultado más cercano a 10 km o menos con state_code: se asigna; a más de
 *   10 km: revisión; sin resultados: sin coincidencia.
 * - Cualquier error de la API (conexión, HTTP o respuesta no válida) termina
 *   con FAILURE antes de escribir nada.
 */
class EnrichCityRegions extends Command
{
    protected $signature = 'cities:enrich-regions {--apply : Aplica los region_code previstos (sin esta opción solo se simula)}';

    protected $description = 'Enrich ambiguous VyaMap cities with region codes using Geoapify';

    public const MATCH_RADIUS_KM = 10.0;

    private const REGION_CODE_MAX_LENGTH = 20;

    /** @var array<string, list<array{lat: float, lon: float, state_code: ?string}>> */
    private array $responses = [];

    public function handle(): int
    {
        if (blank(config('services.geoapify.key'))) {
            $this->error('Geoapify API key is not configured.');

            return self::FAILURE;
        }

        $plan = ['groups' => 0, 'update' => [], 'already_set' => 0, 'invalid_coordinates' => [], 'no_match' => [], 'review' => [], 'no_region' => [], 'error' => null];

        $groups = City::query()
            ->notMerged()
            ->select('country_id', 'name')
            ->groupBy('country_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('country_id')
            ->orderBy('name')
            ->get();

        $plan['groups'] = $groups->count();

        foreach ($groups as $group) {
            $cities = City::query()
                ->notMerged()
                ->where('country_id', $group->country_id)
                ->where('name', $group->name)
                ->with('country')
                ->orderBy('id')
                ->get();

            foreach ($cities as $city) {
                if ($city->region_code !== null) {
                    $plan['already_set']++;

                    continue;
                }

                $latitude = $this->coordinate($city->latitude, 90);
                $longitude = $this->coordinate($city->longitude, 180);

                if ($latitude === null || $longitude === null) {
                    $plan['invalid_coordinates'][] = $city;

                    continue;
                }

                $results = $this->search($city->name, $city->country->iso_code);

                if (is_string($results)) {
                    $plan['error'] = $results;

                    break 2;
                }

                $this->planCity($plan, $city, $latitude, $longitude, $results);
            }
        }

        $this->report($plan);

        if ($plan['error'] !== null) {
            $this->error("Geoapify error: {$plan['error']}. No database records were modified.");

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('DRY RUN: no database records were modified.');

            return self::SUCCESS;
        }

        $updated = DB::transaction(function () use ($plan) {
            $updated = 0;

            foreach ($plan['update'] as ['city' => $city, 'region' => $region]) {
                // Nunca sobrescribe: solo si sigue sin region_code.
                $updated += City::query()->whereKey($city->id)->notMerged()->whereNull('region_code')->update(['region_code' => $region]);
            }

            return $updated;
        });

        $this->info("UPDATED: {$updated}");

        return self::SUCCESS;
    }

    private function planCity(array &$plan, City $city, float $latitude, float $longitude, array $results): void
    {
        if ($results === []) {
            $plan['no_match'][] = $city;

            return;
        }

        $best = collect($results)
            ->map(fn (array $result) => $result + ['distance' => GeoDistance::km($latitude, $longitude, $result['lat'], $result['lon'])])
            ->sortBy('distance')
            ->first();

        if ($best['distance'] > self::MATCH_RADIUS_KM) {
            $plan['review'][] = ['city' => $city, 'distance' => $best['distance']];

            return;
        }

        if ($best['state_code'] === null) {
            $plan['no_region'][] = ['city' => $city, 'distance' => $best['distance']];

            return;
        }

        $plan['update'][] = ['city' => $city, 'region' => $best['state_code'], 'distance' => $best['distance']];
    }

    /**
     * Resultados de Geoapify para un nombre y país (una consulta por ejecución),
     * o un texto con el error. El mensaje nunca incluye la URL con la clave.
     *
     * @return list<array{lat: float, lon: float, state_code: ?string}>|string
     */
    private function search(string $name, string $countryIso): array|string
    {
        $key = mb_strtolower($name) . '|' . $countryIso;

        if (array_key_exists($key, $this->responses)) {
            return $this->responses[$key];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->acceptJson()
                ->get('https://api.geoapify.com/v1/geocode/search', [
                    'text' => "{$name}, {$countryIso}",
                    'type' => 'city',
                    'limit' => 20,
                    'apiKey' => config('services.geoapify.key'),
                ]);
        } catch (Throwable $exception) {
            return 'request failed (' . $exception::class . ')';
        }

        if ($response->failed()) {
            return 'HTTP ' . $response->status();
        }

        $features = $response->json('features');

        if (! is_array($features)) {
            return 'invalid response (no features list)';
        }

        $results = [];

        foreach ($features as $feature) {
            $coordinates = is_array($feature) ? ($feature['geometry']['coordinates'] ?? null) : null;
            $lon = is_array($coordinates) ? $this->coordinate($coordinates[0] ?? null, 180) : null;
            $lat = is_array($coordinates) ? $this->coordinate($coordinates[1] ?? null, 90) : null;

            if ($lat === null || $lon === null) {
                continue; // resultado sin coordenadas válidas: se ignora
            }

            $stateCode = $feature['properties']['state_code'] ?? null;
            $stateCode = is_string($stateCode) ? strtoupper(trim($stateCode)) : null;

            $results[] = [
                'lat' => $lat,
                'lon' => $lon,
                'state_code' => $stateCode === '' || ($stateCode !== null && mb_strlen($stateCode) > self::REGION_CODE_MAX_LENGTH) ? null : $stateCode,
            ];
        }

        return $this->responses[$key] = $results;
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
        $apply = (bool) $this->option('apply') && $plan['error'] === null;

        $this->table(['resultado', 'ciudades'], [
            ['grupos de homónimos (sin fusionadas)', $plan['groups']],
            [$apply ? 'actualizadas' : 'por actualizar', count($plan['update'])],
            ['omitidas: region_code ya asignado', $plan['already_set']],
            ['omitidas: coordenadas no válidas', count($plan['invalid_coordinates'])],
            ['sin coincidencia', count($plan['no_match'])],
            ['revisión (más de 10 km)', count($plan['review'])],
            ['coincidencia sin código de región', count($plan['no_region'])],
            ['errores de la API', $plan['error'] === null ? 0 : 1],
        ]);

        foreach ($plan['update'] as $item) {
            $this->line(sprintf('  + #%d %s (%s) → %s · %.2f km', $item['city']->id, $item['city']->name, $item['city']->country->iso_code, $item['region'], $item['distance']));
        }

        foreach ($plan['invalid_coordinates'] as $city) {
            $this->warn("  ? #{$city->id} {$city->name}: coordenadas no válidas, no se consulta la API");
        }

        foreach ($plan['no_match'] as $city) {
            $this->warn("  ? #{$city->id} {$city->name}: sin coincidencia");
        }

        foreach ($plan['review'] as $item) {
            $this->warn(sprintf('  ? #%d %s: revisión, el resultado más cercano está a %.2f km', $item['city']->id, $item['city']->name, $item['distance']));
        }

        foreach ($plan['no_region'] as $item) {
            $this->warn(sprintf('  ? #%d %s: coincidencia a %.2f km sin código de región', $item['city']->id, $item['city']->name, $item['distance']));
        }
    }
}
