<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\Visit;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PassportController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();

        $trips = Trip::query()
            ->where('user_id', $user->id)
            ->get([
                'id',
                'name',
                'start_date',
                'end_date',
            ]);

        $visits = Visit::query()
            ->whereHas('trip', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with('city.country')
            ->orderByDesc('visited_from')
            ->get();

        $flights = Flight::query()
            ->where('user_id', $user->id)
            ->with([
                'originAirport.city',
                'destinationAirport.city',
            ])
            ->orderByDesc('departure_at')
            ->get();

        $flightData = $flights->map(function (Flight $flight) {
            $distanceKm = $this->calculateDistance(
                (float) $flight->originAirport->latitude,
                (float) $flight->originAirport->longitude,
                (float) $flight->destinationAirport->latitude,
                (float) $flight->destinationAirport->longitude,
            );

            $durationMinutes = null;

            if ($flight->departure_at && $flight->arrival_at) {
                $durationMinutes = max(
                    0,
                    $flight->departure_at->diffInMinutes(
                        $flight->arrival_at,
                    ),
                );
            }

            return [
                'id' => $flight->id,
                'flight_number' => $flight->flight_number,
                'airline' => $flight->airline,
                'departure' => $flight->departure_at?->toISOString(),
                'arrival' => $flight->arrival_at?->toISOString(),
                'distance_km' => round($distanceKm),
                'duration_minutes' => $durationMinutes,

                'origin' => [
                    'code' => $flight->originAirport->iata_code
                        ?: $flight->originAirport->icao_code,
                    'airport' => $flight->originAirport->name,
                    'city' => $flight->originAirport->city->name,
                    'latitude' => (float) $flight->originAirport->latitude,
                    'longitude' => (float) $flight->originAirport->longitude,
                ],

                'destination' => [
                    'code' => $flight->destinationAirport->iata_code
                        ?: $flight->destinationAirport->icao_code,
                    'airport' => $flight->destinationAirport->name,
                    'city' => $flight->destinationAirport->city->name,
                    'latitude' => (float) $flight->destinationAirport->latitude,
                    'longitude' => (float) $flight->destinationAirport->longitude,
                ],
            ];
        })->values();

        $years = collect()
            ->merge($trips->pluck('start_date'))
            ->merge($visits->pluck('visited_from'))
            ->merge($flights->pluck('departure_at'))
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->year)
            ->unique()
            ->sortDesc()
            ->values();

        return Inertia::render('Passport', [
            'totalCountries' => Country::count(),

            'years' => $years,

            'visits' => $visits->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'city_id' => $visit->city->id,
                    'city' => $visit->city->name,
                    'country' => $visit->city->country->name,
                    'iso_code' => $visit->city->country->iso_code,
                    'visited_from' => $visit->visited_from,
                    'visited_until' => $visit->visited_until,
                ];
            })->values(),

            'trips' => $trips->map(fn ($trip) => [
                'id' => $trip->id,
                'name' => $trip->name,
                'start_date' => $trip->start_date?->format('Y-m-d'),
                'end_date' => $trip->end_date?->format('Y-m-d'),
            ])->values(),

            'flights' => $flightData,
        ]);
    }

    private function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2,
    ): float {
        $earthRadius = 6371;

        $lat1 = deg2rad($latitude1);
        $lat2 = deg2rad($latitude2);

        $deltaLat = deg2rad($latitude2 - $latitude1);
        $deltaLon = deg2rad($longitude2 - $longitude1);

        $a =
            sin($deltaLat / 2) ** 2
            + cos($lat1)
            * cos($lat2)
            * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a),
        );

        return $earthRadius * $c;
    }
}