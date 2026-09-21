<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Models\Trip;
use App\Models\City;

use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function store(StoreTripRequest $request)
    {
        $trip = Trip::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
        ]);

        return redirect()->route('trips.show', $trip);
    }

    public function show(Trip $trip): Response
    {
        $this->authorize('view', $trip);

        $trip->load([
            'visits.city.country',
            'flights.originAirport.city',
            'flights.destinationAirport.city',
            'journal',
        ]);

        $availableCities = City::with('country')
            ->orderBy('name')
            ->get()
            ->map(fn ($city) => [
                'id' => $city->id,
                'name' => $city->name,
                'country' => $city->country->name,
                'iso_code' => $city->country->iso_code,
            ]);

        return Inertia::render('Trips/Show', [
            'trip' => [
                'id' => $trip->id,
                'name' => $trip->name,
                'description' => $trip->description,
                'start_date' => $trip->start_date?->format('Y-m-d'),
                'end_date' => $trip->end_date?->format('Y-m-d'),
            ],

            'visits' => $trip->visits->map(fn ($visit) => [
                'id' => $visit->id,
                'city' => [
                    'id' => $visit->city->id,
                    'name' => $visit->city->name,
                    'country' => $visit->city->country->name,
                    'iso_code' => $visit->city->country->iso_code,
                    'latitude' => $visit->city->latitude,
                    'longitude' => $visit->city->longitude,
                ],
                'visited_from' => $visit->visited_from,
                'visited_until' => $visit->visited_until,
                'notes' => $visit->notes,
            ]),

            'flights' => $trip->flights->map(fn ($flight) => [
                'id' => $flight->id,
                'flight_number' => $flight->flight_number,
                'airline' => $flight->airline,
                'departure' => $flight->departure?->toISOString(),
                'arrival' => $flight->arrival?->toISOString(),
                'origin' => [
                    'id' => $flight->originAirport->id,
                    'name' => $flight->originAirport->name,
                    'city' => $flight->originAirport->city->name,
                    'latitude' => (float) $flight->originAirport->latitude,
                    'longitude' => (float) $flight->originAirport->longitude,
                ],
                'destination' => [
                    'id' => $flight->destinationAirport->id,
                    'name' => $flight->destinationAirport->name,
                    'city' => $flight->destinationAirport->city->name,
                    'latitude' => (float) $flight->destinationAirport->latitude,
                    'longitude' => (float) $flight->destinationAirport->longitude,
                ],
            ]),

            'availableCities' => $availableCities,
        ]);
    }
}