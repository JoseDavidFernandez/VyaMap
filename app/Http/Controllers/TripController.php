<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;

class TripController extends Controller
{
    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = Trip::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
        ]);

        return response()->json($trip, 201);
    }
}