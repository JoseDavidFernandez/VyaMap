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
 * con users:create) por el historial inventado de una persona que vive en
 * Madrid: 13 viajes completados entre 2024 y 2026 (escapadas y viajes largos
 * por Europa, América, Asia y África, y Gibraltar), uno cancelado y uno
 * planificado que empieza 45 días después de hoy. Vuelos de ida y vuelta
 * desde Madrid, con escala en Doha, llegadas al día siguiente, algunos sin
 * horas y dos independientes confirmados. Solo usa ciudades y aeropuertos del
 * catálogo. Se puede repetir para restaurar la demo.
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

    /** Aeropuertos (y sus ciudades) que usa la demo: deben existir en el catálogo. */
    private const AIRPORTS = [
        'MAD', 'BCN', 'AGP', 'GIB', 'LIS', 'OPO', 'JFK', 'FCO', 'FLR', 'VCE', 'RAK', 'AMS', 'BRU',
        'DOH', 'HND', 'HIJ', 'KIX', 'KEF', 'ATH', 'JTR', 'MEX', 'CUN', 'LHR', 'LGW', 'PRG', 'VIE',
        'BUD', 'EZE', 'IST', 'YYZ', 'YUL',
    ];

    private int $flightNumber = 0;

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No existe ninguna cuenta con ese correo. Créala antes con: php artisan users:create "Demo" <correo>');

            return self::FAILURE;
        }

        // Aviso, no bloqueo: también sirve para preparar otra cuenta de pruebas.
        if (! $user->isDemo()) {
            $this->warn('Aviso: esta cuenta no es la de DEMO_USER_EMAIL, así que no será de solo lectura.');
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
        $date = fn (string $day) => CarbonImmutable::parse($day);

        // [nombre, descripción, inicio, días, estado, visitas [aeropuerto, desde, hasta],
        //  vuelos [origen, destino, día, salida, llegada, días hasta la llegada]].
        // Días contados desde el inicio; horas locales de cada aeropuerto.
        $trips = [
            ['Lisbon & Porto', 'A long weekend in Lisbon, then up the coast to Porto.', $date('2024-03-14'), 4, Trip::COMPLETED,
                [['LIS', 0, 2], ['OPO', 2, 4]],
                [['MAD', 'LIS', 0, '08:00', '08:20'], ['OPO', 'MAD', 4, '18:40', '20:55']]],
            ['Málaga & Gibraltar', 'Train to the Costa del Sol and a day across the border.', $date('2024-04-26'), 3, Trip::COMPLETED,
                [['AGP', 0, 2], ['GIB', 2, 3]],
                []],
            ['New York', 'First time in New York: a week of museums, parks and neighbourhoods.', $date('2024-06-08'), 8, Trip::COMPLETED,
                [['JFK', 0, 7]],
                [['MAD', 'JFK', 0, '12:05', '14:20'], ['JFK', 'MAD', 7, '18:30', '07:35', 1]]],
            ['Rome, Florence & Venice', 'Italy by train, from Rome to Venice.', $date('2024-09-20'), 8, Trip::COMPLETED,
                [['FCO', 0, 3], ['FLR', 3, 5], ['VCE', 5, 8]],
                [['MAD', 'FCO', 0, '07:10', '09:35'], ['VCE', 'MAD', 8, '19:15', '21:50']]],
            ['Marrakesh', 'A short escape to the medina.', $date('2024-11-08'), 3, Trip::COMPLETED,
                [['RAK', 0, 3]],
                [['MAD', 'RAK', 0, '10:20', '11:55'], ['RAK', 'MAD', 3, null, null]]],
            ['Amsterdam & Brussels', 'Canals, museums and a train to Brussels.', $date('2025-02-14'), 3, Trip::COMPLETED,
                [['AMS', 0, 2], ['BRU', 2, 3]],
                [['MAD', 'AMS', 0, '07:00', '09:30'], ['BRU', 'MAD', 3, '20:05', '22:25']]],
            ['Japan', 'Cherry blossom season: Tokyo, Hiroshima and Osaka by train, via Doha.', $date('2025-04-10'), 12, Trip::COMPLETED,
                [['HND', 1, 6], ['HIJ', 6, 8], ['KIX', 8, 11]],
                [['MAD', 'DOH', 0, '15:45', '23:25'], ['DOH', 'HND', 1, '02:10', '18:50'],
                 ['KIX', 'DOH', 11, '23:30', '04:50', 1], ['DOH', 'MAD', 12, '07:45', '13:50']]],
            ['Iceland', 'A week around Reykjavík: waterfalls, glaciers and the Golden Circle.', $date('2025-08-02'), 6, Trip::COMPLETED,
                [['KEF', 0, 6]],
                [['MAD', 'KEF', 0, '13:10', '14:55'], ['KEF', 'MAD', 6, '16:00', '21:35']]],
            ['Athens & Santorini', 'Ancient Athens and a few days on Santorini.', $date('2025-09-12'), 7, Trip::COMPLETED,
                [['ATH', 0, 3], ['JTR', 3, 7]],
                [['MAD', 'ATH', 0, '11:40', '16:20'], ['ATH', 'JTR', 3, null, null],
                 ['JTR', 'ATH', 7, null, null], ['ATH', 'MAD', 7, '18:15', '21:20']]],
            ['Mexico', 'Mexico City and the Caribbean coast before Christmas.', $date('2025-12-05'), 10, Trip::COMPLETED,
                [['MEX', 0, 4], ['CUN', 4, 9]],
                [['MAD', 'MEX', 0, '12:15', '17:30'], ['MEX', 'CUN', 4, '09:00', '12:15'],
                 ['CUN', 'MAD', 9, '20:30', '11:55', 1]]],
            ['London weekend', 'Markets, theatre and a Sunday roast.', $date('2026-02-20'), 2, Trip::COMPLETED,
                [['LHR', 0, 2]],
                [['MAD', 'LHR', 0, '07:15', '08:35'], ['LGW', 'MAD', 2, null, null]]],
            ['Prague, Vienna & Budapest', 'Central Europe by train along the Danube.', $date('2026-05-01'), 9, Trip::COMPLETED,
                [['PRG', 0, 3], ['VIE', 3, 6], ['BUD', 6, 9]],
                [['MAD', 'PRG', 0, '10:05', '12:55'], ['BUD', 'MAD', 9, '18:20', '21:45']]],
            ['Buenos Aires', 'Two weeks in Argentina, cancelled before departure.', $date('2026-07-10'), 14, Trip::CANCELLED,
                [['EZE', 1, 14]],
                [['MAD', 'EZE', 0, '23:55', '07:45', 1], ['EZE', 'MAD', 14, '13:40', '06:35', 1]]],
            ['Istanbul', 'Four days between Europe and Asia.', $date('2026-10-02'), 4, Trip::COMPLETED,
                [['IST', 0, 4]],
                [['MAD', 'IST', 0, '11:00', '16:00'], ['IST', 'MAD', 4, '19:40', '23:15']]],
            // Siempre futuro: empieza 45 días después del día en que se ejecuta el comando.
            ['Toronto & Montreal', 'Next trip: Toronto, Niagara and Montreal by train.', $today->addDays(45), 10, Trip::PLANNED,
                [['YYZ', 0, 5], ['YUL', 5, 10]],
                [['MAD', 'YYZ', 0, '12:30', '15:10'], ['YUL', 'MAD', 10, '18:35', '07:45', 1]]],
        ];

        foreach ($trips as [$name, $description, $start, $length, $status, $visits, $flights]) {
            $trip = Trip::create([
                'user_id' => $user->id,
                'name' => $name,
                'description' => $description,
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

        // Viaje de trabajo de ida y vuelta en el día: vuelos independientes ya confirmados.
        $workDay = $date('2025-10-14');
        $this->flight($user, null, $airports, $workDay, 'MAD', 'BCN', 0, '07:00', '08:15', confirmed: true);
        $this->flight($user, null, $airports, $workDay, 'BCN', 'MAD', 0, '19:30', '20:45', confirmed: true);
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
            // Número inventado y distinto en cada vuelo; los vuelos sin horas tampoco lo tienen.
            'flight_number' => $departure ? 'VY' . (1100 + 37 * ++$this->flightNumber) : null,
            'confirmed_at' => $confirmed ? now() : null,
        ]);
    }
}
