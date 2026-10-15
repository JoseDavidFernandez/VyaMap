<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use App\Support\FlightPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F1: vuelos independientes y gestión completa.
 *
 * Reglas: fecha local de salida obligatoria; horas, número y aerolínea
 * opcionales (nunca se inventan); estado deducido en Flight::status() (D1,
 * D6, D8); confirmación explícita (D7); al borrar un viaje sus vuelos se
 * conservan (D2).
 *
 * Fecha fija: 15-10-2026 12:00 UTC (14:00 en Madrid).
 */
class FlightManagementTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-15 12:00:00';

    private User $user;

    private Airport $mad;

    private Airport $bcn;

    private Airport $jfk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        config(['app.user_timezone' => 'Europe/Madrid']);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $this->user = User::factory()->create();

        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $usa = Country::create(['name' => 'United States', 'iso_code' => 'US']);
        $this->mad = $this->airport(City::create(['country_id' => $spain->id, 'name' => 'Madrid']), 'MAD', 40.4719, -3.5626, 'Europe/Madrid');
        $this->bcn = $this->airport(City::create(['country_id' => $spain->id, 'name' => 'Barcelona']), 'BCN', 41.2971, 2.0785, 'Europe/Madrid');
        $this->jfk = $this->airport(City::create(['country_id' => $usa->id, 'name' => 'New York']), 'JFK', 40.6413, -73.7781, 'America/New_York');
    }

    /*
    |--------------------------------------------------------------------------
    | Creación y validación
    |--------------------------------------------------------------------------
    */

    public function test_a_historical_flight_needs_only_route_and_date(): void
    {
        $this->actingAs($this->user)
            ->from('/flight-history')
            ->post('/flights', ['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->bcn->id, 'departure_date' => '2019-04-12'])
            ->assertRedirect('/flight-history')
            ->assertSessionHasNoErrors();

        $flight = Flight::sole();
        $this->assertSame($this->user->id, $flight->user_id);
        $this->assertNull($flight->trip_id);
        $this->assertSame('2019-04-12', $flight->departure_date->toDateString());
        $this->assertNull($flight->departure_at);
        $this->assertNull($flight->arrival_at);
        $this->assertNull($flight->flight_number);
        $this->assertNull($flight->airline);
        $this->assertNull($flight->durationMinutes());

        // Pasado e independiente: pendiente de confirmar, no realizado.
        $this->assertSame(Flight::PENDING, $flight->load('trip', 'originAirport')->status());
    }

    public function test_times_flight_number_and_airline_are_stored_when_known(): void
    {
        $this->actingAs($this->user)->post('/flights', [
            'origin_airport_id' => $this->mad->id,
            'destination_airport_id' => $this->jfk->id,
            'departure_date' => '2026-03-10',
            'departure_time' => '10:00',
            'arrival_time' => '13:30',
            'flight_number' => ' ib6251 ',
            'airline' => 'Iberia',
        ])->assertSessionHasNoErrors();

        $flight = Flight::sole();
        $this->assertSame('IB6251', $flight->flight_number);
        $this->assertSame('Iberia', $flight->airline);
        // 10:00 en Madrid (CET) = 09:00 UTC; 13:30 en Nueva York (EDT) = 17:30 UTC.
        $this->assertSame('2026-03-10 09:00:00', $flight->departure_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-10 17:30:00', $flight->arrival_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(510, $flight->durationMinutes());
    }

    public function test_a_flight_can_arrive_the_next_local_day(): void
    {
        $this->actingAs($this->user)->post('/flights', [
            'origin_airport_id' => $this->jfk->id,
            'destination_airport_id' => $this->mad->id,
            'departure_date' => '2026-03-11',
            'departure_time' => '19:00',
            'arrival_date' => '2026-03-12',
            'arrival_time' => '08:00',
        ])->assertSessionHasNoErrors();

        $flight = Flight::sole();
        $this->assertSame('2026-03-11', $flight->departure_date->toDateString());
        $this->assertSame('2026-03-11 23:00:00', $flight->departure_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-12 07:00:00', $flight->arrival_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(480, $flight->durationMinutes());
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function roundTripTimes(): array
    {
        return [
            'medianoche' => ['00:05', 'bcn', '01:20', 0],
            'madrugada (05:30)' => ['05:30', 'bcn', '06:45', 0],
            'mañana (07:30)' => ['07:30', 'bcn', '08:45', 0],
            'mañana hacia otra zona' => ['07:30', 'jfk', '09:45', 0],
            'mediodía (12:30)' => ['12:30', 'bcn', '13:45', 0],
            'llega al día siguiente' => ['23:45', 'bcn', '00:50', 1],
        ];
    }

    /**
     * Crear y después editar sin tocar nada (con las horas tal como las muestra
     * el formulario: hora local de cada aeropuerto y día de llegada) no cambia
     * ningún instante ni la fecha local.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('roundTripTimes')]
    public function test_saving_an_unchanged_flight_keeps_its_times(string $departureTime, string $destination, string $arrivalTime, int $arrivalOffset): void
    {
        $destination = $this->{$destination};
        $payload = [
            'origin_airport_id' => $this->mad->id,
            'destination_airport_id' => $destination->id,
            'departure_date' => '2026-03-10',
            'departure_time' => $departureTime,
            'arrival_date' => Carbon::parse('2026-03-10')->addDays($arrivalOffset)->toDateString(),
            'arrival_time' => $arrivalTime,
            'trip_id' => null,
        ];

        $this->actingAs($this->user)->post('/flights', $payload)->assertSessionHasNoErrors();

        $flight = Flight::withStatuses(Flight::with(['trip', 'originAirport', 'destinationAirport'])->get())->sole();
        $before = [$flight->departure_at->toIso8601String(), $flight->arrival_at->toIso8601String()];

        $shown = FlightPresenter::toArray($flight);
        $localArrival = Carbon::parse($shown['arrival'])->setTimezone($destination->timezone);
        $edited = array_merge($payload, [
            'departure_time' => Carbon::parse($shown['departure'])->setTimezone($this->mad->timezone)->format('H:i'),
            'arrival_time' => $localArrival->format('H:i'),
            'arrival_date' => $localArrival->toDateString(),
        ]);

        $this->assertSame($departureTime, $edited['departure_time']);
        $this->assertSame($payload['arrival_date'], $edited['arrival_date']);

        $this->actingAs($this->user)->put("/flights/{$flight->id}", $edited)->assertSessionHasNoErrors();

        $flight->refresh();
        $this->assertSame($before, [$flight->departure_at->toIso8601String(), $flight->arrival_at->toIso8601String()]);
        $this->assertSame('2026-03-10', $flight->departure_date->toDateString());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidFlights(): array
    {
        return [
            'sin origen' => [['origin_airport_id' => null], 'origin_airport_id'],
            'sin destino' => [['destination_airport_id' => null], 'destination_airport_id'],
            'sin fecha' => [['departure_date' => null], 'departure_date'],
            'fecha mal formada' => [['departure_date' => '12/04/2019'], 'departure_date'],
            'hora de llegada sin hora de salida' => [['arrival_time' => '10:00'], 'arrival_time'],
            'fecha de llegada sin hora de llegada' => [['departure_time' => '08:00', 'arrival_date' => '2026-03-11'], 'arrival_date'],
            'llega antes de salir' => [['departure_time' => '10:00', 'arrival_time' => '09:00'], 'arrival_time'],
            'llegada a más de dos días' => [['departure_time' => '10:00', 'arrival_date' => '2026-03-14', 'arrival_time' => '10:00'], 'arrival_date'],
            'número demasiado largo' => [['flight_number' => str_repeat('X', 21)], 'flight_number'],
            'hora de salida incompleta' => [['departure_time' => '07:3'], 'departure_time'],
            'hora de salida sin cero inicial' => [['departure_time' => '7:30'], 'departure_time'],
            'hora de salida 24:00' => [['departure_time' => '24:00'], 'departure_time'],
            'minutos de salida inválidos' => [['departure_time' => '12:60'], 'departure_time'],
            'hora de llegada incompleta' => [['departure_time' => '10:00', 'arrival_time' => '11:'], 'arrival_time'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFlights')]
    public function test_invalid_flights_are_rejected(array $override, string $field): void
    {
        $payload = array_merge(['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->bcn->id, 'departure_date' => '2026-03-10'], $override);

        $this->actingAs($this->user)->post('/flights', $payload)->assertSessionHasErrors($field);

        $this->assertSame(0, Flight::count());
    }

    public function test_origin_and_destination_must_differ(): void
    {
        $this->actingAs($this->user)
            ->post('/flights', ['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->mad->id, 'departure_date' => '2026-03-10'])
            ->assertSessionHasErrors(['destination_airport_id' => 'Origin and destination must be different airports.']);
    }

    public function test_a_nonexistent_local_time_is_rejected(): void
    {
        // 02:30 del 29-03-2026 no existe en Madrid (cambio a horario de verano).
        $this->actingAs($this->user)
            ->post('/flights', ['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->bcn->id, 'departure_date' => '2026-03-29', 'departure_time' => '02:30'])
            ->assertSessionHasErrors('departure_time');
    }

    public function test_the_trip_must_be_own_and_contain_the_local_departure_date(): void
    {
        $foreign = Trip::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'start_date' => '2026-03-01', 'end_date' => '2026-03-31']);
        $own = $this->trip('2026-03-01', '2026-03-10');
        $base = ['origin_airport_id' => $this->jfk->id, 'destination_airport_id' => $this->mad->id];

        $this->actingAs($this->user)->post('/flights', $base + ['departure_date' => '2026-03-05', 'trip_id' => $foreign->id])
            ->assertSessionHasErrors(['trip_id' => 'Choose one of your trips.']);

        $this->actingAs($this->user)->post('/flights', $base + ['departure_date' => '2026-03-11', 'trip_id' => $own->id])
            ->assertSessionHasErrors('departure_date');

        // D3: el último día del viaje vale, aunque llegue al día siguiente.
        $this->actingAs($this->user)->post('/flights', $base + [
            'departure_date' => '2026-03-10', 'departure_time' => '22:00',
            'arrival_date' => '2026-03-11', 'arrival_time' => '11:00',
            'trip_id' => $own->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame([$own->id], Flight::pluck('trip_id')->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Edición, asociación y borrado
    |--------------------------------------------------------------------------
    */

    public function test_a_standalone_flight_can_be_linked_to_a_trip_and_unlinked_again(): void
    {
        $trip = $this->trip('2026-03-01', '2026-03-31');
        $flight = $this->flight(null, '2026-03-10');
        $payload = ['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->bcn->id, 'departure_date' => '2026-03-10', 'flight_number' => 'VY1'];

        $this->actingAs($this->user)->put("/flights/{$flight->id}", $payload + ['trip_id' => $trip->id])->assertSessionHasNoErrors();
        $this->assertSame($trip->id, $flight->fresh()->trip_id);

        $this->actingAs($this->user)->put("/flights/{$flight->id}", $payload + ['trip_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($flight->fresh()->trip_id);
        $this->assertSame('VY1', $flight->fresh()->flight_number);
    }

    public function test_only_the_owner_can_manage_a_flight(): void
    {
        $flight = $this->flight(null, '2026-03-10');
        $intruder = User::factory()->create();
        $payload = ['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->bcn->id, 'departure_date' => '2026-03-11'];

        $this->actingAs($intruder)->put("/flights/{$flight->id}", $payload)->assertForbidden();
        $this->actingAs($intruder)->delete("/flights/{$flight->id}")->assertForbidden();
        $this->actingAs($intruder)->patch("/flights/{$flight->id}/confirmation", ['confirmed' => true])->assertForbidden();

        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->post('/flights', $payload)->assertRedirect('/login');
        $this->delete("/flights/{$flight->id}")->assertRedirect('/login');

        $this->assertSame('2026-03-10', $flight->fresh()->departure_date->toDateString());
        $this->assertNull($flight->fresh()->confirmed_at);
        $this->assertSame(1, Flight::count());
    }

    public function test_the_owner_deletes_a_standalone_flight(): void
    {
        $flight = $this->flight(null, '2026-03-10');

        $this->actingAs($this->user)->from('/flight-history')->delete("/flights/{$flight->id}")->assertRedirect('/flight-history');

        $this->assertModelMissing($flight);
    }

    public function test_deleting_a_trip_keeps_its_flights_and_their_condition(): void
    {
        // Completado: sus vuelos ya contaban; se conservan confirmados.
        $completed = $this->trip('2026-09-01', '2026-09-05');
        $flown = $this->flight($completed, '2026-09-01', '08:00');
        $flownWithoutTime = $this->flight($completed, '2026-09-02');

        // En curso: el que ya salió con hora cuenta (D1) y se confirma; el de solo fecha, no.
        $ongoing = $this->trip('2026-10-10', '2026-10-20');
        $ongoingFlown = $this->flight($ongoing, '2026-10-10', '08:00');
        $ongoingPending = $this->flight($ongoing, '2026-10-11');
        $ongoingUpcoming = $this->flight($ongoing, '2026-10-20', '18:00');

        // Cancelado: se conservan sin confirmar (pasado → pendiente, futuro → próximo).
        $cancelled = $this->trip('2026-09-10', '2026-12-05', Trip::CANCELLED);
        $cancelledPast = $this->flight($cancelled, '2026-09-10', '08:00');
        $cancelledFuture = $this->flight($cancelled, '2026-12-01', '08:00');

        foreach ([$completed, $ongoing, $cancelled] as $trip) {
            $this->actingAs($this->user)->delete("/trips/{$trip->id}")->assertRedirect('/trips');
        }

        $this->assertSame(7, Flight::whereNull('trip_id')->count());

        $statuses = Flight::withStatuses(Flight::with(['trip', 'originAirport'])->get())->pluck('flight_status', 'id')->all();
        $this->assertSame(Flight::FLOWN, $statuses[$flown->id]);
        $this->assertSame(Flight::FLOWN, $statuses[$flownWithoutTime->id]);
        $this->assertSame(Flight::FLOWN, $statuses[$ongoingFlown->id]);
        $this->assertSame(Flight::PENDING, $statuses[$ongoingPending->id]);
        $this->assertSame(Flight::UPCOMING, $statuses[$ongoingUpcoming->id]);
        $this->assertSame(Flight::PENDING, $statuses[$cancelledPast->id]);
        $this->assertSame(Flight::UPCOMING, $statuses[$cancelledFuture->id]);
        $this->assertNull($cancelledPast->fresh()->confirmed_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Estados (D1, D8) y confirmación (D7)
    |--------------------------------------------------------------------------
    */

    public function test_status_rules(): void
    {
        $ongoing = $this->trip('2026-10-10', '2026-10-20');
        $upcomingTrip = $this->trip('2026-11-01', '2026-11-05');
        $pendingTrip = $this->trip('2026-10-01', '2026-10-05', Trip::PLANNED);

        $cases = [
            'en curso, con hora ya pasada' => [$this->flight($ongoing, '2026-10-12', '08:00'), Flight::FLOWN],
            'en curso, solo fecha pasada' => [$this->flight($ongoing, '2026-10-12'), Flight::PENDING],
            'en curso, sin hora hoy (D8)' => [$this->flight($ongoing, '2026-10-15'), Flight::UPCOMING],
            'en curso, con hora más tarde hoy' => [$this->flight($ongoing, '2026-10-15', '20:00'), Flight::UPCOMING],
            'viaje que aún no empieza' => [$this->flight($upcomingTrip, '2026-11-01', '08:00'), Flight::UPCOMING],
            'viaje pendiente, con hora' => [$this->flight($pendingTrip, '2026-10-01', '08:00'), Flight::FLOWN],
            'viaje pendiente, solo fecha' => [$this->flight($pendingTrip, '2026-10-02'), Flight::PENDING],
            'independiente, solo fecha ayer' => [$this->flight(null, '2026-10-14'), Flight::PENDING],
            'independiente, sin hora hoy (D8)' => [$this->flight(null, '2026-10-15'), Flight::UPCOMING],
            'independiente, futuro' => [$this->flight(null, '2026-12-01'), Flight::UPCOMING],
        ];

        $statuses = Flight::withStatuses(Flight::with(['trip', 'originAirport'])->get())->pluck('flight_status', 'id');

        foreach ($cases as $label => [$flight, $expected]) {
            $this->assertSame($expected, $statuses[$flight->id], $label);
        }

        // D8: al día siguiente local, el vuelo sin hora pasa a pendiente.
        $this->travelTo(Carbon::parse('2026-10-15 22:30:00', 'UTC')); // 00:30 del 16 en Madrid
        $this->assertSame(Flight::PENDING, Flight::with(['trip', 'originAirport'])->find($cases['independiente, sin hora hoy (D8)'][0]->id)->status());
    }

    public function test_confirming_a_flight_counts_it_without_touching_its_trip(): void
    {
        $pendingTrip = $this->trip('2026-10-01', '2026-10-05', Trip::PLANNED);
        $standalone = $this->flight(null, '2019-04-12');
        $inTrip = $this->flight($pendingTrip, '2026-10-02');
        $future = $this->flight(null, '2026-12-01');

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page->where('stats.flights', 0));

        foreach ([$standalone, $inTrip] as $flight) {
            $this->actingAs($this->user)->patch("/flights/{$flight->id}/confirmation", ['confirmed' => true])->assertSessionHasNoErrors();
        }

        // Un vuelo que aún no ha salido no se puede confirmar.
        $this->actingAs($this->user)
            ->patch("/flights/{$future->id}/confirmation", ['confirmed' => true])
            ->assertSessionHasErrors('confirmed');
        $this->assertNull($future->fresh()->confirmed_at);

        $this->assertSame(Trip::PLANNED, $pendingTrip->fresh()->status);
        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.flights', 2)
            ->where('stats.trips', 0)
            ->where('pendingTrips.0.id', $pendingTrip->id));

        // Corregir una confirmación errónea.
        $this->actingAs($this->user)->patch("/flights/{$standalone->id}/confirmation", ['confirmed' => false])->assertSessionHasNoErrors();
        $this->assertNull($standalone->fresh()->confirmed_at);
        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page->where('stats.flights', 1));
    }

    public function test_moving_a_confirmed_flight_to_the_future_removes_its_confirmation(): void
    {
        $flight = $this->flight(null, '2019-04-12');
        $flight->update(['confirmed_at' => now()]);

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page->where('stats.flights', 1));

        $this->actingAs($this->user)
            ->put("/flights/{$flight->id}", ['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->bcn->id, 'departure_date' => '2027-01-10'])
            ->assertSessionHasNoErrors();

        $this->assertNull($flight->fresh()->confirmed_at);
        $this->assertSame(Flight::UPCOMING, $flight->fresh()->load('trip', 'originAirport')->status());
        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.flights', 0)
            ->where('map.flights.0.status', 'upcoming'));
    }

    /*
    |--------------------------------------------------------------------------
    | Páginas: Flight History, Home, Passport y ficha del viaje
    |--------------------------------------------------------------------------
    */

    public function test_flight_history_sends_every_active_flight_with_its_status_and_trip(): void
    {
        $trip = $this->trip('2026-09-01', '2026-09-05', name: 'Barcelona');
        $flown = $this->flight($trip, '2026-09-01', '08:00');
        $pending = $this->flight(null, '2019-04-12');
        $upcoming = $this->flight(null, '2026-12-01');
        $this->flight($this->trip('2026-12-10', '2026-12-12', Trip::CANCELLED), '2026-12-10', '08:00');
        Trip::create(['user_id' => $this->user->id, 'name' => 'Old', 'start_date' => '2025-01-01', 'end_date' => '2025-01-02']);

        $this->actingAs($this->user)
            ->get('/flight-history')
            ->assertInertia(fn ($page) => $page
                ->has('flights', 3)
                ->where('flights', fn ($flights) => collect($flights)->pluck('status', 'id')->all() === [
                    $upcoming->id => 'upcoming',
                    $flown->id => 'flown',
                    $pending->id => 'pending',
                ])
                ->where('flights.1.trip', ['id' => $trip->id, 'name' => 'Barcelona'])
                ->where('flights.2.trip', null)
                ->where('flights.2.departure_date', '2019-04-12')
                ->where('flights.2.departure', null)
                ->where('flights.2.duration_minutes', null)
                ->where('flights.2.flight_number', null)
                ->where('flights.2.distance_km', 483)
                // Viajes para asociar: los del usuario, sin cancelados.
                ->where('trips', fn ($trips) => collect($trips)->pluck('name')->all() === ['Barcelona', 'Old'])
            );
    }

    public function test_statistics_only_count_flown_flights(): void
    {
        $this->flight($this->trip('2026-09-01', '2026-09-05'), '2026-09-01', '08:00', '09:20');
        $this->flight(null, '2019-04-12');
        $this->flight(null, '2026-12-01');

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.flights', 1)
            ->has('recentFlights', 1)
            // Mapa: realizado y próximo; el pendiente no se dibuja como realizado.
            ->where('map.flights', fn ($flights) => collect($flights)->pluck('status')->all() === ['flown', 'upcoming']));

        $this->actingAs($this->user)->get('/passport')->assertInertia(fn ($page) => $page
            ->has('flights', 1)
            ->where('flights.0.duration_minutes', 80)
            ->where('years', [2026]));
    }

    public function test_a_flight_without_coordinates_has_no_distance(): void
    {
        $nowhere = Airport::create(['city_id' => $this->mad->city_id, 'name' => 'No coordinates', 'iata_code' => 'NOC', 'timezone' => 'Europe/Madrid']);
        $flight = $this->flight(null, '2026-03-10', origin: $nowhere);

        $this->assertNull($flight->load('originAirport', 'destinationAirport')->distanceKm());

        $this->actingAs($this->user)->get('/flight-history')->assertInertia(fn ($page) => $page->where('flights.0.distance_km', null));
    }

    public function test_the_trip_page_lists_only_its_own_flights_with_their_status(): void
    {
        $trip = $this->trip('2026-10-10', '2026-10-20');
        $this->flight($trip, '2026-10-10', '08:00');
        $this->flight($trip, '2026-10-12');
        $this->flight(null, '2026-10-12', '08:00');

        $this->actingAs($this->user)
            ->get("/trips/{$trip->id}")
            ->assertInertia(fn ($page) => $page->where('flights', fn ($flights) => collect($flights)->pluck('status')->all() === ['flown', 'pending']));
    }

    public function test_the_nested_trip_routes_still_work_with_the_new_fields(): void
    {
        $trip = $this->trip('2026-09-01', '2026-09-30');

        $this->actingAs($this->user)
            ->post("/trips/{$trip->id}/flights", ['origin_airport_id' => $this->mad->id, 'destination_airport_id' => $this->bcn->id, 'departure_date' => '2026-09-10'])
            ->assertRedirect("/trips/{$trip->id}");

        // En las rutas del viaje, el viaje lo fija la URL (se ignora trip_id).
        $flight = Flight::sole();
        $this->assertSame($trip->id, $flight->trip_id);

        $this->actingAs($this->user)->delete("/trips/{$trip->id}/flights/{$flight->id}")->assertRedirect("/trips/{$trip->id}");
        $this->assertModelMissing($flight);
    }

    private function airport(City $city, string $iata, float $latitude, float $longitude, string $timezone): Airport
    {
        return Airport::create(['city_id' => $city->id, 'name' => "{$iata} Airport", 'iata_code' => $iata, 'latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezone]);
    }

    private function trip(string $start, string $end, ?string $status = null, string $name = 'Trip'): Trip
    {
        return Trip::create(array_filter(['user_id' => $this->user->id, 'name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => $status]));
    }

    // Vuelo MAD → BCN (o desde $origin) con fecha local y, si se indica, horas locales de Madrid.
    private function flight(?Trip $trip, string $date, ?string $departureTime = null, ?string $arrivalTime = null, ?Airport $origin = null): Flight
    {
        $origin ??= $this->mad;
        $local = fn (?string $time) => $time ? Carbon::parse("{$date} {$time}", 'Europe/Madrid')->utc() : null;

        return Flight::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip?->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $this->bcn->id,
            'departure_date' => $date,
            'departure_at' => $local($departureTime),
            'arrival_at' => $local($arrivalTime),
        ]);
    }
}
