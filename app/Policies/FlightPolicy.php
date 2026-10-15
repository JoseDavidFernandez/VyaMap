<?php

namespace App\Policies;

use App\Models\Flight;
use App\Models\User;

/**
 * Cada vuelo pertenece a un usuario (user_id), tenga o no viaje. Solo su
 * dueño puede verlo, editarlo, borrarlo o confirmarlo. El viaje elegido se
 * valida aparte (FlightRequest: debe ser del mismo usuario).
 */
class FlightPolicy
{
    public function view(User $user, Flight $flight): bool
    {
        return $flight->user_id === $user->id;
    }

    public function update(User $user, Flight $flight): bool
    {
        return $flight->user_id === $user->id;
    }

    public function delete(User $user, Flight $flight): bool
    {
        return $flight->user_id === $user->id;
    }
}
