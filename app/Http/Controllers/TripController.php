<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\GoogleMapList;
use App\Models\Trip;
use App\Services\PhotoFileManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function edit(Request $request, Trip $trip): Response
    {
        $this->authorize('update', $trip);

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

    public function destroy(Request $request, Trip $trip, PhotoFileManager $files)
    {
        $this->authorize('delete', $trip);

        $photoIds = $trip->photos()->pluck('id');

        // Las fotos, visitas y vuelos se borran en cascada en la base de datos.
        // Los archivos se eliminan después: si el almacenamiento falla, quedan
        // huérfanos, pero ningún registro apunta a archivos inexistentes.
        DB::transaction(fn () => $trip->delete());

        foreach ($photoIds as $photoId) {
            $files->deleteFiles($photoId);
        }

        return redirect()->route('trips.index');
    }

    public function show(Request $request, Trip $trip): Response
    {
        $this->authorize('view', $trip);

        // Orden cronológico (y por id ante empates) para que la lista y los mapas
        // no dependan del orden en que se crearon los registros.
        $trip->load([
            'visits' => fn ($query) => $query->orderBy('visited_from')->orderBy('visited_until')->orderBy('id'),
            'visits.city.country',
            'flights' => fn ($query) => $query->orderBy('departure_at')->orderBy('id'),
            'flights.originAirport.city',
            'flights.destinationAirport.city',
            'journal',
            'photos' => fn ($query) => $query->where('processing_status', 'ready'),
            'googleMapLists',
        ]);

        return Inertia::render('Trips/Show', [
            'trip' => [
                'id' => $trip->id,
                'name' => $trip->name,
                'description' => $trip->description,
                'start_date' => $trip->start_date?->format('Y-m-d'),
                'end_date' => $trip->end_date?->format('Y-m-d'),
            ],

            'visits' => $trip->visits->map(fn ($visit) => [
                'id' => $visit->id,
                'city' => [
                    'id' => $visit->city->id,
                    'name' => $visit->city->name,
                    'country' => $visit->city->country->name,
                    'iso_code' => $visit->city->country->iso_code,
                    'latitude' => $visit->city->latitude,
                    'longitude' => $visit->city->longitude,
                ],
                'visited_from' => $visit->visited_from?->format('Y-m-d'),
                'visited_until' => $visit->visited_until?->format('Y-m-d'),
                'notes' => $visit->notes,
            ]),

            'flights' => $trip->flights->map(fn ($flight) => [
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
            ]),

            'photos' => $trip->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'path' => $photo->path,
                'thumbnail_path' => $photo->thumbnail_path,
                'original_filename' => $photo->original_filename,
                'width' => $photo->width,
                'height' => $photo->height,
                'taken_at' => $photo->taken_at?->toISOString(),
            ]),

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

    public function photos(Trip $trip): Response
    {
        $this->authorize('view', $trip);

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
                    'path' => $photo->path,
                    'thumbnail_path' => $photo->thumbnail_path,
                    'original_filename' => $photo->original_filename,
                    'width' => $photo->width,
                    'height' => $photo->height,
                    'taken_at' => $photo->taken_at?->toISOString(),
                ])
                ->values(),
        ]);
    }

    public function index(Request $request): Response
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

        return Inertia::render('Trips/Index', [
            'trips' => $trips->map(function ($trip) {
                return [
                    'id' => $trip->id,
                    'name' => $trip->name,
                    'description' => $trip->description,
                    'start_date' => $trip->start_date?->format('Y-m-d'),
                    'end_date' => $trip->end_date?->format('Y-m-d'),

                    'cover' => $trip->photos->first()?->thumbnail_path
                        ?? $trip->photos->first()?->path,

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
                        ])
                        ->values()
                        ->all(),
                ];
            }),
        ]);
    }
}