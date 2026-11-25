<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Trip;
use App\Services\TripFlightService;
use App\Support\FlightPresenter;
use App\Support\StandaloneFlightRules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vuelos existentes en un viaje (TripFlightService): sugerencias y búsqueda
 * de vuelos independientes del usuario, asociarlos a un viaje y quitarlos.
 */
class TripFlightController extends Controller
{
    public function __construct(private readonly TripFlightService $flights)
    {
    }

    // Para los formularios de crear y editar: el viaje aún no tiene (o puede cambiar) sus fechas.
    public function suggestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return $this->json($this->flights->suggestions($request->user()->id, $data['start_date'], $data['end_date']));
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return $this->json($this->flights->search($request->user()->id, $data['q'] ?? null, $data['date'] ?? null));
    }

    public function attach(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate(
            StandaloneFlightRules::rules($request->user()->id, required: true),
            StandaloneFlightRules::messages(),
        );

        $this->flights->attach($trip, $data['flight_ids']);

        return redirect()->route('trips.show', $trip);
    }

    public function detach(Trip $trip, Flight $flight): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->authorize('update', $flight);

        $this->flights->detach($trip, $flight);

        return redirect()->route('trips.show', $trip);
    }

    /**
     * @param  Collection<int, Flight>  $flights
     */
    private function json(Collection $flights): JsonResponse
    {
        return response()->json([
            'flights' => Flight::withStatuses($flights)
                ->map(fn (Flight $flight) => FlightPresenter::toArray($flight))
                ->values(),
        ]);
    }
}
