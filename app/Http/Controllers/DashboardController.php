<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\Visit;
use App\Models\Flight;
use App\Models\Airport;
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
        
        $countries = Visit::query()
            ->where('user_id', $userId)
            ->with('city.country')
            ->get()
            ->map(fn (Visit $visit) => [
                'id' => $visit->city->country->id,
                'name' => $visit->city->country->name,
                'iso_code' => $visit->city->country->iso_code,
            ])
            ->unique('id')
            ->values();

        $cities = Visit::query()
            ->where('user_id', $userId)
            ->with('city')
            ->get()
            ->map(fn (Visit $visit) => [
                'id' => $visit->city->id,
                'name' => $visit->city->name,
                'latitude' => (float) $visit->city->latitude,
                'longitude' => (float) $visit->city->longitude,
            ])
            ->unique('id')
            ->values();

        $flights = Flight::query()
            ->where('user_id', $userId)
            ->with(['originAirport', 'destinationAirport'])
            ->get()
            ->map(fn (Flight $flight) => [
                'id' => $flight->id,
                'origin' => [
                    'name' => $flight->originAirport->name,
                    'latitude' => (float) $flight->originAirport->latitude,
                    'longitude' => (float) $flight->originAirport->longitude,
                ],
                'destination' => [
                    'name' => $flight->destinationAirport->name,
                    'latitude' => (float) $flight->destinationAirport->latitude,
                    'longitude' => (float) $flight->destinationAirport->longitude,
                ],
            ]);

        return Inertia::render('Home', [
            'stats' => $stats,
            'trips' => $trips,
            'cities' => $cities,
            'flights' => $flights,
            'countries' => $countries,

        ]);
    }
}