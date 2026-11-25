<?php

namespace App\Http\Controllers;

use App\Http\Requests\FlightRequest;
use App\Models\Flight;
use App\Models\Trip;
use App\Services\TripFlightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Vuelos desde la ficha de un viaje (/trips/{trip}/flights, el viaje lo fija
 * la URL) y vuelos generales (/flights, viaje opcional). Ambas rutas usan la
 * misma validación (FlightRequest) y el mismo guardado (save()).
 */
class FlightController extends Controller
{
    public function __construct(private readonly TripFlightService $tripFlights)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Rutas del viaje
    |--------------------------------------------------------------------------
    */

    public function store(FlightRequest $request, Trip $trip): RedirectResponse
    {
        $this->save($request, new Flight(['user_id' => $request->user()->id]), $trip);

        return redirect()->route('trips.show', $trip);
    }

    public function update(FlightRequest $request, Trip $trip, Flight $flight): RedirectResponse
    {
        $this->ensureFlightBelongsToTrip($flight, $trip);

        $this->save($request, $flight, $trip);

        return redirect()->route('trips.show', $trip);
    }

    public function destroy(Trip $trip, Flight $flight): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->ensureFlightBelongsToTrip($flight, $trip);

        $flight->delete();

        return redirect()->route('trips.show', $trip);
    }

    /*
    |--------------------------------------------------------------------------
    | Rutas generales (Flight History)
    |--------------------------------------------------------------------------
    */

    public function storeFlight(FlightRequest $request): RedirectResponse
    {
        $this->save($request, new Flight(['user_id' => $request->user()->id]), $request->trip());

        return back();
    }

    public function updateFlight(FlightRequest $request, Flight $flight): RedirectResponse
    {
        $this->save($request, $flight, $request->trip());

        return back();
    }

    public function destroyFlight(Flight $flight): RedirectResponse
    {
        $this->authorize('delete', $flight);

        $flight->delete();

        return back();
    }

    /**
     * Confirma (o deshace la confirmación de) que un vuelo se realizó. Solo
     * para vuelos que ya salieron. No cambia el viaje ni las visitas.
     */
    public function confirm(Request $request, Flight $flight): RedirectResponse
    {
        $this->authorize('update', $flight);

        $confirmed = $request->validate(['confirmed' => ['required', 'boolean']])['confirmed'];

        $flight->loadMissing('originAirport');

        if ($confirmed && ! $flight->hasDeparted()) {
            throw ValidationException::withMessages([
                'confirmed' => 'Only a flight that has already departed can be confirmed.',
            ]);
        }

        $flight->update(['confirmed_at' => $confirmed ? now() : null]);

        return back();
    }

    private function save(FlightRequest $request, Flight $flight, ?Trip $trip): void
    {
        // Quitar el viaje ("No trip") sigue la misma regla que "Remove from
        // trip": si ya contaba como realizado, queda confirmado. Se evalúa
        // antes de aplicar los cambios del formulario.
        if ($trip === null && $flight->trip_id !== null && $flight->trip) {
            $this->tripFlights->release($flight, $flight->trip);
        }

        $flight->fill($request->flightAttributes());
        $flight->trip_id = $trip?->id;

        // Un vuelo confirmado que pasa al futuro deja de estar confirmado (D7).
        if ($flight->confirmed_at !== null) {
            $flight->setRelation('originAirport', $flight->originAirport()->first());

            if (! $flight->hasDeparted()) {
                $flight->confirmed_at = null;
            }
        }

        $flight->save();
    }

    private function ensureFlightBelongsToTrip(Flight $flight, Trip $trip): void
    {
        abort_unless($flight->trip_id === $trip->id, 404);
    }
}
