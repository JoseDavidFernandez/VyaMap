<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Estados guardados: planned, completed, cancelled. Fases que se deducen de
 * las fechas (en app.user_timezone) para un viaje planned:
 *
 * - upcoming: empieza después de hoy.
 * - ongoing:  hoy está entre el inicio y el fin.
 * - pending:  el fin ya pasó; espera que el usuario lo confirme.
 *
 * Un viaje nunca pasa a completed solo porque su fecha haya pasado. Solo
 * los viajes completed cuentan como lugares visitados.
 */
class Trip extends Model
{
    use HasFactory;

    public const PLANNED = 'planned';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::PLANNED, self::COMPLETED, self::CANCELLED];

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        // Sin estado explícito: historial pasado = completed; lo demás, planned.
        static::creating(function (Trip $trip) {
            $trip->status ??= self::initialStatusFor($trip->end_date);
        });
    }

    // "Hoy" es el día del calendario del usuario, no el de UTC.
    public static function today(): string
    {
        return Carbon::now(config('app.user_timezone'))->toDateString();
    }

    public static function initialStatusFor(mixed $endDate, ?string $today = null): string
    {
        $end = $endDate ? Carbon::parse($endDate)->toDateString() : null;

        return $end !== null && $end < ($today ?? self::today()) ? self::COMPLETED : self::PLANNED;
    }

    /**
     * cancelled, completed, upcoming, ongoing o pending.
     */
    public function phase(?string $today = null): string
    {
        $today ??= self::today();

        if ($this->status !== self::PLANNED) {
            return $this->status;
        }

        return match (true) {
            $this->start_date?->toDateString() > $today => 'upcoming',
            $this->end_date?->toDateString() < $today => 'pending',
            default => 'ongoing',
        };
    }

    public function hasStarted(?string $today = null): bool
    {
        return $this->start_date !== null && $this->start_date->toDateString() <= ($today ?? self::today());
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::COMPLETED);
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::PLANNED);
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', self::CANCELLED);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function journal(): HasOne
    {
        return $this->hasOne(Journal::class);
    }

    public function googleMapLists(): BelongsToMany
    {
        return $this->belongsToMany(GoogleMapList::class);
    }
}