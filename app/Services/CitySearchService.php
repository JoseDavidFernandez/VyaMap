<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityAlias;
use App\Support\CityAliasNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class CitySearchService
{
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

        $geoapifyResults = $this->searchGeoapify($query);

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

    private function searchGeoapify(string $query): array
    {
        $response = Http::get(
            'https://api.geoapify.com/v1/geocode/search',
            [
                'text' => $query,
                'format' => 'json',
                'type' => 'city',
                'limit' => 10,
                'apiKey' => config('services.geoapify.key'),
            ]
        );

        if ($response->failed()) {
            return [];
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
