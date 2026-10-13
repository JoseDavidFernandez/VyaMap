<?php

namespace App\Services;

use App\Exceptions\GeoapifyUnavailableException;
use App\Models\City;
use App\Models\CityAlias;
use App\Support\CityAliasNormalizer;
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

    public function search(string $query): array
    {
        $localResults = $this->searchLocal($query);

        // Si hay una coincidencia exacta local, no necesitamos consultar Geoapify.
        $normalizedQuery = CityAliasNormalizer::normalize($query);

        $hasExactLocalMatch = $localResults->contains(
            fn (City $city) =>
                CityAliasNormalizer::normalize($city->name) === $normalizedQuery
                || $city->aliases->contains(
                    fn (CityAlias $alias) =>
                        $alias->normalized_alias === $normalizedQuery
                )
        );

        if ($hasExactLocalMatch) {
            return $this->formatLocalResults($localResults);
        }

        $geoapifyResults = $this->geoapifyResults($query) ?? [];

        // Conservamos las coincidencias locales y añadimos resultados externos.
        return collect($this->formatLocalResults($localResults))
            ->concat($geoapifyResults)
            ->unique(fn (array $result) =>
                strtolower($result['name']) . '|'
                . strtolower($result['country'] ?? '') . '|'
                . strtolower($result['region_code'] ?? '')
            )
            ->take(10)
            ->values()
            ->all();
    }

    private function searchLocal(string $query): Collection
    {
        $normalizedQuery = CityAliasNormalizer::normalize($query);
        $manualAlias = config("city_aliases.{$normalizedQuery}");

        $cities = City::query()
            ->notMerged()
            ->with(['country', 'aliases'])
            ->where(function ($builder) use ($query, $normalizedQuery) {
                $builder
                    ->where('name', 'like', '%' . $query . '%')
                    ->orWhereHas('aliases', function ($aliasQuery) use ($normalizedQuery) {
                        $aliasQuery->where('normalized_alias', $normalizedQuery);
                    })
                    ->orWhereHas('aliases', function ($aliasQuery) use ($normalizedQuery) {
                        $aliasQuery->where('normalized_alias', 'like', '%' . $normalizedQuery . '%');
                    });
            })
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($manualAlias) {
            $manualCities = City::query()
                ->notMerged()
                ->with(['country', 'aliases'])
                ->whereRaw('LOWER(name) = ?', [
                    mb_strtolower($manualAlias['name']),
                ])
                ->whereHas('country', function ($countryQuery) use ($manualAlias) {
                    $countryQuery->where(
                        'iso_code',
                        $manualAlias['country_iso_code']
                    );
                })
                ->get();

            $cities = $manualCities
                ->concat($cities)
                ->unique('id')
                ->take(10);
        }

        return new Collection($cities->values()->all());
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
