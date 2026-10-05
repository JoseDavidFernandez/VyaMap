<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use Inertia\Inertia;
use Inertia\Response;

class FlightHistoryController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();

        $flights = Flight::query()
            ->where('user_id', $user->id)
            ->with([
                'originAirport.city',
                'destinationAirport.city',
            ])
            ->orderByDesc('departure_at')
            ->get();

        return Inertia::render('FlightHistory', [
            'flights' => $flights->map(function (Flight $flight) {
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
            })->values(),
        ]);
    }

    private function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2,
    ): float {
        $earthRadius = 6371;

        $latitudeDifference = deg2rad($latitude2 - $latitude1);
        $longitudeDifference = deg2rad($longitude2 - $longitude1);

        $a =
            sin($latitudeDifference / 2) ** 2 +
            cos(deg2rad($latitude1)) *
            cos(deg2rad($latitude2)) *
            sin($longitudeDifference / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}