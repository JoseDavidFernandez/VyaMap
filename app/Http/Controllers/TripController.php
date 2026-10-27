<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Requests\UpdateTripStatusRequest;
use App\Models\Flight;
use App\Models\GoogleMapList;
use App\Models\Trip;
use App\Services\PhotoFileManager;
use App\Services\TravelStatistics;
use App\Support\FlightPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\SovereignCountries;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function store(StoreTripRequest $request)
    {
        $trip = Trip::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
        ]);

        return redirect()->route('trips.show', $trip);
    }

    public function edit(Request $request, Trip $trip): Response|RedirectResponse
    {
        $this->authorize('update', $trip);

        if ($redirect = $this->canonicalRedirect($request, 'trip', $trip)) {
            return $redirect;
        }

        return Inertia::render('Trips/Edit', [
            'trip' => [
                'id' => $trip->id,
                'name' => $trip->name,
                'description' => $trip->description,
                'start_date' => $trip->start_date?->format('Y-m-d'),
                'end_date' => $trip->end_date?->format('Y-m-d'),
            ],
        ]);
    }

    public function update(
        UpdateTripRequest $request,
        Trip $trip
    ) {
        $trip->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
        ]);

        return redirect()->route('trips.show', $trip);
    }

    /**
     * Antes de borrar un viaje, sus vuelos pasan a ser independientes. Los que
     * ya contaban como realizados (viaje no cancelado) quedan confirmados para
     * no volver a "pendientes"; los de un viaje cancelado no se confirman.
     */
    private function keepFlightsAsStandalone(Trip $trip): void
    {
        if ($trip->status !== Trip::CANCELLED) {
            $now = now();
            $today = Trip::today();

            $trip->flights()
                ->with('originAirport')
                ->whereNull('confirmed_at')
                ->get()
                ->each(fn (Flight $flight) => $flight->setRelation('trip', $trip))
                ->filter(fn (Flight $flight) => $flight->status($now, $today) === Flight::FLOWN)
                ->each(fn (Flight $flight) => $flight->update(['confirmed_at' => $now]));
        }

        $trip->flights()->update(['trip_id' => null]);
    }

    public function updateStatus(UpdateTripStatusRequest $request, Trip $trip)
    {
        $trip->update(['status' => $request->validated('status')]);

        return back();
    }

    public function destroy(Request $request, Trip $trip, PhotoFileManager $files)
    {
        $this->authorize('delete', $trip);

        $photoIds = $trip->photos()->pluck('id');

        // Las fotos y visitas se borran en cascada en la base de datos; los
        // vuelos se conservan como independientes (D2). Los archivos se
        // eliminan después: si el almacenamiento falla, quedan huérfanos, pero
        // ningún registro apunta a archivos inexistentes.
        DB::transaction(function () use ($trip) {
            $this->keepFlightsAsStandalone($trip);

            $trip->delete();
        });

        foreach ($photoIds as $photoId) {
            $files->deleteFiles($photoId);
        }

        return redirect()->route('trips.index');
    }

    public function show(Request $request, Trip $trip): Response|RedirectResponse
    {
        $this->authorize('view', $trip);

        if ($redirect = $this->canonicalRedirect($request, 'trip', $trip)) {
            return $redirect;
        }

        // Orden cronológico (y por id ante empates) para que la lista y los mapas
        // no dependan del orden en que se crearon los registros.
        $trip->load([
            'visits' => fn ($query) => $query->orderBy('visited_from')->orderBy('visited_until')->orderBy('id'),
            'visits.city.country',
            'flights' => fn ($query) => $query->chronological(),
            'flights.originAirport.city',
            'flights.destinationAirport.city',
            'journal',
            'photos' => fn ($query) => $query->where('processing_status', 'ready'),
            'googleMapLists',
        ]);

        $today = Trip::today();
        $now = now();

        return Inertia::render('Trips/Show', [
            'trip' => [
                'id' => $trip->id,
                'name' => $trip->name,
                'description' => $trip->description,
                'start_date' => $trip->start_date?->format('Y-m-d'),
                'end_date' => $trip->end_date?->format('Y-m-d'),
                'status' => $trip->status,
                // upcoming, ongoing, pending, completed o cancelled.
                'phase' => $trip->phase($today),
                // Solo un viaje planificado que ya empezó (nunca uno cancelado).
                'can_complete' => $trip->status === Trip::PLANNED && $trip->hasStarted($today),
            ],

            'visits' => $trip->visits->map(fn ($visit) => [
                'id' => $visit->id,
                'city' => [
                    'id' => $visit->city->id,
                    'name' => $visit->city->name,
                    'country' => $visit->city->country->name,
                    'iso_code' => $visit->city->country->iso_code,
                    'country_status' => SovereignCountries::classify($visit->city->country->iso_code),
                    'latitude' => $visit->city->latitude,
                    'longitude' => $visit->city->longitude,
                ],
                'visited_from' => $visit->visited_from?->format('Y-m-d'),
                'visited_until' => $visit->visited_until?->format('Y-m-d'),
                'notes' => $visit->notes,
            ]),

            // Misma forma y mismo estado que en Flight History (FlightPresenter).
            'flights' => Flight::withStatuses(
                $trip->flights->each(fn (Flight $flight) => $flight->setRelation('trip', $trip)),
                $now,
                $today,
            )->map(fn (Flight $flight) => FlightPresenter::toArray($flight))->values(),

            'photos' => $trip->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->fileUrl(),
                'thumbnail_url' => $photo->thumbnailUrl(),
                'original_filename' => $photo->original_filename,
                'width' => $photo->width,
                'height' => $photo->height,
                'taken_at' => $photo->taken_at?->toISOString(),
            ]),

            // Fotos subidas que la cola aún no ha procesado (no se listan arriba).
            'processing_photos_count' => $trip->photos()
                ->whereIn('processing_status', ['pending', 'processing'])
                ->count(),

            'google_map_lists' => $trip->googleMapLists->map(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'url' => $list->url,
            ]),

            'available_google_map_lists' => GoogleMapList::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name', 'url']),
        ]);
    }

    public function photos(Request $request, Trip $trip): Response|RedirectResponse
    {
        $this->authorize('view', $trip);

        if ($redirect = $this->canonicalRedirect($request, 'trip', $trip)) {
            return $redirect;
        }

        $trip->load([
            'photos',
        ]);

        return Inertia::render('Trips/Photos', [
            'trip' => [
                'id' => $trip->id,
                'name' => $trip->name,
                'start_date' => $trip->start_date?->format('Y-m-d'),
                'end_date' => $trip->end_date?->format('Y-m-d'),
            ],

            'photos' => $trip->photos
                ->where('processing_status', 'ready')
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => $photo->fileUrl(),
                    'thumbnail_url' => $photo->thumbnailUrl(),
                    'original_filename' => $photo->original_filename,
                    'width' => $photo->width,
                    'height' => $photo->height,
                    'taken_at' => $photo->taken_at?->toISOString(),
                ])
                ->values(),
        ]);
    }

    public function index(Request $request, TravelStatistics $statistics): Response
    {
        $trips = Trip::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'visits.city.country',
                'photos' => function ($query) {
                    $query
                        ->where('processing_status', 'ready')
                        ->orderBy('id');
                },
            ])
            ->orderByDesc('start_date')
            ->get();

        $today = Trip::today();

        // Cifras de la cabecera: solo viajes completados y sus visitas (ya cargadas).
        $completed = $trips->where('status', Trip::COMPLETED)->values();
        $stats = $statistics->summarisePlaces($completed, $completed->flatMap->visits->values());

        return Inertia::render('Trips/Index', [
            'stats' => [
                'total' => $stats['total'],
                'by_year' => $stats['by_year'],
            ],

            'trips' => $trips->map(function ($trip) use ($today) {
                return [
                    'id' => $trip->id,
                    'status' => $trip->status,
                    'phase' => $trip->phase($today),
                    'name' => $trip->name,
                    'description' => $trip->description,
                    'start_date' => $trip->start_date?->format('Y-m-d'),
                    'end_date' => $trip->end_date?->format('Y-m-d'),

                    'cover' => $trip->photos->first()?->thumbnailUrl(),

                    // Ciudades distintas por identidad (city_id), no por nombre.
                    'city_ids' => $trip->visits
                        ->pluck('city_id')
                        ->unique()
                        ->values()
                        ->all(),
                    'cities' => $trip->visits
                        ->map(fn ($visit) => $visit->city?->name)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),

                    'countries' => $trip->visits
                        ->map(fn ($visit) => $visit->city?->country)
                        ->filter()
                        ->unique('iso_code')
                        ->values()
                        ->map(fn ($country) => [
                            'name' => $country->name,
                            'iso_code' => $country->iso_code,
                            'country_status' => SovereignCountries::classify($country->iso_code),
                        ])
                        ->values()
                        ->all(),
                ];
            }),
        ]);
    }
}