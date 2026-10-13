<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Visit;


class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'region_code',
        'latitude',
        'longitude',
        'external_provider',
        'external_id',
        'merged_into_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /**
     * Ciudad canónica cuando este registro se ha fusionado con otro.
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * Ciudades seleccionables: excluye los duplicados ya fusionados.
     */
    public function scopeNotMerged(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('merged_into_id'));
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function airports(): HasMany
    {
        return $this->hasMany(Airport::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CityAlias::class);
    }
}