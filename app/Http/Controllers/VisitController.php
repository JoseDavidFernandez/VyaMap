<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisitRequest;
use App\Http\Requests\UpdateVisitRequest;
use App\Models\Trip;
use App\Models\Visit;
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

    public function update(
        UpdateVisitRequest $request,
        Trip $trip,
        Visit $visit
    ): RedirectResponse {
        $this->authorize('view', $trip);

        $visit->update($request->validated());

        return redirect()->route('trips.show', $trip);
    }
}