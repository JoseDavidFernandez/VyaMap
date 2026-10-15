<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Trip;
use App\Support\FlightPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Flight History: lugar principal de gestión de vuelos (con o sin viaje).
 *
 * Se envían los vuelos realizados, próximos y pendientes de confirmar con su
 * estado (Flight::status(), la misma definición que el resto de páginas). Los
 * de viajes cancelados no se muestran. Las cifras las calcula la vista solo
 * con los realizados.
 */
class FlightHistoryController extends Controller
{
    public function __invoke(): Response
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

        return Inertia::render('FlightHistory', [
            'flights' => $flights->map(fn (Flight $flight) => FlightPresenter::toArray($flight))->values(),

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
