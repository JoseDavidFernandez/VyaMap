<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_day_id',
        'title',
        'content',
        'city_id',
        'place_id',
        'sort_order',
    ];

    public function journalDay(): BelongsTo
    {
        return $this->belongsTo(JournalDay::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}