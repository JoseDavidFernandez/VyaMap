<?php

namespace App\Services;

use App\Models\Flight;
use App\Models\Trip;
use App\Models\Visit;
use App\Support\SovereignCountries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Estadísticas de viaje (F3). Única fuente de las cifras derivadas: las
 * páginas solo eligen el periodo que muestran.
 *
 * Reglas:
 * - Solo cuentan los viajes completados y las visitas de esos viajes
 *   (Visit::visitedBy). Un vuelo nunca marca un país como visitado.
 * - Días viajados: días naturales, inicio y fin incluidos, como unión de los
 *   intervalos de los viajes completados (los solapes no duplican días).
 *   Un intervalo que cruza de año reparte sus días entre los años naturales.
 * - Un viaje cuenta (y se lista) en el año en que empieza, como hasta ahora;
 *   sus días, en cambio, se reparten por año natural.
 * - Una visita pertenece a cada año natural que toca (sus países cuentan en
 *   cada uno de esos años).
 * - Países (Estados soberanos) y territorios se cuentan por separado, con
 *   App\Support\SovereignCountries; los códigos especiales no cuentan.
 * - Primera visita de un país: el año de su visita fechada más antigua.
 *   Las visitas sin fechas cuentan en el total, pero no en ningún año.
 * - Continentes: config/continents.php. Un código sin continente no suma a
 *   ninguno y se informa aparte.
 * - Vuelos: solo los realizados (Flight::status() = flown). Distancia solo
 *   con coordenadas en ambos aeropuertos; tiempo en el aire solo con las dos
 *   horas (instantes UTC, convertidos con la zona de cada aeropuerto).
 */
class TravelStatistics
{
    /*
    |--------------------------------------------------------------------------
    | Días viajados
    |--------------------------------------------------------------------------
    */

    /**
     * @param  iterable<array{0: ?string, 1: ?string}>  $intervals  [inicio, fin] en Y-m-d
     * @return array{total: int, by_year: array<int, int>, invalid: int}
     */
    public static function travelDays(iterable $intervals): array
    {
        $ranges = [];
        $invalid = 0;

        foreach ($intervals as [$start, $end]) {
            $range = self::dateRange($start, $end);

            if ($range === null) {
                $invalid++;

                continue;
            }

            $ranges[] = $range;
        }

        $byYear = [];
        $total = 0;

        foreach (self::mergeRanges($ranges) as [$start, $end]) {
            for ($year = $start->year; $year <= $end->year; $year++) {
                $from = $start->max(CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'UTC'));
                $until = $end->min(CarbonImmutable::create($year, 12, 31, 0, 0, 0, 'UTC'));
                $days = (int) $from->diffInDays($until) + 1;

                $byYear[$year] = ($byYear[$year] ?? 0) + $days;
                $total += $days;
            }
        }

        krsort($byYear);

        return ['total' => $total, 'by_year' => $byYear, 'invalid' => $invalid];
    }

    /**
     * Años naturales que toca un intervalo (vacío si no es válido).
     *
     * @return list<int>
     */
    public static function yearsOf(?string $start, ?string $end): array
    {
        $range = self::dateRange($start, $end);

        return $range === null ? [] : range($range[0]->year, $range[1]->year);
    }

    /*
    |--------------------------------------------------------------------------
    | Lugares: países, territorios, ciudades y continentes
    |--------------------------------------------------------------------------
    */

    /**
     * Resumen de un usuario: total histórico y desglose por año natural.
     */
    public function places(int $userId): array
    {
        $trips = Trip::query()
            ->where('user_id', $userId)
            ->completed()
            ->get(['id', 'start_date', 'end_date']);

        $visits = Visit::query()
            ->visitedBy($userId)
            ->with('city.country')
            ->get(['id', 'trip_id', 'city_id', 'visited_from', 'visited_until']);

        return $this->summarisePlaces($trips, $visits);
    }

    /**
     * @param  Collection<int, Trip>  $trips  viajes completados
     * @param  Collection<int, Visit>  $visits  visitas de viajes completados, con city.country
     */
    public function summarisePlaces(Collection $trips, Collection $visits): array
    {
        $tripRanges = $trips->map(fn (Trip $trip) => [$trip->start_date?->toDateString(), $trip->end_date?->toDateString()]);
        $days = self::travelDays($tripRanges);

        // Una entrada por visita con país conocido: código, ciudad y años que toca.
        $entries = $visits
            ->filter(fn (Visit $visit) => $visit->city?->country?->iso_code)
            ->map(fn (Visit $visit) => [
                'iso' => strtoupper($visit->city->country->iso_code),
                'name' => $visit->city->country->name,
                'city_id' => $visit->city_id,
                'years' => self::yearsOf($visit->visited_from?->toDateString(), $visit->visited_until?->toDateString()),
            ])
            ->values();

        // Año de la primera visita fechada de cada código.
        $firstYear = [];

        foreach ($entries as $entry) {
            if ($entry['years'] !== []) {
                $firstYear[$entry['iso']] = min($firstYear[$entry['iso']] ?? PHP_INT_MAX, $entry['years'][0]);
            }
        }

        $years = collect($days['by_year'])->keys()
            ->merge($entries->flatMap(fn ($entry) => $entry['years']))
            ->unique()
            ->sortDesc()
            ->values();

        $byYear = [];

        foreach ($years as $year) {
            $yearEntries = $entries->filter(fn ($entry) => in_array($year, $entry['years'], true));
            $new = $entries
                ->filter(fn ($entry) => ($firstYear[$entry['iso']] ?? null) === $year)
                ->unique('iso')
                ->sortBy('name')
                ->values();

            $byYear[$year] = [
                ...$this->countPlaces($yearEntries),
                'days' => $days['by_year'][$year] ?? 0,
                // Año de inicio (regla previa de "viajes por año").
                'trips' => $tripRanges->filter(fn ($range) => $range[0] !== null && (int) substr($range[0], 0, 4) === $year)->count(),
                'new_countries' => $new
                    ->filter(fn ($entry) => SovereignCountries::isSovereign($entry['iso']))
                    ->map(fn ($entry) => ['iso_code' => $entry['iso'], 'name' => $entry['name']])
                    ->values()
                    ->all(),
                'new_territories' => SovereignCountries::countVisitedTerritories($new->pluck('iso')),
            ];
        }

        return [
            'total' => [
                ...$this->countPlaces($entries),
                'days' => $days['total'],
                'trips' => $trips->count(),
                'percentage' => SovereignCountries::percentage($entries->pluck('iso')),
                'total_countries' => SovereignCountries::total(),
            ],
            'by_year' => $byYear,
            'continents' => $this->continents($entries->pluck('iso')),
            // Datos que no se pueden situar con fiabilidad (se informan, no se inventan).
            'undated_visits' => $entries->filter(fn ($entry) => $entry['years'] === [])->count(),
            'invalid_trips' => $days['invalid'],
        ];
    }

    /**
     * @param  Collection<int, array{iso: string, city_id: int}>  $entries
     * @return array{countries: int, territories: int, cities: int, continents: int}
     */
    private function countPlaces(Collection $entries): array
    {
        $codes = $entries->pluck('iso');

        return [
            'countries' => SovereignCountries::countVisited($codes),
            'territories' => SovereignCountries::countVisitedTerritories($codes),
            'cities' => $entries->pluck('city_id')->filter()->unique()->count(),
            'continents' => collect($this->continents($codes)['items'])->filter(fn ($item) => $item['visited'])->count(),
        ];
    }

    /**
     * Progreso por continente: países soberanos visitados sobre el total del
     * continente, y territorios aparte.
     *
     * @param  iterable<string>  $isoCodes
     */
    public function continents(iterable $isoCodes): array
    {
        $map = config('continents.countries', []);
        $names = config('continents.names', []);
        $visited = collect($isoCodes)->map(fn ($code) => strtoupper((string) $code))->unique();

        $counted = $visited->filter(fn ($code) => SovereignCountries::classify($code) !== SovereignCountries::SPECIAL);
        $withoutContinent = $counted->reject(fn ($code) => isset($map[$code]))->sort()->values()->all();

        $items = collect($names)->map(function (string $name, string $continent) use ($map, $counted) {
            $members = $counted->filter(fn ($code) => ($map[$code] ?? null) === $continent);
            $sovereign = $members->filter(fn ($code) => SovereignCountries::isSovereign($code))->count();

            return [
                'code' => $continent,
                'name' => $name,
                'countries' => $sovereign,
                'territories' => $members->count() - $sovereign,
                'total_countries' => collect(SovereignCountries::codes())->filter(fn ($code) => ($map[$code] ?? null) === $continent)->count(),
                'visited' => $members->isNotEmpty(),
            ];
        })->values()->all();

        return ['items' => $items, 'without_continent' => $withoutContinent];
    }

    /*
    |--------------------------------------------------------------------------
    | Vuelos
    |--------------------------------------------------------------------------
    */

    /**
     * Cifras de los vuelos realizados entre los dados. Los vuelos necesitan
     * trip, originAirport.city y destinationAirport.city cargados.
     *
     * @param  iterable<Flight>  $flights
     */
    public function flights(iterable $flights): array
    {
        $flown = collect($flights)
            ->filter(fn (Flight $flight) => ($flight->flight_status ?? $flight->status()) === Flight::FLOWN)
            ->values();

        // Mismo redondeo que cada vuelo en la lista (FlightPresenter).
        $distances = $flown
            ->map(fn (Flight $flight) => ['flight' => $flight, 'km' => $flight->distanceKm()])
            ->filter(fn ($item) => $item['km'] !== null)
            ->map(fn ($item) => [...$item, 'km' => (int) round($item['km'])]);

        $durations = $flown->map(fn (Flight $flight) => $flight->durationMinutes())->filter(fn ($minutes) => $minutes !== null);

        $airports = $flown->flatMap(fn (Flight $flight) => [$flight->originAirport, $flight->destinationAirport])->filter();

        $longest = $distances
            ->sortBy([['km', 'desc'], [fn ($a, $b) => $a['flight']->departure_date <=> $b['flight']->departure_date]])
            ->first();

        return [
            'flights' => $flown->count(),
            'distance_km' => $distances->isEmpty() ? null : $distances->sum('km'),
            'flights_with_distance' => $distances->count(),
            'average_distance_km' => $distances->isEmpty() ? null : (int) round($distances->avg('km')),
            'air_minutes' => $durations->isEmpty() ? null : $durations->sum(),
            'flights_with_times' => $durations->count(),
            'flights_without_times' => $flown->count() - $durations->count(),
            'average_air_minutes' => $durations->isEmpty() ? null : (int) round($durations->avg()),
            'airports' => $airports->pluck('id')->unique()->count(),
            'cities' => $airports->pluck('city_id')->filter()->unique()->count(),
            'longest' => $longest ? [
                'id' => $longest['flight']->id,
                'origin' => $this->airportCode($longest['flight']->originAirport),
                'destination' => $this->airportCode($longest['flight']->destinationAirport),
                'distance_km' => $longest['km'],
                'departure_date' => $longest['flight']->departure_date?->toDateString(),
            ] : null,
            'top_airports' => $airports
                ->groupBy('id')
                ->map(fn ($uses) => [
                    'id' => $uses->first()->id,
                    'code' => $this->airportCode($uses->first()),
                    'airport' => $uses->first()->name,
                    'city' => $uses->first()->city?->name,
                    'count' => $uses->count(),
                ])
                ->sortBy([['count', 'desc'], ['code', 'asc'], ['airport', 'asc']])
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private static function dateRange(?string $start, ?string $end): ?array
    {
        $pattern = '/^\d{4}-\d{2}-\d{2}/';

        if (! $start || ! $end || ! preg_match($pattern, $start) || ! preg_match($pattern, $end)) {
            return null;
        }

        try {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', substr($start, 0, 10), 'UTC');
            $until = CarbonImmutable::createFromFormat('!Y-m-d', substr($end, 0, 10), 'UTC');
        } catch (\Throwable) {
            return null;
        }

        // Fechas imposibles (p. ej. 2026-02-30) no se aceptan aunque Carbon las desborde.
        if (! $from || ! $until || $from->format('Y-m-d') !== substr($start, 0, 10) || $until->format('Y-m-d') !== substr($end, 0, 10)) {
            return null;
        }

        return $from->lessThanOrEqualTo($until) ? [$from, $until] : null;
    }

    /**
     * Une intervalos solapados o contiguos.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $ranges
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private static function mergeRanges(array $ranges): array
    {
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);

            if ($last !== null && $start->lessThanOrEqualTo($merged[$last][1]->addDay())) {
                $merged[$last][1] = $merged[$last][1]->max($end);

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }

    private function airportCode($airport): ?string
    {
        return $airport ? ($airport->iata_code ?: $airport->icao_code ?: null) : null;
    }
}
