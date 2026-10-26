<?php

namespace App\Console\Commands;

use App\Models\Airport;
use App\Models\Flight;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Datos ficticios para una cuenta de demostración.
 *
 * Sustituye los viajes, visitas y vuelos de UNA cuenta ya existente (creada
 * con users:create) por un conjunto fijo de viajes inventados, con fechas
 * relativas a hoy: viajes completados en los últimos años (uno cruza el fin
 * de año), un viaje planificado, vuelos con y sin horas y un vuelo
 * independiente confirmado. Se puede repetir para restaurar la demo.
 *
 * Salvaguardas: la cuenta debe existir; si tiene fotos no se toca (no es una
 * cuenta de demostración); si faltan aeropuertos en el catálogo no se borra
 * nada; pide confirmación salvo con --force; todo ocurre en una transacción y
 * solo afecta a los viajes y vuelos de esa cuenta.
 */
class ResetDemoData extends Command
{
    protected $signature = 'demo:reset
        {email : Correo de la cuenta de demostración (créala antes con users:create)}
        {--force : No pedir confirmación}';

    protected $description = 'Sustituye los viajes y vuelos de la cuenta de demostración por datos ficticios';

    private const AIRPORTS = ['MAD', 'BCN', 'LIS', 'FCO', 'FLR', 'LHR', 'JFK', 'CDG'];

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No existe ninguna cuenta con ese correo. Créala antes con: php artisan users:create "Demo" <correo>');

            return self::FAILURE;
        }

        if (Photo::where('user_id', $user->id)->exists()) {
            $this->error('La cuenta tiene fotos: no parece una cuenta de demostración y no se modifica.');

            return self::FAILURE;
        }

        $airports = Airport::whereIn('iata_code', self::AIRPORTS)->get()->keyBy('iata_code');
        $missing = collect(self::AIRPORTS)->reject(fn (string $code) => $airports->has($code)
            && $airports[$code]->city_id !== null
            && $airports[$code]->timezone !== null);

        if ($missing->isNotEmpty()) {
            $this->error('Faltan en el catálogo (o sin ciudad o zona horaria): ' . $missing->implode(', ') . '. Importa el catálogo antes. No se ha cambiado nada.');

            return self::FAILURE;
        }

        $current = [
            'viajes' => Trip::where('user_id', $user->id)->count(),
            'visitas' => Visit::where('user_id', $user->id)->count(),
            'vuelos' => Flight::where('user_id', $user->id)->count(),
        ];

        $this->line("Cuenta: {$user->email}. Se borrarán " . collect($current)->map(fn ($count, $what) => "{$count} {$what}")->implode(', ') . ' y se crearán los datos de demostración.');

        if (! $this->option('force') && ! $this->confirm('¿Continuar?')) {
            $this->line('Cancelado. No se ha cambiado nada.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $airports) {
            Flight::where('user_id', $user->id)->delete();
            Trip::where('user_id', $user->id)->get()->each->delete(); // las visitas se borran en cascada
            Visit::where('user_id', $user->id)->delete();

            $this->createDemoData($user, $airports);
        });

        $this->info(sprintf(
            'Datos de demostración creados: %d viajes, %d visitas y %d vuelos.',
            Trip::where('user_id', $user->id)->count(),
            Visit::where('user_id', $user->id)->count(),
            Flight::where('user_id', $user->id)->count(),
        ));

        return self::SUCCESS;
    }

    private function createDemoData(User $user, $airports): void
    {
        $today = CarbonImmutable::parse(Trip::today());
        // Fin de año completo y ya pasado: del 30 de diciembre al 2 de enero.
        $newYear = $today->lt($today->setDate($today->year, 1, 3)) ? $today->year - 2 : $today->year - 1;

        $trips = [
            ['Weekend in Lisbon', $today->subDays(540), 3, Trip::COMPLETED, [['LIS', 0, 3]], [
                ['MAD', 'LIS', 0, '08:00', '08:25'], ['LIS', 'MAD', 3, null, null],
            ]],
            ['Rome & Florence', $today->subDays(300), 6, Trip::COMPLETED, [['FCO', 0, 3], ['FLR', 4, 6]], [
                ['MAD', 'FCO', 0, '07:15', '09:45'], ['FLR', 'MAD', 6, '18:30', '20:45'],
            ]],
            ['New Year in London', CarbonImmutable::create($newYear, 12, 30), 3, Trip::COMPLETED, [['LHR', 0, 3]], [
                ['MAD', 'LHR', 0, '07:10', '08:40'], ['LHR', 'MAD', 3, '20:00', '23:20'],
            ]],
            ['New York', $today->subDays(120), 8, Trip::COMPLETED, [['JFK', 0, 8]], [
                ['MAD', 'JFK', 0, '10:00', '12:30'], ['JFK', 'MAD', 7, '19:00', '08:00', 1],
            ]],
            ['Paris', $today->addDays(30), 4, Trip::PLANNED, [['CDG', 0, 4]], [
                ['MAD', 'CDG', 0, '09:00', '11:05'],
            ]],
        ];

        foreach ($trips as [$name, $start, $length, $status, $visits, $flights]) {
            $trip = Trip::create([
                'user_id' => $user->id,
                'name' => $name,
                'description' => 'Demo trip with fictitious data.',
                'start_date' => $start->toDateString(),
                'end_date' => $start->addDays($length)->toDateString(),
                'status' => $status,
            ]);

            foreach ($visits as [$code, $from, $until]) {
                Visit::create([
                    'user_id' => $user->id,
                    'trip_id' => $trip->id,
                    'city_id' => $airports[$code]->city_id,
                    'visited_from' => $start->addDays($from)->toDateString(),
                    'visited_until' => $start->addDays($until)->toDateString(),
                ]);
            }

            foreach ($flights as $flight) {
                $this->flight($user, $trip, $airports, $start, ...$flight);
            }
        }

        // Vuelo independiente, sin horas y confirmado como realizado.
        $this->flight($user, null, $airports, $today->subDays(60), 'MAD', 'BCN', 0, null, null, confirmed: true);
    }

    private function flight(User $user, ?Trip $trip, $airports, CarbonImmutable $start, string $from, string $to, int $day, ?string $departure, ?string $arrival, int $arrivalOffset = 0, bool $confirmed = false): void
    {
        $origin = $airports[$from];
        $destination = $airports[$to];
        $date = $start->addDays($day)->toDateString();
        $local = fn (string $day, ?string $time, string $zone) => $time ? CarbonImmutable::parse("{$day} {$time}", $zone)->utc() : null;

        Flight::create([
            'user_id' => $user->id,
            'trip_id' => $trip?->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'departure_date' => $date,
            'departure_at' => $local($date, $departure, $origin->timezone),
            'arrival_at' => $local($start->addDays($day + $arrivalOffset)->toDateString(), $arrival, $destination->timezone),
            'flight_number' => $departure ? 'VY' . (1000 + $origin->id % 9000) : null,
            'confirmed_at' => $confirmed ? now() : null,
        ]);
    }
}
