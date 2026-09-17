<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airport extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id',
        'name',
        'iata_code',
        'icao_code',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function departureFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'origin_airport_id');
    }

    public function arrivalFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'destination_airport_id');
    }
}