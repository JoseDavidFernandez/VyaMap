<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Marca como verificado el correo de UNA cuenta, por ejemplo una creada
 * antes de activar la verificación o cuyo buzón no recibe el enlace.
 *
 *   php artisan users:verify correo@example.com
 *
 * Solo escribe email_verified_at de esa fila (ni updated_at ni ninguna otra
 * columna) y pide confirmación salvo con --force.
 */
class VerifyUserEmail extends Command
{
    protected $signature = 'users:verify
        {email : Correo de la cuenta}
        {--force : No pedir confirmación}';

    protected $description = 'Marca como verificado el correo de una cuenta';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No existe ninguna cuenta con ese correo.');

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->line('El correo de esa cuenta ya está verificado. No se ha cambiado nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("¿Marcar como verificado el correo de la cuenta {$user->id} ({$user->email})?")) {
            $this->line('Cancelado. No se ha cambiado nada.');

            return self::FAILURE;
        }

        // Consulta directa (toBase): markEmailAsVerified() guardaría también
        // updated_at. whereNull evita pisar una verificación simultánea.
        $updated = User::query()->whereKey($user->id)->whereNull('email_verified_at')
            ->toBase()->update(['email_verified_at' => now()]);

        if ($updated !== 1) {
            $this->error('No se ha cambiado nada: la cuenta ya no está pendiente de verificar.');

            return self::FAILURE;
        }

        $this->info("Correo verificado (cuenta {$user->id}).");

        return self::SUCCESS;
    }
}
