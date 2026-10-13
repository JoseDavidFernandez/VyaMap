<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\CityAlias;
use App\Support\CityAliasNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $normalizedQuery = CityAliasNormalizer::normalize($query);

        $learnedAlias = CityAlias::query()
            ->with('city.country')
            ->where('normalized_alias', $normalizedQuery)
            ->first();

        $manualAlias = config("city_aliases.{$normalizedQuery}");

        $airports = Airport::query()
            ->with('city.country')
            ->where(function ($builder) use (
                $query,
                $normalizedQuery,
                $learnedAlias,
                $manualAlias
            ) {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('iata_code', 'like', "%{$query}%")
                    ->orWhere('icao_code', 'like', "%{$query}%")
                    ->orWhereHas('city', function ($cityQuery) use ($query) {
                        $cityQuery->where('name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('city.aliases', function ($aliasQuery) use ($normalizedQuery) {
                        $aliasQuery->where('normalized_alias', $normalizedQuery);
                    });

                if ($manualAlias) {
                    $builder->orWhereHas('city', function ($cityQuery) use ($manualAlias) {
                        $cityQuery
                            ->whereRaw('LOWER(name) = ?', [
                                mb_strtolower($manualAlias['name']),
                            ])
                            ->whereHas('country', function ($countryQuery) use ($manualAlias) {
                                $countryQuery->where(
                                    'iso_code',
                                    $manualAlias['country_iso_code']
                                );
                            });
                    });
                }
            })
            ->orderByRaw(
                'CASE
                    WHEN iata_code = ? THEN 0
                    WHEN icao_code = ? THEN 1
                    ELSE 2
                END',
                [$query, $query]
            )
            ->when($learnedAlias, function ($builder) use ($learnedAlias) {
                $builder->orderByRaw(
                    'CASE WHEN city_id = ? THEN 0 ELSE 1 END',
                    [$learnedAlias->city_id]
                );
            })
            ->when($manualAlias, function ($builder) use ($manualAlias) {
                $builder->orderByRaw(
                    'CASE WHEN EXISTS (
                        SELECT 1
                        FROM cities
                        INNER JOIN countries
                            ON countries.id = cities.country_id
                        WHERE cities.id = airports.city_id
                          AND LOWER(cities.name) = ?
                          AND countries.iso_code = ?
                    ) THEN 0 ELSE 1 END',
                    [
                        mb_strtolower($manualAlias['name']),
                        $manualAlias['country_iso_code'],
                    ]
                );
            })
            ->orderByRaw(
                'CASE WHEN name LIKE ? THEN 0 ELSE 1 END',
                ["{$query}%"]
            )
            ->orderBy('iata_code')
            ->get();

        return response()->json([
            'results' => $airports->map(fn ($airport) => [
                'id' => $airport->id,
                'name' => $airport->name,
                'iata_code' => $airport->iata_code,
                'icao_code' => $airport->icao_code,
                'city' => $airport->city?->name,
                'country' => $airport->city?->country?->name,
                'latitude' => (float) $airport->latitude,
                'longitude' => (float) $airport->longitude,
                'timezone' => $airport->timezone,
            ]),
        ]);
    }
}
