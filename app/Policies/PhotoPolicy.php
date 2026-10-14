<?php

namespace App\Policies;

use App\Models\Photo;
use App\Models\User;

class PhotoPolicy
{
    /**
     * Solo el propietario, y solo si todo aquello a lo que está asociada la
     * foto (viaje, visita, entrada del diario, álbumes) también es suyo. Las
     * subidas ya lo validan; aquí se comprueba de nuevo al servir el archivo.
     */
    public function view(User $user, Photo $photo): bool
    {
        if ($photo->user_id !== $user->id) {
            return false;
        }

        if ($photo->trip_id !== null && $photo->trip?->user_id !== $user->id) {
            return false;
        }

        if ($photo->visit_id !== null && $photo->visit?->user_id !== $user->id) {
            return false;
        }

        if (
            $photo->journal_entry_id !== null
            && $photo->journalEntry?->journalDay?->journal?->trip?->user_id !== $user->id
        ) {
            return false;
        }

        return $photo->albums()->where('albums.user_id', '!=', $user->id)->doesntExist();
    }
}
