<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;

class VisitController extends Controller
{
    public function store(StoreVisitRequest $request): JsonResponse
    {
        $visit = Visit::create([
            'user_id' => $request->user()->id,
            'trip_id' => $request->validated('trip_id'),
            'city_id' => $request->validated('city_id'),
            'visited_from' => $request->validated('visited_from'),
            'visited_until' => $request->validated('visited_until'),
            'notes' => $request->validated('notes'),
        ]);

        return response()->json($visit, 201);
    }
}