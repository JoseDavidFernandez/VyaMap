<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;

class VisitController extends Controller
{
    public function store(StoreVisitRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('view', $trip);

        $trip->visits()->create([
            'user_id' => $request->user()->id,
            'city_id' => $request->validated('city_id'),
            'visited_from' => $request->validated('visited_from'),
            'visited_until' => $request->validated('visited_until'),
            'notes' => $request->validated('notes'),
        ]);

        return redirect()->route('trips.show', $trip);
    }
}