<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Trip;
use App\Models\Visit;
use App\Support\SovereignCountries;
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

        // Solo las visitas de viajes completados cuentan como lugares visitados.
        $visits = Visit::query()
            ->visitedBy($user->id)
            ->with([
                'city.country',
            ])
            ->orderBy('visited_from')
            ->get();

        // Destinos planificados: se pintan aparte (lima) y no cuentan.
        $plannedVisits = Visit::query()
            ->plannedBy($user->id)
            ->with(['city.country', 'trip'])
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Flights
        |--------------------------------------------------------------------------
        */

        $now = now();
        $todayDate = Trip::today();

        $flights = Flight::withStatuses(
            Flight::query()
                ->where('user_id', $user->id)
                ->with([
                    'trip',
                    'originAirport.city',
                    'destinationAirport.city',
                ])
                ->chronological()
                ->get(),
            $now,
            $todayDate,
        )
            ->filter(fn (Flight $flight) => $flight->flight_status !== Flight::CANCELLED)
            ->values();

        // Solo los vuelos realizados cuentan en las cifras y en "recientes".
        $flownFlights = $flights
            ->where('flight_status', Flight::FLOWN)
            ->values();

        // El orden cronológico ya viene de la consulta: los últimos, al final.
        $recentFlights = $flownFlights
            ->reverse()
            ->take(3)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Today / next trip
        |--------------------------------------------------------------------------
        */

        // "Hoy" es el día del calendario en la zona horaria del usuario, no en UTC:
        // a las 00:30 en Madrid ya es el día siguiente aunque en UTC no lo sea.
        $today = Carbon::parse(
            Carbon::now(config('app.user_timezone'))->toDateString()
        );

        // Próximo viaje: planificado y que aún no ha empezado (o empieza hoy).
        $nextTrip = $trips
            ->filter(fn ($trip) => $trip->status === Trip::PLANNED
                && $trip->start_date
                && $trip->start_date->toDateString() >= $today->toDateString())
            ->sortBy('start_date')
            ->first();

        // Viajes planificados cuya fecha de fin ya pasó: el usuario debe confirmarlos.
        $pendingTrips = $trips
            ->filter(fn ($trip) => $trip->phase($todayDate) === 'pending')
            ->sortBy('end_date')
            ->values();

        $completedTrips = $trips
            ->where('status', Trip::COMPLETED)
            ->values();

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

        // Solo viajes ya empezados (terminados o en curso), con el mismo "hoy"
        // que nextTrip. Las demás cifras siguen usando todos los viajes.
        $recentTrips = $trips
            ->filter(fn ($trip) => $trip->status !== Trip::CANCELLED
                && $trip->start_date
                && $trip->start_date->toDateString() <= $today->toDateString())
            ->take(4)
            ->values();

        // Portada: primera foto lista y propia por id, como en TripController,
        // ProfileController y CountryController. Solo para estos viajes.
        $recentTrips->load([
            'photos' => function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->where('processing_status', 'ready')
                    ->orderBy('id');
            },
        ]);

        /*
        |--------------------------------------------------------------------------
        | Photos
        |--------------------------------------------------------------------------
        */

        // Las fotos de viajes cancelados no salen aquí (no se borran ni se
        // desasocian); las de viajes planificados y las sin viaje, sí.
        $recentPhotos = Photo::query()
            ->where('user_id', $user->id)
            ->where('processing_status', 'ready')
            ->where(fn ($query) => $query
                ->whereNull('trip_id')
                ->orWhereHas('trip', fn ($trip) => $trip->where('status', '!=', Trip::CANCELLED)))
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

        // El porcentaje solo considera Estados soberanos (config/countries.php).
        // El contador de países visitados sigue incluyendo territorios.
        $visitedCountryCount = $countries->count();

        $visitedSovereignCount = SovereignCountries::countVisited(
            $countries->map(fn ($visit) => $visit->city->country->iso_code)
        );

        // Historial: solo viajes completados.
        $tripsByYear = $completedTrips
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
                'url' => $photo->fileUrl(),
                'thumbnail_url' => $photo->thumbnailUrl(),
                'original_filename' => $photo->original_filename,
            ])->values(),

            /*
            |--------------------------------------------------------------------------
            | Stats
            |--------------------------------------------------------------------------
            */

            'stats' => [
                // countries: todos los códigos visitados (incluye territorios).
                // Las cifras generales usan sovereign_countries (sobre 193) y
                // territories por separado.
                'countries' => $visitedCountryCount,
                'sovereign_countries' => $visitedSovereignCount,
                'territories' => SovereignCountries::countVisitedTerritories(
                    $countries->map(fn ($visit) => $visit->city->country->iso_code)
                ),
                'total_sovereign_countries' => SovereignCountries::total(),
                'cities' => $visitedCities->count(),
                'trips' => $completedTrips->count(),
                'upcoming_trips' => $trips->filter(fn ($trip) => $trip->phase($todayDate) === 'upcoming')->count(),
                'flights' => $flownFlights->count(),
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
                        (int) $today->diffInDays(
                            Carbon::parse($nextTrip->start_date->toDateString()),
                            false
                        )
                    ),
                ]
                : null,

            // Viajes planificados cuya fecha de fin ya pasó y esperan confirmación.
            'pendingTrips' => $pendingTrips->map(fn ($trip) => [
                'id' => $trip->id,
                'name' => $trip->name,
                'end_date' => $trip->end_date?->format('Y-m-d'),
            ])->values(),

            /*
            |--------------------------------------------------------------------------
            | Maps
            |--------------------------------------------------------------------------
            */

            'map' => [

                // Países con destinos planificados (no cuentan como visitados).
                // ongoing: algún viaje de ese país está en curso hoy.
                'planned_countries' => $plannedVisits
                    ->filter(fn ($visit) => $visit->city?->country)
                    ->groupBy(fn ($visit) => $visit->city->country->iso_code)
                    ->map(fn ($countryVisits, $iso) => [
                        'iso_code' => $iso,
                        'name' => $countryVisits->first()->city->country->name,
                        'ongoing' => $countryVisits->contains(fn ($visit) => $visit->trip->phase($todayDate) === 'ongoing'),
                    ])
                    ->sortBy('name')
                    ->values(),

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
                        'trip_id' => $visit->trip_id,

                        'city' => [
                            'id' => $visit->city->id,
                            'name' => $visit->city->name,
                            'country' => $visit->city->country->name,
                            'country_id' => $visit->city->country->id,
                            'iso_code' => $visit->city->country->iso_code,
                            'country_status' => SovereignCountries::classify($visit->city->country->iso_code),
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

                // Vuelos realizados y próximos (status). Ni cancelados ni pendientes
                // de confirmar: estos no se dibujan como si se hubieran realizado.
                'flights' => $flights->where('flight_status', '!=', Flight::PENDING)->values()->map(function ($flight) {
                    return [
                        'id' => $flight->id,
                        'status' => $flight->flight_status,
                        'flight_number' => $flight->flight_number,
                        'airline' => $flight->airline,
                        'departure_date' => $flight->departure_date?->format('Y-m-d'),
                        'departure' => $flight->departure_at?->toISOString(),
                        'arrival' => $flight->arrival_at?->toISOString(),

                        'origin' => [
                            'id' => $flight->originAirport->id,
                            'name' => $flight->originAirport->name,
                            'city' => $flight->originAirport->city->name,
                            'latitude' => (float) $flight->originAirport->latitude,
                            'longitude' => (float) $flight->originAirport->longitude,
                            'timezone' => $flight->originAirport->timezone,
                        ],

                        'destination' => [
                            'id' => $flight->destinationAirport->id,
                            'name' => $flight->destinationAirport->name,
                            'city' => $flight->destinationAirport->city->name,
                            'latitude' => (float) $flight->destinationAirport->latitude,
                            'longitude' => (float) $flight->destinationAirport->longitude,
                            'timezone' => $flight->destinationAirport->timezone,
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
                    'departure_date' => $flight->departure_date?->format('Y-m-d'),
                    'departure' => $flight->departure_at?->toISOString(),
                    'arrival' => $flight->arrival_at?->toISOString(),

                    'origin' => [
                        'id' => $flight->originAirport->id,
                        'name' => $flight->originAirport->name,
                        'city' => $flight->originAirport->city->name,
                        'latitude' => (float) $flight->originAirport->latitude,
                        'longitude' => (float) $flight->originAirport->longitude,
                        'timezone' => $flight->originAirport->timezone,
                    ],

                    'destination' => [
                        'id' => $flight->destinationAirport->id,
                        'name' => $flight->destinationAirport->name,
                        'city' => $flight->destinationAirport->city->name,
                        'latitude' => (float) $flight->destinationAirport->latitude,
                        'longitude' => (float) $flight->destinationAirport->longitude,
                        'timezone' => $flight->destinationAirport->timezone,
                    ],
                ];
            })->values(),

            /*
            |--------------------------------------------------------------------------
            | Recent trips
            |--------------------------------------------------------------------------
            */

            'recentTrips' => $recentTrips->map(function ($trip) use ($todayDate) {
                $tripVisits = $trip->visits;

                return [
                    'id' => $trip->id,
                    // completed, ongoing o pending (los próximos y cancelados no llegan aquí).
                    'phase' => $trip->phase($todayDate),
                    'name' => $trip->name,
                    'description' => $trip->description,
                    'start_date' => $trip->start_date?->format('Y-m-d'),
                    'end_date' => $trip->end_date?->format('Y-m-d'),
                    'cover' => $trip->photos->first()?->thumbnailUrl(),

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