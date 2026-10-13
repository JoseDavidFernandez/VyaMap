<?php

namespace App\Services;

use App\Exceptions\GeoapifyUnavailableException;
use App\Models\City;
use App\Models\CityAlias;
use App\Support\CityAliasNormalizer;
use App\Support\GeoDistance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CitySearchService
{
    /**
     * Los resultados de Geoapify se guardan unos minutos para que la
     * verificación al crear la ciudad no repita la llamada externa.
     */
    private const GEOAPIFY_CACHE_SECONDS = 600;

    private const MAX_RESULTS = 10;

    public function search(string $query, bool $includeExternal = false): array
    {
        return $this->searchWithMeta($query, $includeExternal)['results'];
    }

    /**
     * @return array{results: list<array>, external_searched: bool, external_unavailable: bool}
     */
    public function searchWithMeta(string $query, bool $includeExternal = false): array
    {
        $normalizedQuery = CityAliasNormalizer::normalize($query);
        $localResults = $this->searchLocal($query, $normalizedQuery);

        // Con una coincidencia exacta local no se consulta Geoapify, salvo que el
        // usuario lo pida expresamente ("buscar en todo el mundo"): puede haber
        // ciudades homónimas que aún no están en el catálogo.
        $hasExactLocalMatch = $localResults->contains(
            fn (City $city) => $this->matchRank($city, $normalizedQuery) === 0
        );

        $local = $this->formatLocalResults($localResults);

        if ($hasExactLocalMatch && ! $includeExternal) {
            return ['results' => $local, 'external_searched' => false, 'external_unavailable' => false];
        }

        $geoapifyResults = $this->geoapifyResults($query);

        $external = collect($geoapifyResults ?? [])
            // Lo que ya está en el catálogo (mismo identificador, o mismo nombre
            // y país a poca distancia) no se ofrece dos veces.
            ->reject(fn (array $result) => $localResults->contains(
                fn (City $city) => $this->sameCity($city, $result)
            ));

        $results = collect($local)
            ->concat($external)
            ->unique(fn (array $result) =>
                strtolower($result['name']) . '|'
                . strtolower($result['country'] ?? '') . '|'
                . strtolower($result['region_code'] ?? '') . '|'
                . ($result['id'] ?? $result['external_id'] ?? '')
            )
            ->take(self::MAX_RESULTS)
            ->values()
            ->all();

        return [
            'results' => $results,
            'external_searched' => true,
            'external_unavailable' => $geoapifyResults === null,
        ];
    }

    /**
     * Coincidencias exactas (nombre o alias), después las que empiezan por la
     * búsqueda y por último las que la contienen. Las exactas se consultan
     * aparte para que el límite de resultados nunca las deje fuera.
     */
    private function searchLocal(string $query, string $normalizedQuery): Collection
    {
        $manualAlias = config("city_aliases.{$normalizedQuery}");
        $like = $this->escapeLike($query);

        $base = fn () => City::query()->notMerged()->with(['country', 'aliases']);

        $exact = $base()
            ->where(fn ($builder) => $builder
                ->where('name', $query)    // sin distinguir mayúsculas ni acentos (collation)
                ->orWhereHas('aliases', fn ($aliasQuery) => $aliasQuery->where('normalized_alias', $normalizedQuery)))
            ->get();

        if ($manualAlias) {
            $exact = $exact->concat(
                $base()
                    ->where('name', $manualAlias['name'])
                    ->whereHas('country', fn ($countryQuery) => $countryQuery->where('iso_code', $manualAlias['country_iso_code']))
                    ->get()
            );
        }

        $partial = $base()
            ->where(fn ($builder) => $builder
                ->where('name', 'like', '%' . $like . '%')
                ->orWhereHas('aliases', fn ($aliasQuery) => $aliasQuery->where('normalized_alias', 'like', '%' . $this->escapeLike($normalizedQuery) . '%')))
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$like . '%'])
            ->orderBy('name')
            ->limit(self::MAX_RESULTS)
            ->get();

        $cities = $exact
            ->concat($partial)
            ->unique('id')
            ->sortBy(fn (City $city) => sprintf('%d|%s', $this->matchRank($city, $normalizedQuery, $manualAlias), CityAliasNormalizer::normalize($city->name)))
            ->take(self::MAX_RESULTS);

        return new Collection($cities->values()->all());
    }

    /**
     * 0 = nombre, alias o alias manual exactos; 1 = empieza por la búsqueda;
     * 2 = la contiene.
     */
    private function matchRank(City $city, string $normalizedQuery, ?array $manualAlias = null): int
    {
        $name = CityAliasNormalizer::normalize($city->name);

        if (
            $name === $normalizedQuery
            || $city->aliases->contains(fn (CityAlias $alias) => $alias->normalized_alias === $normalizedQuery)
            || ($manualAlias !== null
                && $name === CityAliasNormalizer::normalize($manualAlias['name'])
                && $city->country?->iso_code === $manualAlias['country_iso_code'])
        ) {
            return 0;
        }

        return str_starts_with($name, $normalizedQuery) ? 1 : 2;
    }

    private function sameCity(City $city, array $result): bool
    {
        if (($result['external_id'] ?? null) !== null && $city->external_id === $result['external_id']) {
            return true;
        }

        if (
            CityAliasNormalizer::normalize($city->name) !== CityAliasNormalizer::normalize((string) $result['name'])
            || $city->country?->iso_code !== ($result['iso_code'] ?? null)
            || $city->latitude === null
            || ($result['latitude'] ?? null) === null
        ) {
            return false;
        }

        return GeoDistance::km(
            (float) $city->latitude,
            (float) $city->longitude,
            (float) $result['latitude'],
            (float) $result['longitude'],
        ) <= (float) config('cities.same_city_max_distance_km', 25);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function formatLocalResults(Collection $cities): array
    {
        return $cities->map(fn (City $city) => [
            'source' => 'local',
            'id' => $city->id,
            'name' => $city->name,
            'country' => $city->country->name,
            'iso_code' => $city->country->iso_code,
            'region_code' => $city->region_code,
            'latitude' => (float) $city->latitude,
            'longitude' => (float) $city->longitude,
        ])->values()->all();
    }

    /**
     * Comprueba en el servidor que la ciudad elegida es uno de los resultados
     * que Geoapify devuelve para esa búsqueda, y devuelve sus datos.
     *
     * @throws GeoapifyUnavailableException
     */
    public function verifyGeoapifyCity(string $query, string $externalId): ?array
    {
        $results = $this->geoapifyResults($query);

        if ($results === null) {
            throw new GeoapifyUnavailableException();
        }

        foreach ($results as $result) {
            if (($result['external_id'] ?? null) === $externalId) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Resultados de Geoapify, o null si el servicio no está disponible.
     */
    private function geoapifyResults(string $query): ?array
    {
        $cacheKey = 'geoapify:city-search:' . sha1(CityAliasNormalizer::normalize($query));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $results = $this->searchGeoapify($query);

        if ($results !== null) {
            Cache::put($cacheKey, $results, self::GEOAPIFY_CACHE_SECONDS);
        }

        return $results;
    }

    private function searchGeoapify(string $query): ?array
    {
        $apiKey = config('services.geoapify.key');

        if (blank($apiKey)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->acceptJson()
                ->get(
                    'https://api.geoapify.com/v1/geocode/search',
                    [
                        'text' => $query,
                        'format' => 'json',
                        'type' => 'city',
                        'limit' => 10,
                        'apiKey' => $apiKey,
                    ]
                );
        } catch (Throwable $exception) {
            // Solo la clase: el mensaje puede incluir la URL con la clave.
            Log::warning('Geoapify no está disponible.', [
                'exception' => $exception::class,
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Geoapify ha devuelto un error.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $normalizedQuery = CityAliasNormalizer::normalize($query);

        $results = collect($response->json('results', []))
            ->filter(fn (array $result) =>
                ($result['result_type'] ?? null) === 'city'
            )
            ->map(function (array $result) use ($normalizedQuery) {
                $name = $result['city'] ?? $result['name'] ?? null;

                return [
                    'source' => 'geoapify',
                    'id' => null,
                    'name' => $name,
                    'country' => $result['country'] ?? null,
                    'iso_code' => isset($result['country_code'])
                        ? strtoupper($result['country_code'])
                        : null,
                    'region_code' => isset($result['state_code'])
                        ? strtoupper($result['state_code'])
                        : null,
                    'latitude' => $result['lat'] ?? null,
                    'longitude' => $result['lon'] ?? null,
                    'external_id' => $result['place_id'] ?? null,
                    '_exact_match' => $name !== null
                        && CityAliasNormalizer::normalize($name) === $normalizedQuery,
                    '_importance' => $result['rank']['importance'] ?? 0,
                ];
            })
            ->filter(fn (array $result) =>
                $result['name'] !== null
                && $result['country'] !== null
                && $result['latitude'] !== null
                && $result['longitude'] !== null
            );

        $exactMatches = $results->filter(
            fn (array $result) => $result['_exact_match']
        );

        if ($exactMatches->isNotEmpty()) {
            $results = $exactMatches;
        }

        return $results
            ->sortByDesc('_importance')
            ->unique(fn (array $result) =>
                strtolower($result['name']) . '|'
                . strtolower($result['country']) . '|'
                . strtolower($result['region_code'] ?? '')
            )
            ->take(5)
            ->map(function (array $result) {
                unset($result['_exact_match'], $result['_importance']);

                return $result;
            })
            ->values()
            ->all();
    }
}
