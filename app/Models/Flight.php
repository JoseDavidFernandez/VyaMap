<?php

namespace App\Models;

use App\Support\GeoDistance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de un vuelo: no se guarda, se deduce aquí y solo aquí (status()).
 *
 * Datos:
 * - departure_date: día local de salida en el aeropuerto de origen (siempre).
 * - departure_at / arrival_at: instantes UTC, solo si se conocen las horas.
 * - confirmed_at: confirmación explícita de que el vuelo se realizó.
 *
 * Reglas, en orden:
 * 1. cancelled: su viaje está cancelado (no cuenta en nada general).
 * 2. upcoming:  aún no ha salido. Con hora, departure_at > ahora; sin hora,
 *               durante su fecha local de salida (y antes) sigue próximo.
 * 3. flown:     ya salió y su viaje está completado (confirmar el viaje
 *               confirma sus vuelos), o tiene confirmed_at.
 * 4. flown:     ya salió, su viaje planificado ya empezó y se conoce la hora
 *               exacta de salida (que ya pasó).
 * 5. pending:   el resto de vuelos que ya salieron: sin hora y sin
 *               confirmación, o independientes sin confirmar.
 *
 * status() necesita las relaciones trip y originAirport cargadas (with()).
 */
class Flight extends Model
{
    use HasFactory;

    public const UPCOMING = 'upcoming';

    public const FLOWN = 'flown';

    public const PENDING = 'pending';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'trip_id',
        'origin_airport_id',
        'destination_airport_id',
        'departure_date',
        'flight_number',
        'departure_at',
        'arrival_at',
        'airline',
        'confirmed_at',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'departure_at' => 'datetime',
        'arrival_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Misma regla que la migración: si se conoce el instante de salida y no
        // la fecha local, esta sale de la zona del aeropuerto de origen. Sin
        // zona no se deduce (la base de datos rechazará el vuelo sin fecha).
        static::saving(function (Flight $flight) {
            if ($flight->departure_date !== null || $flight->departure_at === null) {
                return;
            }

            $timezone = Airport::whereKey($flight->origin_airport_id)->value('timezone');

            if ($timezone) {
                $flight->departure_date = $flight->departure_at->copy()->setTimezone($timezone)->toDateString();
            }
        });
    }

    public function status(?CarbonInterface $now = null, ?string $today = null): string
    {
        $now ??= now();
        $trip = $this->trip;

        if ($trip?->status === Trip::CANCELLED) {
            return self::CANCELLED;
        }

        if (! $this->hasDeparted($now)) {
            return self::UPCOMING;
        }

        return match (true) {
            $trip?->status === Trip::COMPLETED,
            $this->confirmed_at !== null,
            $trip?->status === Trip::PLANNED && $trip->hasStarted($today) && $this->departure_at !== null => self::FLOWN,
            default => self::PENDING,
        };
    }

    /**
     * Con hora: el instante de salida ya pasó. Sin hora: la fecha local de
     * salida ya terminó en la zona del aeropuerto de origen (D8).
     */
    public function hasDeparted(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        if ($this->departure_at !== null) {
            return $this->departure_at->lessThanOrEqualTo($now);
        }

        if ($this->departure_date === null) {
            return false;
        }

        $timezone = $this->originAirport?->timezone ?: config('app.user_timezone');

        return $this->departure_date->toDateString() < $now->copy()->setTimezone($timezone)->toDateString();
    }

    // Solo si se conocen las dos horas; nunca se estima.
    public function durationMinutes(): ?int
    {
        if ($this->departure_at === null || $this->arrival_at === null) {
            return null;
        }

        return max(0, (int) $this->departure_at->diffInMinutes($this->arrival_at));
    }

    // Distancia del círculo máximo; null si falta alguna coordenada (nunca 0).
    public function distanceKm(): ?float
    {
        $origin = $this->originAirport;
        $destination = $this->destinationAirport;

        if (
            $origin?->latitude === null || $origin?->longitude === null
            || $destination?->latitude === null || $destination?->longitude === null
        ) {
            return null;
        }

        return GeoDistance::km(
            (float) $origin->latitude,
            (float) $origin->longitude,
            (float) $destination->latitude,
            (float) $destination->longitude,
        );
    }

    // Orden cronológico estable: fecha local, hora (los vuelos sin hora, antes) e id.
    public function scopeChronological(Builder $query, string $direction = 'asc'): Builder
    {
        return $query
            ->orderBy('departure_date', $direction)
            ->orderBy('departure_at', $direction)
            ->orderBy('id', $direction);
    }

    /**
     * Calcula el estado de cada vuelo una sola vez (flight_status) para que
     * todas las páginas apliquen la misma definición.
     *
     * @param  Collection<int, Flight>  $flights
     * @return Collection<int, Flight>
     */
    public static function withStatuses(Collection $flights, ?CarbonInterface $now = null, ?string $today = null): Collection
    {
        $now ??= now();
        $today ??= Trip::today();

        return $flights->each(fn (Flight $flight) => $flight->setAttribute('flight_status', $flight->status($now, $today)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function originAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'origin_airport_id');
    }

    public function destinationAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'destination_airport_id');
    }
}
