<?php

namespace Tests\Feature;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripFlightService;
use App\Support\StandaloneFlightRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Vuelos existentes en un viaje (App\Services\TripFlightService).
 *
 * - Sugerencias: vuelos independientes del usuario con salida entre el
 *   inicio −1 día y el fin +1 día. Nunca los de otro usuario ni los que ya
 *   están en un viaje.
 * - Asociar y quitar: solo vuelos propios en viajes propios; quitar no borra
 *   el vuelo y, si ya contaba como realizado, lo deja confirmado.
 * - Fuera de las fechas del viaje se avisa, no se bloquea (D3).
 *
 * Fecha fija: 15-10-2026 12:00 UTC.
 */
class TripFlightLinkingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Airport $mad;

    private Airport $lis;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        config(['app.user_timezone' => 'Europe/Madrid']);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'UTC'));

        $this->user = User::factory()->create();

        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $portugal = Country::create(['name' => 'Portugal', 'iso_code' => 'PT']);
        $this->mad = Airport::create(['city_id' => City::create(['country_id' => $spain->id, 'name' => 'Madrid'])->id, 'name' => 'Barajas', 'iata_code' => 'MAD', 'icao_code' => 'LEMD', 'timezone' => 'Europe/Madrid']);
        $this->lis = Airport::create(['city_id' => City::create(['country_id' => $portugal->id, 'name' => 'Lisbon'])->id, 'name' => 'Humberto Delgado', 'iata_code' => 'LIS', 'icao_code' => 'LPPT', 'timezone' => 'Europe/Lisbon']);

        $this->trip = $this->trip($this->user, '2026-05-10', '2026-05-20');
    }

    /*
    |--------------------------------------------------------------------------
    | Sugerencias
    |--------------------------------------------------------------------------
    */

    public function test_suggestions_include_standalone_flights_within_one_day_of_the_trip(): void
    {
        $before = $this->flight('2026-05-08', 'OUT1');   // dos días antes: fuera del margen
        $eve = $this->flight('2026-05-09', 'IN1');       // víspera: dentro del margen
        $inside = $this->flight('2026-05-15', 'IN2');
        $after = $this->flight('2026-05-21', 'IN3');     // día siguiente al fin: dentro del margen
        $late = $this->flight('2026-05-22', 'OUT2');     // dos días después: fuera

        $this->actingAs($this->user)
            ->getJson('/flights/suggestions?start_date=2026-05-10&end_date=2026-05-20')
            ->assertOk()
            ->assertJsonPath('flights.*.id', [$eve->id, $inside->id, $after->id])
            ->assertJsonPath('flights.0.flight_number', 'IN1')
            ->assertJsonPath('flights.0.origin.code', 'MAD');

        $this->assertNotContains($before->id, $this->suggestedIds());
        $this->assertNotContains($late->id, $this->suggestedIds());
    }

    public function test_suggestions_never_include_flights_of_other_users_or_of_a_trip(): void
    {
        $own = $this->flight('2026-05-12', 'OWN');
        $this->flight('2026-05-12', 'INTRIP', $this->trip);
        $this->flight('2026-05-12', 'OTHERTRIP', $this->trip($this->user, '2026-05-11', '2026-05-13'));

        $other = User::factory()->create();
        $this->flight('2026-05-12', 'FOREIGN', null, $other);

        $this->assertSame([$own->id], $this->suggestedIds());
    }

    public function test_suggestions_need_a_valid_date_range(): void
    {
        $this->actingAs($this->user)
            ->getJson('/flights/suggestions?start_date=2026-05-20&end_date=2026-05-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_search_finds_standalone_flights_by_route_airport_city_number_and_date(): void
    {
        $outbound = $this->flight('2026-03-01', 'TP1027');
        $return = $this->flight('2026-03-05', 'TP1028', null, null, $this->lis, $this->mad);
        $this->flight('2026-03-03', 'INTRIP', $this->trip);
        $this->flight('2026-03-01', 'FOREIGN', null, User::factory()->create());

        $search = fn (array $query) => $this->actingAs($this->user)
            ->getJson('/flights/standalone?' . http_build_query($query))
            ->assertOk()
            ->json('flights.*.id');

        // Ruta con sentido: origen y destino, en ese orden.
        $this->assertSame([$outbound->id], $search(['q' => 'MAD LIS']));
        $this->assertSame([$return->id], $search(['q' => 'lis-mad']));
        // Un aeropuerto (IATA, ICAO o ciudad) en cualquier extremo; los más recientes primero.
        $this->assertSame([$return->id, $outbound->id], $search(['q' => 'LPPT']));
        $this->assertSame([$return->id, $outbound->id], $search(['q' => 'Lisb']));
        $this->assertSame([$return->id], $search(['q' => 'tp1028']));
        $this->assertSame([$outbound->id], $search(['date' => '2026-03-01']));
        $this->assertSame([$return->id, $outbound->id], $search([]));
    }

    /*
    |--------------------------------------------------------------------------
    | Asociar y quitar
    |--------------------------------------------------------------------------
    */

    public function test_the_owner_attaches_existing_flights_to_a_trip(): void
    {
        $first = $this->flight('2026-05-10', 'A1');
        $second = $this->flight('2026-05-20', 'A2');

        $this->actingAs($this->user)
            ->post("/trips/{$this->trip->id}/flights/attach", ['flight_ids' => [$first->id, $second->id]])
            ->assertRedirect(route('trips.show', $this->trip))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->trip->id, $first->fresh()->trip_id);
        $this->assertSame($this->trip->id, $second->fresh()->trip_id);
    }

    public function test_a_flight_outside_the_trip_dates_is_attached_with_a_warning(): void
    {
        $eve = $this->flight('2026-05-09', 'EVE');
        $inside = $this->flight('2026-05-12', 'IN');

        $this->actingAs($this->user)
            ->post("/trips/{$this->trip->id}/flights/attach", ['flight_ids' => [$eve->id, $inside->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->trip->id, $eve->fresh()->trip_id);

        $this->actingAs($this->user)
            ->get("/trips/{$this->trip->getRouteKey()}")
            ->assertInertia(fn ($page) => $page
                ->where('flights.0.flight_number', 'EVE')
                ->where('flights.0.outside_trip_dates', true)
                ->where('flights.1.flight_number', 'IN')
                ->where('flights.1.outside_trip_dates', false));
    }

    public function test_a_flight_already_in_another_trip_cannot_be_attached(): void
    {
        $other = $this->trip($this->user, '2026-05-01', '2026-05-30');
        $flight = $this->flight('2026-05-12', 'TAKEN', $other);

        $this->actingAs($this->user)
            ->post("/trips/{$this->trip->id}/flights/attach", ['flight_ids' => [$flight->id]])
            ->assertSessionHasErrors(['flight_ids.0' => StandaloneFlightRules::UNAVAILABLE]);

        $this->assertSame($other->id, $flight->fresh()->trip_id);
    }

    public function test_attaching_is_all_or_nothing_when_a_flight_was_taken_meanwhile(): void
    {
        // Las dos peticiones validaron con el vuelo libre; la primera ya lo asoció.
        $taken = $this->flight('2026-05-12', 'TAKEN');
        $free = $this->flight('2026-05-13', 'FREE');
        $first = $this->trip($this->user, '2026-05-01', '2026-05-30');
        app(TripFlightService::class)->attach($first, [$taken->id]);

        try {
            app(TripFlightService::class)->attach($this->trip, [$free->id, $taken->id]);
            $this->fail('The second attach should fail.');
        } catch (ValidationException $e) {
            // El índice es el del vuelo perdido en la lista enviada.
            $this->assertSame(['flight_ids.1' => [StandaloneFlightRules::UNAVAILABLE]], $e->errors());
        }

        $this->assertSame($first->id, $taken->fresh()->trip_id);
        $this->assertNull($free->fresh()->trip_id);
    }

    /**
     * La carrera por HTTP: otra petición se lleva el vuelo después de que esta
     * lo haya validado y antes de guardarlo.
     *
     * @return array<string, array{string}>
     */
    public static function attachRoutes(): array
    {
        return ['create trip' => ['store'], 'edit trip' => ['update'], 'trip page' => ['attach']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('attachRoutes')]
    public function test_a_flight_taken_after_validation_is_reported_and_nothing_is_saved(string $route): void
    {
        $flight = $this->flight('2026-05-12', 'RACE');
        $winner = $this->trip($this->user, '2026-05-01', '2026-05-30');

        // La otra petición termina después de que esta valide y antes de que
        // guarde: al resolver el FormRequest (ya validado) o, en la ficha, que
        // valida en el controlador, justo antes de asociar.
        $otherRequestWins = fn () => Flight::whereKey($flight->id)->update(['trip_id' => $winner->id]);

        if ($route === 'attach') {
            $this->app->instance(TripFlightService::class, new class($otherRequestWins) extends TripFlightService
            {
                public function __construct(private \Closure $before)
                {
                }

                public function attach(Trip $trip, iterable $flightIds): int
                {
                    ($this->before)();

                    return parent::attach($trip, $flightIds);
                }
            });
        } else {
            $this->app->afterResolving($route === 'store' ? StoreTripRequest::class : UpdateTripRequest::class, $otherRequestWins);
        }

        $payload = ['name' => 'Second', 'start_date' => '2026-05-10', 'end_date' => '2026-05-20', 'flight_ids' => [$flight->id]];
        $request = $this->actingAs($this->user)->from('/trips/create');

        $response = match ($route) {
            'store' => $request->post('/trips', $payload),
            'update' => $request->put("/trips/{$this->trip->id}", $payload),
            'attach' => $request->post("/trips/{$this->trip->id}/flights/attach", ['flight_ids' => [$flight->id]]),
        };

        $response->assertSessionHasErrors(['flight_ids.0' => StandaloneFlightRules::UNAVAILABLE]);

        // Se queda con el viaje que llegó primero y la otra petición no guarda nada.
        $this->assertSame($winner->id, $flight->fresh()->trip_id);
        $this->assertDatabaseMissing('trips', ['name' => 'Second']);
    }

    public function test_removing_a_flight_from_a_trip_keeps_it_as_standalone(): void
    {
        // Viaje planificado: el vuelo no contaba como realizado y no se confirma.
        $trip = $this->trip($this->user, '2026-11-01', '2026-11-05');
        $flight = $this->flight('2026-11-01', 'KEEP', $trip);

        $this->actingAs($this->user)
            ->post("/trips/{$trip->id}/flights/{$flight->id}/detach")
            ->assertRedirect(route('trips.show', $trip));

        $flight->refresh();
        $this->assertModelExists($flight);
        $this->assertNull($flight->trip_id);
        $this->assertNull($flight->confirmed_at);
        $this->assertSame('KEEP', $flight->flight_number);
        $this->assertModelExists($trip);
    }

    /**
     * Las dos formas de quitar el viaje a un vuelo: "Remove from trip" en la
     * ficha y "No trip" en el formulario de Flight History.
     *
     * @return array<string, array{string}>
     */
    public static function removalRoutes(): array
    {
        return ['remove from trip' => ['detach'], 'no trip in flight history' => ['no-trip']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('removalRoutes')]
    public function test_both_removal_routes_keep_a_flown_flight_confirmed(string $route): void
    {
        $flight = $this->flight('2026-05-12', 'FLOWN', $this->trip);
        $this->assertSame(Trip::COMPLETED, $this->trip->status);

        $this->removeFromTrip($route, $this->trip, $flight)->assertSessionHasNoErrors();

        $flight = $flight->fresh()->load(['trip', 'originAirport']);
        $this->assertNull($flight->trip_id);
        $this->assertNotNull($flight->confirmed_at);
        $this->assertSame(Flight::FLOWN, $flight->status());
        $this->assertSame('FLOWN', $flight->flight_number);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('removalRoutes')]
    public function test_both_removal_routes_leave_a_flight_that_had_not_counted_unconfirmed(string $route): void
    {
        // Viaje planificado que ya terminó (pendiente de confirmar): su vuelo
        // sin hora aún no contaba como realizado.
        $planned = Trip::create(['user_id' => $this->user->id, 'name' => 'Pending', 'start_date' => '2026-09-01', 'end_date' => '2026-09-05', 'status' => Trip::PLANNED]);
        $pending = $this->flight('2026-09-02', 'PEND', $planned);

        // Viaje cancelado: sus vuelos no cuentan y no se confirman.
        $cancelled = Trip::create(['user_id' => $this->user->id, 'name' => 'Cancelled', 'start_date' => '2026-06-01', 'end_date' => '2026-06-05', 'status' => Trip::CANCELLED]);
        $void = $this->flight('2026-06-02', 'VOID', $cancelled);

        $this->removeFromTrip($route, $planned, $pending)->assertSessionHasNoErrors();
        $this->removeFromTrip($route, $cancelled, $void)->assertSessionHasNoErrors();

        foreach ([$pending, $void] as $flight) {
            $this->assertNull($flight->fresh()->trip_id);
            $this->assertNull($flight->fresh()->confirmed_at);
        }
    }

    public function test_moving_a_released_flight_to_the_future_drops_the_confirmation(): void
    {
        // "No trip" y nueva fecha futura a la vez: D7 manda sobre la confirmación.
        $flight = $this->flight('2026-05-12', 'MOVED', $this->trip);

        $this->actingAs($this->user)
            ->put("/flights/{$flight->id}", $this->flightPayload($flight, ['departure_date' => '2026-12-01']))
            ->assertSessionHasNoErrors();

        $this->assertNull($flight->fresh()->trip_id);
        $this->assertNull($flight->fresh()->confirmed_at);
    }

    public function test_a_removed_flight_can_be_suggested_again(): void
    {
        $flight = $this->flight('2026-05-12', 'BACK', $this->trip);
        $this->assertSame([], $this->suggestedIds());

        $this->actingAs($this->user)->post("/trips/{$this->trip->id}/flights/{$flight->id}/detach");

        $this->assertSame([$flight->id], $this->suggestedIds());
    }

    /*
    |--------------------------------------------------------------------------
    | Crear y editar el viaje con vuelos sugeridos
    |--------------------------------------------------------------------------
    */

    public function test_accepted_suggestions_are_attached_when_creating_a_trip(): void
    {
        $accepted = $this->flight('2026-07-01', 'YES');
        $dismissed = $this->flight('2026-07-02', 'NO');

        $this->actingAs($this->user)
            ->post('/trips', ['name' => 'Balkans 2026', 'start_date' => '2026-07-02', 'end_date' => '2026-07-09', 'flight_ids' => [$accepted->id]])
            ->assertSessionHasNoErrors();

        $trip = Trip::where('name', 'Balkans 2026')->firstOrFail();
        $this->assertSame($trip->id, $accepted->fresh()->trip_id);
        // Lo descartado no se guarda: sigue suelto y vuelve a sugerirse.
        $this->assertNull($dismissed->fresh()->trip_id);
        $this->assertSame([$dismissed->id], $this->suggestedIds('2026-07-02', '2026-07-09'));
    }

    public function test_accepted_suggestions_are_attached_when_editing_a_trip(): void
    {
        $flight = $this->flight('2026-05-21', 'LATE');

        $this->actingAs($this->user)
            ->put("/trips/{$this->trip->id}", ['name' => 'Lisbon', 'start_date' => '2026-05-10', 'end_date' => '2026-05-20', 'flight_ids' => [$flight->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->trip->id, $flight->fresh()->trip_id);
    }

    public function test_a_trip_is_not_created_with_flights_of_another_user(): void
    {
        $foreign = $this->flight('2026-07-03', 'FOREIGN', null, User::factory()->create());

        $this->actingAs($this->user)
            ->post('/trips', ['name' => 'Nope', 'start_date' => '2026-07-02', 'end_date' => '2026-07-09', 'flight_ids' => [$foreign->id]])
            ->assertSessionHasErrors('flight_ids.0');

        $this->assertDatabaseMissing('trips', ['name' => 'Nope']);
        $this->assertNull($foreign->fresh()->trip_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Autorización
    |--------------------------------------------------------------------------
    */

    public function test_only_own_flights_can_be_attached_to_own_trips(): void
    {
        $intruder = User::factory()->create();
        $foreignFlight = $this->flight('2026-05-12', 'FOREIGN', null, $intruder);
        $ownFlight = $this->flight('2026-05-12', 'OWN');

        // Un vuelo ajeno en un viaje propio: no pasa la validación.
        $this->actingAs($this->user)
            ->post("/trips/{$this->trip->id}/flights/attach", ['flight_ids' => [$foreignFlight->id]])
            ->assertSessionHasErrors('flight_ids.0');

        // Un vuelo propio en un viaje ajeno: prohibido.
        $foreignTrip = $this->trip($intruder, '2026-05-10', '2026-05-20');
        $this->actingAs($this->user)
            ->post("/trips/{$foreignTrip->id}/flights/attach", ['flight_ids' => [$ownFlight->id]])
            ->assertForbidden();

        $this->assertNull($foreignFlight->fresh()->trip_id);
        $this->assertNull($ownFlight->fresh()->trip_id);
    }

    public function test_only_the_owner_can_remove_a_flight_from_a_trip(): void
    {
        $flight = $this->flight('2026-05-12', 'MINE', $this->trip);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post("/trips/{$this->trip->id}/flights/{$flight->id}/detach")
            ->assertForbidden();

        // Un vuelo que no es de ese viaje no existe en su URL.
        $loose = $this->flight('2026-05-12', 'LOOSE');
        $this->actingAs($this->user)
            ->post("/trips/{$this->trip->id}/flights/{$loose->id}/detach")
            ->assertNotFound();

        $this->assertSame($this->trip->id, $flight->fresh()->trip_id);
    }

    public function test_suggestions_and_search_require_a_session(): void
    {
        $this->get('/flights/suggestions?start_date=2026-05-10&end_date=2026-05-20')->assertRedirect('/login');
        $this->get('/flights/standalone?q=MAD')->assertRedirect('/login');
    }

    public function test_the_demo_account_cannot_attach_or_remove_flights(): void
    {
        config(['demo.email' => 'demo@vyamap.test']);
        $demo = User::factory()->create(['email' => 'demo@vyamap.test']);
        $trip = $this->trip($demo, '2026-05-10', '2026-05-20');
        $loose = $this->flight('2026-05-12', 'LOOSE', null, $demo);
        $linked = $this->flight('2026-05-13', 'LINKED', $trip, $demo);

        $this->actingAs($demo)
            ->post("/trips/{$trip->id}/flights/attach", ['flight_ids' => [$loose->id]])
            ->assertSessionHas('demo_notice');

        $this->actingAs($demo)
            ->post("/trips/{$trip->id}/flights/{$linked->id}/detach")
            ->assertSessionHas('demo_notice');

        $this->assertNull($loose->fresh()->trip_id);
        $this->assertSame($trip->id, $linked->fresh()->trip_id);

        // Leer las sugerencias sí puede (no escribe nada).
        $this->actingAs($demo)
            ->getJson('/flights/suggestions?start_date=2026-05-10&end_date=2026-05-20')
            ->assertOk()
            ->assertJsonPath('flights.*.id', [$loose->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Utilidades
    |--------------------------------------------------------------------------
    */

    private function removeFromTrip(string $route, Trip $trip, Flight $flight): \Illuminate\Testing\TestResponse
    {
        return $route === 'detach'
            ? $this->actingAs($this->user)->post("/trips/{$trip->id}/flights/{$flight->id}/detach")
            : $this->actingAs($this->user)->from('/flight-history')->put("/flights/{$flight->id}", $this->flightPayload($flight));
    }

    // Formulario de Flight History con los datos del vuelo y "No trip".
    private function flightPayload(Flight $flight, array $overrides = []): array
    {
        return [
            'origin_airport_id' => $flight->origin_airport_id,
            'destination_airport_id' => $flight->destination_airport_id,
            'departure_date' => $flight->departure_date->toDateString(),
            'flight_number' => $flight->flight_number,
            'trip_id' => null,
            ...$overrides,
        ];
    }

    /** @return list<int> */
    private function suggestedIds(string $start = '2026-05-10', string $end = '2026-05-20'): array
    {
        return $this->actingAs($this->user)
            ->getJson("/flights/suggestions?start_date={$start}&end_date={$end}")
            ->assertOk()
            ->json('flights.*.id');
    }

    private function trip(User $user, string $start, string $end): Trip
    {
        return Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => $start, 'end_date' => $end]);
    }

    private function flight(string $date, string $number, ?Trip $trip = null, ?User $user = null, ?Airport $from = null, ?Airport $to = null): Flight
    {
        return Flight::create([
            'user_id' => ($user ?? $this->user)->id,
            'trip_id' => $trip?->id,
            'origin_airport_id' => ($from ?? $this->mad)->id,
            'destination_airport_id' => ($to ?? $this->lis)->id,
            'departure_date' => $date,
            'flight_number' => $number,
        ]);
    }
}
