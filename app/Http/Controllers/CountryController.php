<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Trip;
use Illuminate\Http\Request;
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
            ->with([
                'cities' => function ($query) use ($user) {
                    $query
                        ->whereHas('visits', function ($visitQuery) use ($user) {
                            $visitQuery->whereHas('trip', function ($tripQuery) use ($user) {
                                $tripQuery->where('user_id', $user->id);
                            });
                        })
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        $countries = $countries->map(function ($country) use ($user) {
            $tripCount = Trip::query()
                ->where('user_id', $user->id)
                ->whereHas('visits.city', function ($query) use ($country) {
                    $query->where('country_id', $country->id);
                })
                ->count();

            return [
                'id' => $country->id,
                'name' => $country->name,
                'iso_code' => $country->iso_code,
                'cities_count' => $country->visited_cities_count,
                'trips_count' => $tripCount,
            ];
        });

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
                    'cover' => $trip->photos->first()?->thumbnail_path
                        ?? $trip->photos->first()?->path,
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