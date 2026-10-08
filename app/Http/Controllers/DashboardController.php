<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Trip;
use App\Models\Visit;
use App\Models\Country;
use App\Models\Photo;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();

        /*
        |--------------------------------------------------------------------------
        | Trips
        |--------------------------------------------------------------------------
        */

        $trips = Trip::query()
            ->where('user_id', $user->id)
            ->with([
                'visits.city.country',
            ])
            ->orderByDesc('start_date')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Visits
        |--------------------------------------------------------------------------
        */

        $visits = Visit::query()
            ->whereHas('trip', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'city.country',
            ])
            ->orderBy('visited_from')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Flights
        |--------------------------------------------------------------------------
        */

        $flights = Flight::query()
            ->where('user_id', $user->id)
            ->with([
                'originAirport.city',
                'destinationAirport.city',
            ])
            ->orderBy('departure_at')
            ->get();

        $recentFlights = $flights
            ->sortByDesc('departure_at')
            ->take(3)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Today / next trip
        |--------------------------------------------------------------------------
        */

        $today = Carbon::today();

        $nextTrip = $trips
            ->filter(function ($trip) use ($today) {
                return $trip->start_date
                    && Carbon::parse($trip->start_date)->greaterThanOrEqualTo($today);
            })
            ->sortBy('start_date')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Visited cities
        |--------------------------------------------------------------------------
        */

        $visitedCities = $visits
            ->unique('city_id')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Visited countries
        |--------------------------------------------------------------------------
        */

        $countries = $visits
            ->filter(fn ($visit) => $visit->city?->country)
            ->unique(fn ($visit) => $visit->city->country->id)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Countries for CountryMap
        |--------------------------------------------------------------------------
        |
        | CountryMap uses the ISO code to paint world.json.
        |
        */

        $countriesForMap = $countries
            ->map(fn ($visit) => [
                'id' => $visit->city->country->id,
                'name' => $visit->city->country->name,
                'iso_code' => $visit->city->country->iso_code,
            ])
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Recent trips
        |--------------------------------------------------------------------------
        */

        $recentTrips = $trips
            ->take(4)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Photos
        |--------------------------------------------------------------------------
        */

        $recentPhotos = Photo::query()
            ->where('user_id', $user->id)
            ->where('processing_status', 'ready')
            ->latest()
            ->take(7)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Trips by year
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | Countries percentage
        |--------------------------------------------------------------------------
        */

        $totalCountries = Country::query()->count();

        $visitedCountryCount = $countries->count();

        $remainingCountryCount = max(
            0,
            $totalCountries - $visitedCountryCount
        );

        $tripsByYear = $trips
            ->filter(fn ($trip) => $trip->start_date)
            ->groupBy(fn ($trip) => Carbon::parse($trip->start_date)->year)
            ->map(fn ($yearTrips, $year) => [
                'year' => (int) $year,
                'count' => $yearTrips->count(),
            ])
            ->sortBy('year')
            ->values();

        return Inertia::render('Home', [
            'user' => [
                'name' => $user->name,
            ],

            'tripsByYear' => $tripsByYear,

            'recentPhotos' => $recentPhotos->map(fn ($photo) => [
                'id' => $photo->id,
                'path' => $photo->path,
                'thumbnail_path' => $photo->thumbnail_path,
                'original_filename' => $photo->original_filename,
            ])->values(),

            /*
            |--------------------------------------------------------------------------
            | Stats
            |--------------------------------------------------------------------------
            */

            'stats' => [
                'countries' => $visitedCountryCount,
                'cities' => $visitedCities->count(),
                'trips' => $trips->count(),
                'flights' => $flights->count(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Country map
            |--------------------------------------------------------------------------
            */

            'countries' => $countriesForMap,

            /*
            |--------------------------------------------------------------------------
            | Next trip
            |--------------------------------------------------------------------------
            */

            'nextTrip' => $nextTrip
                ? [
                    'id' => $nextTrip->id,
                    'name' => $nextTrip->name,
                    'description' => $nextTrip->description,
                    'start_date' => $nextTrip->start_date?->format('Y-m-d'),
                    'end_date' => $nextTrip->end_date?->format('Y-m-d'),
                    'days_until' => max(
                        0,
                        $today->diffInDays(
                            Carbon::parse($nextTrip->start_date),
                            false
                        )
                    ),
                ]
                : null,

            /*
            |--------------------------------------------------------------------------
            | Maps
            |--------------------------------------------------------------------------
            */

            'map' => [

                /*
                |--------------------------------------------------------------------------
                | Visits
                |--------------------------------------------------------------------------
                |
                | Kept because the dashboard still has access to visited cities,
                | but CountryMap is responsible for the world map.
                |
                */

                'visits' => $visits->map(function ($visit) {
                    return [
                        'id' => $visit->id,

                        'city' => [
                            'id' => $visit->city->id,
                            'name' => $visit->city->name,
                            'country' => $visit->city->country->name,
                            'country_id' => $visit->city->country->id,
                            'iso_code' => $visit->city->country->iso_code,
                            'latitude' => $visit->city->latitude,
                            'longitude' => $visit->city->longitude,
                        ],

                        'visited_from' => $visit->visited_from,
                        'visited_until' => $visit->visited_until,
                        'notes' => $visit->notes,
                    ];
                })->values(),

                /*
                |--------------------------------------------------------------------------
                | Flights
                |--------------------------------------------------------------------------
                |
                | Only flight data is sent to TravelMap.
                |
                */

                'flights' => $flights->map(function ($flight) {
                    return [
                        'id' => $flight->id,
                        'flight_number' => $flight->flight_number,
                        'airline' => $flight->airline,
                        'departure' => $flight->departure_at?->toISOString(),
                        'arrival' => $flight->arrival_at?->toISOString(),

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
                    ];
                })->values(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Recent flights
            |--------------------------------------------------------------------------
            */

            'recentFlights' => $recentFlights->map(function ($flight) {
                return [
                    'id' => $flight->id,
                    'flight_number' => $flight->flight_number,
                    'airline' => $flight->airline,
                    'departure' => $flight->departure_at?->toISOString(),
                    'arrival' => $flight->arrival_at?->toISOString(),

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
                ];
            })->values(),

            /*
            |--------------------------------------------------------------------------
            | Recent trips
            |--------------------------------------------------------------------------
            */

            'recentTrips' => $recentTrips->map(function ($trip) {
                $tripVisits = $trip->visits;

                return [
                    'id' => $trip->id,
                    'name' => $trip->name,
                    'description' => $trip->description,
                    'start_date' => $trip->start_date?->format('Y-m-d'),
                    'end_date' => $trip->end_date?->format('Y-m-d'),

                    'cities' => $tripVisits
                        ->unique('city_id')
                        ->map(fn ($visit) => $visit->city->name)
                        ->values()
                        ->all(),

                    'countries' => $tripVisits
                        ->filter(fn ($visit) => $visit->city?->country)
                        ->unique(fn ($visit) => $visit->city->country->id)
                        ->map(fn ($visit) => $visit->city->country->name)
                        ->values()
                        ->all(),
                ];
            })->values(),
        ]);
    }
}