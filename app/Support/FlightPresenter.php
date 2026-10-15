<?php

namespace App\Support;

use App\Models\Airport;
use App\Models\Flight;

/**
 * Forma común de un vuelo en Flight History, Passport y la ficha del viaje.
 * Requiere flight_status (Flight::withStatuses) y las relaciones trip,
 * originAirport.city y destinationAirport.city cargadas.
 */
class FlightPresenter
{
    /**
     * Forma común de un vuelo para las vistas. Sin horas no hay instante ni
     * duración; sin coordenadas no hay distancia (nunca se inventan).
     */
    public static function toArray(Flight $flight): array
    {
        $distance = $flight->distanceKm();

        return [
            'id' => $flight->id,
            // flown cuenta en las cifras; upcoming y pending se muestran aparte.
            'status' => $flight->flight_status,
            'confirmed' => $flight->confirmed_at !== null,
            'departure_date' => $flight->departure_date?->format('Y-m-d'),
            'flight_number' => $flight->flight_number,
            'airline' => $flight->airline,
            'departure' => $flight->departure_at?->toISOString(),
            'arrival' => $flight->arrival_at?->toISOString(),
            'distance_km' => $distance === null ? null : (int) round($distance),
            'duration_minutes' => $flight->durationMinutes(),
            'trip' => $flight->trip ? [
                'id' => $flight->trip->id,
                'name' => $flight->trip->name,
            ] : null,
            'origin' => self::airport($flight->originAirport),
            'destination' => self::airport($flight->destinationAirport),
        ];
    }

    private static function airport(Airport $airport): array
    {
        return [
            'id' => $airport->id,
            'code' => $airport->iata_code ?: $airport->icao_code,
            'airport' => $airport->name,
            'city' => $airport->city?->name,
            'latitude' => $airport->latitude === null ? null : (float) $airport->latitude,
            'longitude' => $airport->longitude === null ? null : (float) $airport->longitude,
            'timezone' => $airport->timezone,
        ];
    }
}
