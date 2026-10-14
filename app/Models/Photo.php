<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Photo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trip_id',
        'visit_id',
        'place_id',
        'journal_entry_id',
        'path',
        'thumbnail_path',
        'original_filename',
        'mime_type',
        'size',
        'width',
        'height',
        'taken_at',
        'latitude',
        'longitude',
        'metadata',
        'processing_status',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'metadata' => 'array',
    ];

    /** Variantes que sirve PhotoFileController. */
    public const VARIANTS = ['full', 'thumb'];

    /**
     * URL autenticada de una variante (null si la foto aún no está lista).
     * El parámetro v cambia si se regenera el archivo; no revela la ruta.
     */
    public function fileUrl(string $variant = 'full'): ?string
    {
        $path = $this->pathFor($variant);

        if ($this->processing_status !== 'ready' || $path === null) {
            return null;
        }

        return route('photos.file', [
            'photo' => $this->id,
            'variant' => $variant,
            'v' => substr(hash('sha256', $path), 0, 10),
        ], false);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->fileUrl('thumb');
    }

    /**
     * Ruta relativa en disco de una variante; la miniatura recurre a la
     * imagen completa si no existe.
     */
    public function pathFor(string $variant): ?string
    {
        $path = match ($variant) {
            'full' => $this->path,
            'thumb' => $this->thumbnail_path ?: $this->path,
            default => null,
        };

        return $path === '' ? null : $path;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class);
    }
}