<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFlightRequest;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class FlightController extends Controller
{
    public function store(
        StoreFlightRequest $request,
        Trip $trip
    ): RedirectResponse {
        $this->authorize('view', $trip);

        $data = $request->validated();

        $origin = Airport::findOrFail($data['origin_airport_id']);
        $destination = Airport::findOrFail($data['destination_airport_id']);

        Flight::create([
            'user_id' => $request->user()->id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'flight_number' => $data['flight_number'],
            'departure_at' => $this->toUtc(
                $data['departure_at'],
                $origin
            ),
            'arrival_at' => $this->toUtc(
                $data['arrival_at'],
                $destination
            ),
            'airline' => $data['airline'] ?? null,
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

        $data = $request->validated();

        $origin = Airport::findOrFail($data['origin_airport_id']);
        $destination = Airport::findOrFail($data['destination_airport_id']);

        $flight->update([
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'flight_number' => $data['flight_number'],
            'departure_at' => $this->toUtc(
                $data['departure_at'],
                $origin
            ),
            'arrival_at' => $this->toUtc(
                $data['arrival_at'],
                $destination
            ),
            'airline' => $data['airline'] ?? null,
        ]);

        return redirect()->route('trips.show', $trip);
    }

    public function destroy(
        Trip $trip,
        Flight $flight
    ): RedirectResponse {
        $this->authorize('view', $trip);
        $this->ensureFlightBelongsToTrip($flight, $trip);

        $flight->delete();

        return redirect()->route('trips.show', $trip);
    }

    private function toUtc(
        string $value,
        Airport $airport
    ): CarbonImmutable {
        return CarbonImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $value,
            $airport->timezone
        )->utc();
    }

    private function ensureFlightBelongsToTrip(
        Flight $flight,
        Trip $trip
    ): void {
        abort_unless($flight->trip_id === $trip->id, 404);
    }
}
