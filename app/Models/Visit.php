<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Trip;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trip_id',
        'city_id',
        'visited_from',
        'visited_until',
        'notes',
    ];

    protected $casts = [
        'visited_from' => 'date',
        'visited_until' => 'date',
    ];

    /**
     * Visitas que cuentan como lugar visitado: las de viajes completados del
     * usuario. Una visita planificada (o de un viaje cancelado) no cuenta.
     */
    public function scopeVisitedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas('trip', fn ($trip) => $trip
            ->where('user_id', $userId)
            ->where('status', Trip::COMPLETED));
    }

    // Visitas de viajes planificados (próximos, en curso o pendientes de confirmar).
    public function scopePlannedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas('trip', fn ($trip) => $trip
            ->where('user_id', $userId)
            ->where('status', Trip::PLANNED));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}