<?php

namespace App\Http\Controllers;

use App\Models\Airport;
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

        $airports = Airport::query()
            ->with('city.country')
            ->where(function ($builder) use ($query) {
                $builder
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('iata_code', 'like', "%{$query}%")
                    ->orWhere('icao_code', 'like', "%{$query}%")
                    ->orWhereHas('city', function ($cityQuery) use ($query) {
                        $cityQuery->where('name', 'like', "%{$query}%");
                    });
            })
            ->orderByRaw(
                'CASE
                    WHEN iata_code = ? THEN 0
                    WHEN icao_code = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END',
                [$query, $query, "{$query}%"]
            )
            ->limit(10)
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
