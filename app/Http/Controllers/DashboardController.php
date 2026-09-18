<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Visit;
use App\Models\Flight;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;

        $stats = [
            'countries' => Visit::query()
                ->where('user_id', $userId)
                ->join('cities', 'visits.city_id', '=', 'cities.id')
                ->distinct('cities.country_id')
                ->count('cities.country_id'),

            'cities' => Visit::query()
                ->where('user_id', $userId)
                ->distinct('city_id')
                ->count('city_id'),

            'trips' => Trip::query()
                ->where('user_id', $userId)
                ->count(),

            'flights' => Flight::query()
                ->where('user_id', $userId)
                ->count(),
        ];

        $trips = Trip::query()
            ->where('user_id', $userId)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Trip $trip) => [
                'id' => $trip->id,
                'name' => $trip->name,
                'description' => $trip->description,
                'start_date' => $trip->start_date->format('Y-m-d'),
                'end_date' => $trip->end_date->format('Y-m-d'),
            ]);

        return Inertia::render('Home', [
            'stats' => $stats,
            'trips' => $trips,
        ]);
    }
}