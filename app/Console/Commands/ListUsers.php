<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Registration;
use Illuminate\Console\Command;

/**
 * Lista de cuentas para revisar las altas del registro público.
 *
 *   php artisan users:list
 *   php artisan users:list --limit=20
 *
 * Solo lectura: muestra id, nombre, correo, fecha de alta y cuántos viajes,
 * vuelos y fotos tiene cada cuenta. Nunca contraseñas, tokens ni datos de
 * los viajes.
 */
class ListUsers extends Command
{
    protected $signature = 'users:list {--limit=50 : Número máximo de cuentas, de la más reciente a la más antigua}';

    protected $description = 'Lista las cuentas (solo lectura) y el estado del registro';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $total = User::count();
        $max = config('auth.registration_max_users');

        $users = User::query()
            ->withCount(['trips', 'flights', 'photos'])
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'created_at']);

        $this->table(
            ['ID', 'Nombre', 'Correo', 'Alta', 'Viajes', 'Vuelos', 'Fotos'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->created_at?->format('Y-m-d H:i'),
                $user->trips_count,
                $user->flights_count,
                $user->photos_count,
            ])->all(),
        );

        $this->line(sprintf(
            'Cuentas: %d%s (se muestran %d). Registro: %s.',
            $total,
            $max === null ? '' : " de un máximo de {$max}",
            $users->count(),
            Registration::isOpen() ? 'abierto' : 'cerrado',
        ));

        return self::SUCCESS;
    }
}
