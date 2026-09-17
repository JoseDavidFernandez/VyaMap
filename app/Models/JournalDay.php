<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_id',
        'day_number',
        'date',
        'title',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}