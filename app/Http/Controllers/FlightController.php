<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFlightRequest;
use App\Models\Flight;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;

class FlightController extends Controller
{
    public function store(StoreFlightRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('view', $trip);

        Flight::create([
            'user_id' => $request->user()->id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $request->validated('origin_airport_id'),
            'destination_airport_id' => $request->validated('destination_airport_id'),
            'flight_number' => $request->validated('flight_number'),
            'departure_at' => $request->validated('departure_at'),
            'arrival_at' => $request->validated('arrival_at'),
            'airline' => $request->validated('airline'),
        ]);

        return redirect()->route('trips.show', $trip);
    }

    public function update(
        StoreFlightRequest $request,
        Trip $trip,
        Flight $flight
    ): RedirectResponse {
        $this->authorize('view', $trip);
        $this->ensureFlightBelongsToTrip($flight, $trip);

        $flight->update([
            'origin_airport_id' => $request->validated('origin_airport_id'),
            'destination_airport_id' => $request->validated('destination_airport_id'),
            'flight_number' => $request->validated('flight_number'),
            'departure_at' => $request->validated('departure_at'),
            'arrival_at' => $request->validated('arrival_at'),
            'airline' => $request->validated('airline'),
        ]);

        return redirect()->route('trips.show', $trip);
    }

    public function destroy(Trip $trip, Flight $flight): RedirectResponse
    {
        $this->authorize('view', $trip);
        $this->ensureFlightBelongsToTrip($flight, $trip);

        $flight->delete();

        return redirect()->route('trips.show', $trip);
    }

    private function ensureFlightBelongsToTrip(Flight $flight, Trip $trip): void
    {
        abort_unless($flight->trip_id === $trip->id, 404);
    }
}
