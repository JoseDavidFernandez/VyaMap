<?php

namespace App\Services;

use App\Models\Flight;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vuelos existentes en un viaje: sugerir, buscar, asociar y quitar.
 *
 * Reglas:
 * - Solo se ofrecen y asocian vuelos independientes (trip_id null) del mismo
 *   usuario que el viaje: un vuelo solo puede estar en un viaje.
 * - Sugerencias: fecha local de salida entre el inicio del viaje menos un día
 *   y el fin más un día (vuelo de ida la víspera, husos horarios).
 * - Un vuelo fuera de las fechas del viaje se avisa, no se bloquea (D3).
 * - Quitar un vuelo no lo borra: vuelve a ser independiente. Si ya contaba
 *   como realizado, queda confirmado, igual que al borrar el viaje.
 */
class TripFlightService
{
    public const SUGGESTION_MARGIN_DAYS = 1;

    public const SEARCH_LIMIT = 20;

    /**
     * Vuelos independientes del usuario cuya salida cae en las fechas dadas,
     * con un día de margen por cada lado.
     *
     * @return Collection<int, Flight>
     */
    public function suggestions(int $userId, string $startDate, string $endDate): Collection
    {
        $from = CarbonImmutable::parse($startDate)->subDays(self::SUGGESTION_MARGIN_DAYS)->toDateString();
        $until = CarbonImmutable::parse($endDate)->addDays(self::SUGGESTION_MARGIN_DAYS)->toDateString();

        return $this->standalone($userId)
            ->whereBetween('departure_date', [$from, $until])
            ->chronological()
            ->get();
    }

    /**
     * Búsqueda de vuelos independientes del usuario por fecha local de salida
     * y por texto: código o ciudad de origen o destino, o número de vuelo.
     * "MAD LIS" o "MAD-LIS" buscan la ruta (origen y destino, en ese orden).
     *
     * @return Collection<int, Flight>
     */
    public function search(int $userId, ?string $query, ?string $date): Collection
    {
        $flights = $this->standalone($userId);

        if ($date) {
            $flights->where('departure_date', $date);
        }

        $terms = collect(preg_split('/[\s,>\-→]+/u', trim((string) $query)) ?: [])
            ->filter(fn ($term) => $term !== '')
            ->take(2)
            ->values();

        if ($terms->count() === 2) {
            $flights
                ->whereHas('originAirport', fn ($airport) => $this->matchAirport($airport, $terms[0]))
                ->whereHas('destinationAirport', fn ($airport) => $this->matchAirport($airport, $terms[1]));
        } elseif ($terms->count() === 1) {
            $term = $terms[0];

            $flights->where(fn ($match) => $match
                ->where('flight_number', 'like', $this->like(strtoupper($term)))
                ->orWhereHas('originAirport', fn ($airport) => $this->matchAirport($airport, $term))
                ->orWhereHas('destinationAirport', fn ($airport) => $this->matchAirport($airport, $term)));
        }

        return $flights->chronological('desc')->limit(self::SEARCH_LIMIT)->get();
    }

    /**
     * Asocia al viaje los vuelos independientes del mismo usuario. Los ids
     * que no cumplan (ajenos o ya en otro viaje) se ignoran; la validación
     * de la petición ya los rechaza antes.
     *
     * @param  iterable<int>  $flightIds
     * @return int vuelos asociados
     */
    public function attach(Trip $trip, iterable $flightIds): int
    {
        $ids = collect($flightIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return $this->standalone($trip->user_id)
            ->whereKey($ids->all())
            ->update(['trip_id' => $trip->id]);
    }

    /**
     * Quita un vuelo del viaje sin borrarlo.
     */
    public function detach(Trip $trip, Flight $flight): void
    {
        DB::transaction(function () use ($trip, $flight) {
            $this->confirmFlownFlights($trip, Flight::query()->whereKey($flight->id));

            Flight::query()->whereKey($flight->id)->where('trip_id', $trip->id)->update(['trip_id' => null]);
        });
    }

    /**
     * Todos los vuelos del viaje pasan a ser independientes (antes de borrarlo).
     */
    public function detachAll(Trip $trip): void
    {
        $this->confirmFlownFlights($trip, $trip->flights()->getQuery());

        $trip->flights()->update(['trip_id' => null]);
    }

    public function isOutsideTrip(Flight $flight, Trip $trip): bool
    {
        $date = $flight->departure_date?->toDateString();

        return $date === null
            || $date < $trip->start_date->toDateString()
            || $date > $trip->end_date->toDateString();
    }

    /**
     * Los vuelos que ya contaban como realizados por su viaje (no cancelado)
     * quedan confirmados para no volver a "pendientes" al quedarse sin viaje.
     */
    private function confirmFlownFlights(Trip $trip, Builder $flights): void
    {
        if ($trip->status === Trip::CANCELLED) {
            return;
        }

        $now = now();
        $today = Trip::today();

        $flights
            ->where('trip_id', $trip->id)
            ->whereNull('confirmed_at')
            ->with('originAirport')
            ->get()
            ->each(fn (Flight $flight) => $flight->setRelation('trip', $trip))
            ->filter(fn (Flight $flight) => $flight->status($now, $today) === Flight::FLOWN)
            ->each(fn (Flight $flight) => $flight->update(['confirmed_at' => $now]));
    }

    private function standalone(int $userId): Builder
    {
        return Flight::query()
            ->where('user_id', $userId)
            ->whereNull('trip_id')
            ->with(['trip', 'originAirport.city', 'destinationAirport.city']);
    }

    private function matchAirport(Builder $airport, string $term): void
    {
        $airport->where(fn ($match) => $match
            ->where('iata_code', strtoupper($term))
            ->orWhere('icao_code', strtoupper($term))
            ->orWhereHas('city', fn ($city) => $city->where('name', 'like', $this->like($term))));
    }

    private function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
