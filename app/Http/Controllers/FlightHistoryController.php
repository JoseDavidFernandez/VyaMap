<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Trip;
use App\Services\TravelStatistics;
use App\Support\FlightPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Flight History: lugar principal de gestión de vuelos (con o sin viaje).
 *
 * Se envían los vuelos realizados, próximos y pendientes de confirmar con su
 * estado (Flight::status(), la misma definición que el resto de páginas). Los
 * de viajes cancelados no se muestran.
 *
 * Las cifras (solo vuelos realizados) las calcula TravelStatistics para cada
 * combinación de los filtros de la página: periodo (todos o un año, por la
 * fecha local de salida) y vínculo (todos, con viaje o independientes). Así
 * la vista nunca recalcula y siempre muestra lo mismo que el servidor.
 */
class FlightHistoryController extends Controller
{
    public function __invoke(TravelStatistics $statistics): Response
    {
        $user = request()->user();

        $flights = Flight::withStatuses(
            Flight::query()
                ->where('user_id', $user->id)
                ->with([
                    'trip',
                    'originAirport.city',
                    'destinationAirport.city',
                ])
                ->chronological('desc')
                ->get()
        )
            ->filter(fn (Flight $flight) => $flight->flight_status !== Flight::CANCELLED)
            ->values();

        $periods = $flights->map(fn (Flight $flight) => (string) $flight->departure_date->year)->unique()->prepend('all');
        $links = [
            'all' => fn (Flight $flight) => true,
            'trip' => fn (Flight $flight) => $flight->trip_id !== null,
            'standalone' => fn (Flight $flight) => $flight->trip_id === null,
        ];

        $stats = [];

        foreach ($periods as $period) {
            $inPeriod = $period === 'all'
                ? $flights
                : $flights->filter(fn (Flight $flight) => (string) $flight->departure_date->year === $period);

            foreach ($links as $link => $matches) {
                $stats["{$period}:{$link}"] = $statistics->flights($inPeriod->filter($matches));
            }
        }

        return Inertia::render('FlightHistory', [
            'flights' => $flights->map(fn (Flight $flight) => FlightPresenter::toArray($flight))->values(),

            // Clave "periodo:vínculo", p. ej. "all:all" o "2024:standalone".
            'stats' => $stats,

            // Viajes del usuario para asociar un vuelo (los cancelados no se ofrecen).
            'trips' => Trip::query()
                ->where('user_id', $user->id)
                ->notCancelled()
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date', 'status'])
                ->map(fn (Trip $trip) => [
                    'id' => $trip->id,
                    'name' => $trip->name,
                    'start_date' => $trip->start_date?->format('Y-m-d'),
                    'end_date' => $trip->end_date?->format('Y-m-d'),
                ])
                ->values(),
        ]);
    }
}
