<?php

namespace App\Http\Controllers;

use App\Services\TravelStatistics;
use App\Support\FlightPresenter;
use App\Support\SovereignCountries;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\Visit;
use Inertia\Inertia;
use Inertia\Response;

class PassportController extends Controller
{
    public function __invoke(TravelStatistics $statistics): Response
    {
        $user = request()->user();

        // Progreso geográfico: solo viajes completados, visitas de esos viajes
        // y vuelos ya realizados. Lo planificado se muestra aparte en el mapa.
        $trips = Trip::query()
            ->where('user_id', $user->id)
            ->completed()
            ->get([
                'id',
                'name',
                'start_date',
                'end_date',
            ]);

        $visits = Visit::query()
            ->visitedBy($user->id)
            ->with('city.country')
            ->orderByDesc('visited_from')
            ->get();

        // Solo vuelos realizados, con la misma definición que el resto (Flight::status()).
        $flights = Flight::withStatuses(
            Flight::query()
                ->where('user_id', $user->id)
                ->with([
                    'trip',
                    'originAirport.city',
                    'destinationAirport.city',
                ])
                ->chronological('desc')
                ->get()
        )
            ->filter(fn (Flight $flight) => $flight->flight_status === Flight::FLOWN)
            ->values();

        $flightData = $flights->map(fn (Flight $flight) => FlightPresenter::toArray($flight))->values();

        // Cifras del servidor: total y por año natural (viajes y visitas en
        // cada año que tocan; vuelos en el de su fecha local de salida).
        $places = $statistics->summarisePlaces($trips, $visits);

        $flightYears = $flights->groupBy(fn (Flight $flight) => $flight->departure_date->year);

        $years = collect(array_keys($places['by_year']))
            ->merge($flightYears->keys())
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();

        $today = Trip::today();

        $plannedCountries = Visit::query()
            ->plannedBy($user->id)
            ->with(['city.country', 'trip'])
            ->get()
            ->filter(fn ($visit) => $visit->city?->country)
            ->groupBy(fn ($visit) => $visit->city->country->iso_code)
            ->map(fn ($countryVisits, $iso) => [
                'iso_code' => $iso,
                'name' => $countryVisits->first()->city->country->name,
                'ongoing' => $countryVisits->contains(fn ($visit) => $visit->trip->phase($today) === 'ongoing'),
            ])
            ->sortBy('name')
            ->values();

        return Inertia::render('Passport', [
            // Solo Estados soberanos (ver config/countries.php).
            'totalCountries' => SovereignCountries::total(),

            'years' => $years,

            'visits' => $visits->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'city_id' => $visit->city->id,
                    'city' => $visit->city->name,
                    'country' => $visit->city->country->name,
                    'iso_code' => $visit->city->country->iso_code,
                    'is_sovereign' => SovereignCountries::isSovereign($visit->city->country->iso_code),
                    'country_status' => SovereignCountries::classify($visit->city->country->iso_code),
                    'visited_from' => $visit->visited_from,
                    'visited_until' => $visit->visited_until,
                ];
            })->values(),

            'trips' => $trips->map(fn ($trip) => [
                'id' => $trip->id,
                'name' => $trip->name,
                'start_date' => $trip->start_date?->format('Y-m-d'),
                'end_date' => $trip->end_date?->format('Y-m-d'),
            ])->values(),

            'flights' => $flightData,

            'stats' => $places,

            'flightStats' => [
                'all' => $statistics->flights($flights),
                'by_year' => $flightYears->map(fn ($yearFlights) => $statistics->flights($yearFlights))->all(),
            ],

            // Destinos planificados para el mapa (lima); no cuentan en el progreso.
            'plannedCountries' => $plannedCountries,
        ]);
    }
}