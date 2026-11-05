<?php

namespace App\Support;

use App\Models\User;

/**
 * Cuenta de demostración de solo lectura (config/demo.php, DEMO_USER_EMAIL).
 *
 * Se identifica por la configuración: el correo de la cuenta no se puede
 * cambiar desde la aplicación mientras sea la de demostración.
 */
final class DemoAccount
{
    public const NOTICE = 'This is a read-only demo account.';

    public static function email(): ?string
    {
        $email = trim((string) config('demo.email'));

        return $email === '' ? null : mb_strtolower($email);
    }

    public static function matches(?string $email): bool
    {
        $demo = self::email();

        return $demo !== null && $email !== null && mb_strtolower(trim($email)) === $demo;
    }

    /** La cuenta, si está configurada y existe. */
    public static function user(): ?User
    {
        $email = self::email();

        return $email === null ? null : User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }
}
