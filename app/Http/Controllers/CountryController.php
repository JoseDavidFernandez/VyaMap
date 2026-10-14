<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $countries = Country::query()
            ->whereHas('cities.visits', function ($query) use ($user) {
                $query->whereHas('trip', function ($tripQuery) use ($user) {
                    $tripQuery->where('user_id', $user->id);
                });
            })
            ->withCount([
                'cities as visited_cities_count' => function ($query) use ($user) {
                    $query->whereHas('visits', function ($visitQuery) use ($user) {
                        $visitQuery->whereHas('trip', function ($tripQuery) use ($user) {
                            $tripQuery->where('user_id', $user->id);
                        });
                    });
                },
            ])
            ->orderBy('name')
            ->get();

        // Viajes distintos por país en una sola consulta (antes, una por país).
        $tripsPerCountry = DB::table('trips')
            ->join('visits', 'visits.trip_id', '=', 'trips.id')
            ->join('cities', 'cities.id', '=', 'visits.city_id')
            ->where('trips.user_id', $user->id)
            ->groupBy('cities.country_id')
            ->selectRaw('cities.country_id, COUNT(DISTINCT trips.id) AS trips_count')
            ->pluck('trips_count', 'country_id');

        $countries = $countries->map(fn ($country) => [
            'id' => $country->id,
            'name' => $country->name,
            'iso_code' => $country->iso_code,
            'cities_count' => $country->visited_cities_count,
            'trips_count' => (int) ($tripsPerCountry[$country->id] ?? 0),
        ]);

        return Inertia::render('Countries/Index', [
            'countries' => $countries->values(),
        ]);
    }

    public function show(Request $request, Country $country): Response
    {
        $user = $request->user();

        $cities = $country->cities()
            ->whereHas('visits', function ($query) use ($user) {
                $query->whereHas('trip', function ($tripQuery) use ($user) {
                    $tripQuery->where('user_id', $user->id);
                });
            })
            ->withCount([
                'visits as user_visits_count' => function ($query) use ($user) {
                    $query->whereHas('trip', function ($tripQuery) use ($user) {
                        $tripQuery->where('user_id', $user->id);
                    });
                },
            ])
            ->orderBy('name')
            ->get();

        $trips = Trip::query()
            ->where('user_id', $user->id)
            ->whereHas('visits.city', function ($query) use ($country) {
                $query->where('country_id', $country->id);
            })
            ->with([
                'photos' => function ($query) {
                    $query
                        ->where('processing_status', 'ready')
                        ->orderBy('id');
                },
                'visits.city',
            ])
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($trip) use ($country) {
                $cities = $trip->visits
                    ->filter(fn ($visit) => $visit->city?->country_id === $country->id)
                    ->map(fn ($visit) => $visit->city?->name)
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $trip->id,
                    'name' => $trip->name,
                    'description' => $trip->description,
                    'start_date' => $trip->start_date?->format('Y-m-d'),
                    'end_date' => $trip->end_date?->format('Y-m-d'),
                    'cover' => $trip->photos->first()?->thumbnailUrl(),
                    'cities' => $cities,
                ];
            })
            ->values();

        return Inertia::render('Countries/Show', [
            'country' => [
                'id' => $country->id,
                'name' => $country->name,
                'iso_code' => $country->iso_code,
            ],
            'cities' => $cities->map(fn ($city) => [
                'id' => $city->id,
                'name' => $city->name,
                'visits_count' => $city->user_visits_count,
            ])->values(),
            'trips' => $trips,
        ]);
    }
}