<?php

namespace App\Support;

use App\Models\User;

/**
 * Si el formulario de registro admite cuentas nuevas: debe estar activado
 * (auth.registration_enabled) y no haberse alcanzado el número máximo de
 * cuentas (auth.registration_max_users, sin límite si es null).
 */
final class Registration
{
    public static function isOpen(): bool
    {
        return config('auth.registration_enabled') && ! self::isFull();
    }

    public static function isFull(?int $users = null): bool
    {
        $max = config('auth.registration_max_users');

        return $max !== null && ($users ?? User::count()) >= $max;
    }
}
