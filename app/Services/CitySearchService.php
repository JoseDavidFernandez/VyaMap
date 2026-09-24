<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;

class CitySearchService
{
    public function search(string $query): array
    {
        $localResults = $this->searchLocal($query);

        if ($localResults->isNotEmpty()) {
            return $localResults->map(fn (City $city) => [
                'source' => 'local',
                'id' => $city->id,
                'name' => $city->name,
                'country' => $city->country->name,
                'iso_code' => $city->country->iso_code,
                'latitude' => (float) $city->latitude,
                'longitude' => (float) $city->longitude,
            ])->values()->all();
        }

        return $this->searchGeoapify($query);
    }

    private function searchLocal(string $query): Collection
    {
        return City::query()
            ->with('country')
            ->where('name', 'like', '%' . $query . '%')
            ->orderBy('name')
            ->limit(10)
            ->get();
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

        $normalizedQuery = mb_strtolower(trim($query));

        $results = collect($response->json('results', []))
            ->filter(
                fn (array $result) =>
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
                    'latitude' => $result['lat'] ?? null,
                    'longitude' => $result['lon'] ?? null,
                    'external_id' => $result['place_id'] ?? null,
                    '_exact_match' => $name !== null
                        && mb_strtolower(trim($name)) === $normalizedQuery,
                    '_importance' => $result['rank']['importance'] ?? 0,
                ];
            })
            ->filter(
                fn (array $result) =>
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
            ->unique(
                fn (array $result) =>
                    strtolower($result['name']) . '|' . strtolower($result['country'])
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