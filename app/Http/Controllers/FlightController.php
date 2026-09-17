<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFlightRequest;
use App\Models\Flight;
use Illuminate\Http\JsonResponse;

class FlightController extends Controller
{
    public function store(StoreFlightRequest $request): JsonResponse
    {
        $flight = Flight::create([
            'user_id' => $request->user()->id,
            'trip_id' => $request->validated('trip_id'),
            'origin_airport_id' => $request->validated('origin_airport_id'),
            'destination_airport_id' => $request->validated('destination_airport_id'),
            'flight_number' => $request->validated('flight_number'),
            'departure_at' => $request->validated('departure_at'),
            'arrival_at' => $request->validated('arrival_at'),
            'airline' => $request->validated('airline'),
        ]);

        return response()->json($flight, 201);
    }
}