<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityReconciliation extends Model
{
    protected $fillable = [
        'duplicate_city_id',
        'canonical_city_id',
        'changes',
        'reconciled_at',
        'reverted_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'reconciled_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function duplicateCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'duplicate_city_id');
    }

    public function canonicalCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'canonical_city_id');
    }
}
