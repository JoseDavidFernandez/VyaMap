<?php

namespace App\Http\Controllers;

use App\Models\GoogleMapList;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleMapListController extends Controller
{
    public function store(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $list = GoogleMapList::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'url' => $validated['url'],
        ]);

        $trip->googleMapLists()->syncWithoutDetaching([
            $list->id,
        ]);

        return back();
    }

    public function attach(Request $request, Trip $trip, GoogleMapList $googleMapList): RedirectResponse
    {
        $this->authorize('view', $trip);

        abort_unless(
            $googleMapList->user_id === $request->user()->id,
            403
        );

        $trip->googleMapLists()->syncWithoutDetaching([
            $googleMapList->id,
        ]);

        return back();
    }

    public function detach(Request $request, Trip $trip, GoogleMapList $googleMapList): RedirectResponse
    {
        $this->authorize('view', $trip);

        abort_unless(
            $googleMapList->user_id === $request->user()->id,
            403
        );

        $trip->googleMapLists()->detach($googleMapList->id);

        return back();
    }
}