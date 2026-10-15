<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bloque 2, F0: estado del viaje (planned, completed, cancelled) y fases
 * deducidas (upcoming, ongoing, pending).
 *
 * Reglas: solo los viajes completados cuentan como lugares visitados; un
 * viaje nunca se completa solo por la fecha; lo cancelado no cuenta. Los
 * vuelos ya hechos de un viaje en curso sí cuentan.
 *
 * Fecha fija: 15-10-2026 12:00 UTC (14:00 en Madrid).
 */
class TripStatusTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-15 12:00:00';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        config(['app.user_timezone' => 'Europe/Madrid']);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $this->user = User::factory()->create();
    }

    public function test_new_trips_are_completed_only_when_they_are_already_over(): void
    {
        $this->assertSame(Trip::COMPLETED, Trip::initialStatusFor('2026-10-14'));
        $this->assertSame(Trip::PLANNED, Trip::initialStatusFor('2026-10-15'));
        $this->assertSame(Trip::PLANNED, Trip::initialStatusFor('2027-01-01'));

        $this->actingAs($this->user)->post('/trips', ['name' => 'Past', 'start_date' => '2026-09-01', 'end_date' => '2026-09-05'])->assertRedirect();
        $this->actingAs($this->user)->post('/trips', ['name' => 'Today', 'start_date' => '2026-10-15', 'end_date' => '2026-10-15'])->assertRedirect();
        $this->actingAs($this->user)->post('/trips', ['name' => 'Future', 'start_date' => '2026-12-01', 'end_date' => '2026-12-05'])->assertRedirect();

        $this->assertSame(
            ['Future' => Trip::PLANNED, 'Past' => Trip::COMPLETED, 'Today' => Trip::PLANNED],
            Trip::orderBy('name')->pluck('status', 'name')->all(),
        );
    }

    public function test_phases_are_derived_from_status_and_dates(): void
    {
        $this->assertSame('upcoming', $this->trip('2026-10-16', '2026-10-20')->phase());
        $this->assertSame('ongoing', $this->trip('2026-10-10', '2026-10-15')->phase());
        $this->assertSame('pending', $this->trip('2026-10-01', '2026-10-05', Trip::PLANNED)->phase());
        $this->assertSame('completed', $this->trip('2026-10-01', '2026-10-05')->phase());
        $this->assertSame('cancelled', $this->trip('2026-10-16', '2026-10-20', Trip::CANCELLED)->phase());
    }

    public function test_a_planned_trip_is_never_completed_by_its_date(): void
    {
        $pending = $this->trip('2026-10-01', '2026-10-05', Trip::PLANNED);

        $this->travelTo(Carbon::parse('2027-06-01 12:00:00', 'UTC'));
        $this->actingAs($this->user)->get('/')->assertOk();
        $this->actingAs($this->user)->get("/trips/{$pending->id}")->assertOk();

        $this->assertSame(Trip::PLANNED, $pending->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->phase());
    }

    public function test_status_transitions(): void
    {
        $upcoming = $this->trip('2026-11-01', '2026-11-05');

        // No se puede completar un viaje que aún no ha empezado.
        $this->actingAs($this->user)
            ->patch("/trips/{$upcoming->id}/status", ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->assertSame(Trip::PLANNED, $upcoming->fresh()->status);

        $this->actingAs($this->user)->patch("/trips/{$upcoming->id}/status", ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(Trip::CANCELLED, $upcoming->fresh()->status);

        $this->actingAs($this->user)->patch("/trips/{$upcoming->id}/status", ['status' => 'planned'])->assertRedirect();
        $this->assertSame(Trip::PLANNED, $upcoming->fresh()->status);

        // En curso y pendiente sí se pueden completar; y deshacer.
        foreach ([$this->trip('2026-10-10', '2026-10-20'), $this->trip('2026-10-01', '2026-10-05', Trip::PLANNED)] as $started) {
            $this->actingAs($this->user)->patch("/trips/{$started->id}/status", ['status' => 'completed'])->assertSessionHasNoErrors();
            $this->assertSame(Trip::COMPLETED, $started->fresh()->status);

            $this->actingAs($this->user)->patch("/trips/{$started->id}/status", ['status' => 'planned'])->assertSessionHasNoErrors();
            $this->assertSame(Trip::PLANNED, $started->fresh()->status);
        }

        $this->actingAs($this->user)
            ->patch("/trips/{$upcoming->id}/status", ['status' => 'finished'])
            ->assertSessionHasErrors('status');
    }

    public function test_only_the_owner_can_change_the_status(): void
    {
        $trip = $this->trip('2026-11-01', '2026-11-05');

        $this->actingAs(User::factory()->create())
            ->patch("/trips/{$trip->id}/status", ['status' => 'cancelled'])
            ->assertForbidden();

        $this->post('/logout');
        $this->app['auth']->forgetGuards();
        $this->patch("/trips/{$trip->id}/status", ['status' => 'cancelled'])->assertRedirect('/login');

        $this->assertSame(Trip::PLANNED, $trip->fresh()->status);
    }

    public function test_a_completed_trip_cannot_be_moved_to_the_future(): void
    {
        $trip = $this->trip('2026-09-01', '2026-09-05');

        $this->actingAs($this->user)
            ->put("/trips/{$trip->id}", ['name' => 'Trip', 'start_date' => '2026-11-01', 'end_date' => '2026-11-05'])
            ->assertSessionHasErrors('start_date');

        $this->assertSame('2026-09-01', $trip->fresh()->start_date->toDateString());
    }

    public function test_only_completed_trips_count_as_visited_places(): void
    {
        [$madrid, $paris, $rome, $lisbon, $berlin] = [
            $this->city('Spain', 'ES', 'Madrid'),
            $this->city('France', 'FR', 'Paris'),
            $this->city('Italy', 'IT', 'Rome'),
            $this->city('Portugal', 'PT', 'Lisbon'),
            $this->city('Germany', 'DE', 'Berlin'),
        ];

        $completed = $this->trip('2026-09-01', '2026-09-05', name: 'Completed');
        $this->visit($completed, $madrid, '2026-09-01');
        $upcoming = $this->trip('2026-11-01', '2026-11-05', name: 'Upcoming');
        $this->visit($upcoming, $paris, '2026-11-01');
        $this->visit($upcoming, $madrid, '2026-11-03');
        $cancelled = $this->trip('2026-12-01', '2026-12-05', Trip::CANCELLED, 'Cancelled');
        $this->visit($cancelled, $rome, '2026-12-01');
        $ongoing = $this->trip('2026-10-14', '2026-10-18', name: 'Ongoing');
        $this->visit($ongoing, $lisbon, '2026-10-14');
        $pending = $this->trip('2026-10-01', '2026-10-05', Trip::PLANNED, 'Pending');
        $this->visit($pending, $berlin, '2026-10-01');

        // Otro usuario con un viaje completado en Italia: no cuenta aquí.
        $foreign = Trip::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);
        $this->visit($foreign, $rome, '2026-01-01');

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('stats.sovereign_countries', 1)
                ->where('stats.cities', 1)
                ->where('stats.trips', 1)
                ->where('stats.upcoming_trips', 1)
                ->where('map.visits', fn ($visits) => collect($visits)->pluck('city.iso_code')->all() === ['ES'])
                // Planificados en lima (Madrid también está planificado, pero lo visitado prevalece en el mapa).
                // Ordenados por nombre.
                ->where('map.planned_countries', [
                    ['iso_code' => 'FR', 'name' => 'France', 'ongoing' => false],
                    ['iso_code' => 'DE', 'name' => 'Germany', 'ongoing' => false],
                    ['iso_code' => 'PT', 'name' => 'Portugal', 'ongoing' => true],
                    ['iso_code' => 'ES', 'name' => 'Spain', 'ongoing' => false],
                ])
                ->where('nextTrip.id', $upcoming->id)
                ->where('pendingTrips', [['id' => $pending->id, 'name' => 'Pending', 'end_date' => '2026-10-05']])
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('phase', 'name')->all() === ['Ongoing' => 'ongoing', 'Pending' => 'pending', 'Completed' => 'completed'])
                ->where('tripsByYear', [['year' => 2026, 'count' => 1]])
            );

        $this->actingAs($this->user)
            ->get('/passport')
            ->assertInertia(fn ($page) => $page
                ->where('visits', fn ($visits) => collect($visits)->pluck('iso_code')->all() === ['ES'])
                ->where('trips', fn ($trips) => collect($trips)->pluck('name')->all() === ['Completed'])
                ->where('plannedCountries', fn ($planned) => collect($planned)->pluck('iso_code')->sort()->values()->all() === ['DE', 'ES', 'FR', 'PT'])
            );

        $this->actingAs($this->user)
            ->get('/countries')
            ->assertInertia(fn ($page) => $page
                ->where('countries', fn ($countries) => collect($countries)->pluck('iso_code')->all() === ['ES'])
                ->where('countries.0.trips_count', 1)
                // Solo planificados (España está visitada, así que no aparece aquí).
                ->where('plannedCountries', fn ($planned) => collect($planned)->pluck('iso_code')->all() === ['FR', 'DE', 'PT'])
            );

        $spain = Country::where('iso_code', 'ES')->first();
        $this->actingAs($this->user)
            ->get("/countries/{$spain->id}")
            ->assertInertia(fn ($page) => $page
                ->where('cities', [['id' => $madrid->id, 'name' => 'Madrid', 'visits_count' => 1, 'planned' => true]])
                ->where('trips', fn ($trips) => collect($trips)->pluck('phase', 'name')->all() === ['Upcoming' => 'upcoming', 'Completed' => 'completed'])
            );

        $this->actingAs($this->user)
            ->get('/profile')
            ->assertInertia(fn ($page) => $page->where('countries', fn ($countries) => collect($countries)->pluck('iso_code')->all() === ['ES']));

        $this->actingAs($this->user)
            ->get('/trips')
            ->assertInertia(fn ($page) => $page
                ->where('trips', fn ($trips) => collect($trips)->mapWithKeys(fn ($trip) => [$trip['name'] => [$trip['status'], $trip['phase']]])->sortKeys()->all() === [
                    'Cancelled' => ['cancelled', 'cancelled'],
                    'Completed' => ['completed', 'completed'],
                    'Ongoing' => ['planned', 'ongoing'],
                    'Pending' => ['planned', 'pending'],
                    'Upcoming' => ['planned', 'upcoming'],
                ])
            );

        $this->actingAs($this->user)
            ->get("/trips/{$pending->id}")
            ->assertInertia(fn ($page) => $page
                ->where('trip.status', 'planned')
                ->where('trip.phase', 'pending')
                ->where('trip.can_complete', true)
            );

        $this->actingAs($this->user)
            ->get("/trips/{$upcoming->id}")
            ->assertInertia(fn ($page) => $page->where('trip.can_complete', false));
    }

    public function test_cancelled_trips_are_neither_recent_nor_next(): void
    {
        // Cancelados que, por fecha, serían el viaje reciente y el próximo.
        $this->trip('2026-10-10', '2026-10-12', Trip::CANCELLED, 'Cancelled past');
        $this->trip('2026-10-20', '2026-10-22', Trip::CANCELLED, 'Cancelled soon');
        $past = $this->trip('2026-09-01', '2026-09-05', name: 'Past');
        $later = $this->trip('2026-11-01', '2026-11-05', name: 'Later');

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('id')->all() === [$past->id])
                ->where('nextTrip.id', $later->id)
                ->where('stats.trips', 1)
                ->where('stats.upcoming_trips', 1)
            );
    }

    public function test_completing_a_trip_turns_its_places_into_visited_places(): void
    {
        $pending = $this->trip('2026-10-01', '2026-10-05', Trip::PLANNED);
        $this->visit($pending, $this->city('Germany', 'DE', 'Berlin'), '2026-10-01');

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page->where('stats.sovereign_countries', 0));

        $this->actingAs($this->user)->patch("/trips/{$pending->id}/status", ['status' => 'completed'])->assertRedirect();

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('stats.sovereign_countries', 1)
                ->where('map.planned_countries', [])
                ->where('pendingTrips', [])
            );
    }

    public function test_flight_status_follows_its_trip(): void
    {
        $mad = $this->airport('MAD', 'Europe/Madrid', $this->city('Spain', 'ES', 'Madrid'));
        $cdg = $this->airport('CDG', 'Europe/Paris', $this->city('France', 'FR', 'Paris'));

        $completed = $this->flight($this->trip('2026-09-01', '2026-09-05'), $mad, $cdg, '2026-09-01 08:00:00', 'C1');
        // Viaje en curso: el vuelo de ida ya salió (cuenta); el de vuelta, aún no.
        $ongoing = $this->trip('2026-10-14', '2026-10-18');
        $outbound = $this->flight($ongoing, $mad, $cdg, '2026-10-14 08:00:00', 'O1');
        $return = $this->flight($ongoing, $cdg, $mad, '2026-10-18 18:00:00', 'O2');
        // Pendiente de confirmar: el vuelo ya hecho sigue contando.
        $pendingFlight = $this->flight($this->trip('2026-10-01', '2026-10-05', Trip::PLANNED), $mad, $cdg, '2026-10-01 08:00:00', 'P1');
        $cancelled = $this->flight($this->trip('2026-12-01', '2026-12-05', Trip::CANCELLED), $mad, $cdg, '2026-12-01 08:00:00', 'X1');

        $statuses = Flight::with('trip')->get()->mapWithKeys(fn ($flight) => [$flight->flight_number => $flight->status()])->sortKeys()->all();
        $this->assertSame(['C1' => 'flown', 'O1' => 'flown', 'O2' => 'upcoming', 'P1' => 'flown', 'X1' => 'cancelled'], $statuses);
        $this->assertSame(['C1', 'O1', 'P1'], $this->flownFlights()->sort()->values()->all());

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('stats.flights', 3)
                ->where('recentFlights', fn ($recent) => collect($recent)->pluck('id')->all() === [$outbound->id, $pendingFlight->id, $completed->id])
                ->where('map.flights', fn ($flights) => collect($flights)->pluck('status', 'id')->sortKeys()->all() === collect([$completed->id => 'flown', $outbound->id => 'flown', $return->id => 'upcoming', $pendingFlight->id => 'flown'])->sortKeys()->all())
            );

        $this->actingAs($this->user)
            ->get('/flight-history')
            ->assertInertia(fn ($page) => $page
                ->has('flights', 4)
                ->where('flights', fn ($flights) => ! collect($flights)->pluck('id')->contains($cancelled->id))
                ->where('flights.0.id', $return->id)
                ->where('flights.0.status', 'upcoming')
            );

        $this->actingAs($this->user)
            ->get('/passport')
            ->assertInertia(fn ($page) => $page
                ->where('flights', fn ($flights) => collect($flights)->pluck('status')->unique()->values()->all() === ['flown'] && count($flights) === 3)
            );

        $this->actingAs($this->user)
            ->get("/trips/{$ongoing->id}")
            ->assertInertia(fn ($page) => $page->where('flights', fn ($flights) => collect($flights)->pluck('status')->all() === ['flown', 'upcoming']));
    }

    public function test_forbidden_status_transitions_are_rejected_and_leave_the_status_unchanged(): void
    {
        // [estado inicial, destino]: completed → cancelled y cancelled → completed
        // (este último aunque el viaje ya haya empezado), y repetir el mismo estado.
        $cases = [
            [Trip::COMPLETED, Trip::CANCELLED],
            [Trip::CANCELLED, Trip::COMPLETED],
            [Trip::PLANNED, Trip::PLANNED],
            [Trip::COMPLETED, Trip::COMPLETED],
            [Trip::CANCELLED, Trip::CANCELLED],
        ];

        foreach ($cases as [$from, $to]) {
            $trip = $this->trip('2026-10-01', '2026-10-05', $from);

            $this->actingAs($this->user)
                ->patch("/trips/{$trip->id}/status", ['status' => $to])
                ->assertSessionHasErrors(['status' => "A {$from} trip cannot be changed to {$to}."]);

            $this->assertSame($from, $trip->fresh()->status, "{$from} → {$to}");
        }
    }

    public function test_a_cancelled_trip_offers_restore_but_not_complete(): void
    {
        $cancelled = $this->trip('2026-10-01', '2026-10-05', Trip::CANCELLED);
        $started = $this->trip('2026-10-10', '2026-10-20');

        $this->actingAs($this->user)
            ->get("/trips/{$cancelled->id}")
            ->assertInertia(fn ($page) => $page->where('trip.phase', 'cancelled')->where('trip.can_complete', false));

        $this->actingAs($this->user)
            ->get("/trips/{$started->id}")
            ->assertInertia(fn ($page) => $page->where('trip.can_complete', true));
    }

    public function test_t3_a_future_flight_of_a_trip_that_has_not_started_is_upcoming(): void
    {
        [$mad, $cdg] = $this->airports();
        $flight = $this->flight($this->trip('2026-11-01', '2026-11-05'), $mad, $cdg, '2026-11-01 08:00:00', 'U1');

        $this->assertSame(Flight::UPCOMING, $flight->load('trip')->status());
        $this->assertSame([], $this->flownFlights()->all());

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.flights', 0)
            ->where('recentFlights', [])
            ->where('map.flights.0.id', $flight->id)
            ->where('map.flights.0.status', 'upcoming'));

        $this->actingAs($this->user)->get('/flight-history')->assertInertia(fn ($page) => $page
            ->has('flights', 1)
            ->where('flights.0.status', 'upcoming'));

        $this->actingAs($this->user)->get('/passport')->assertInertia(fn ($page) => $page->where('flights', []));
    }

    public function test_t4_a_past_flight_of_a_cancelled_trip_appears_nowhere(): void
    {
        [$mad, $cdg] = $this->airports();
        $trip = $this->trip('2026-09-01', '2026-09-05', Trip::CANCELLED);
        $flight = $this->flight($trip, $mad, $cdg, '2026-09-01 08:00:00', 'X2');

        $this->assertSame(Flight::CANCELLED, $flight->load('trip')->status());
        $this->assertSame([], $this->flownFlights()->all());

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.flights', 0)
            ->where('recentFlights', [])
            ->where('map.flights', []));

        $this->actingAs($this->user)->get('/flight-history')->assertInertia(fn ($page) => $page->where('flights', []));
        $this->actingAs($this->user)->get('/passport')->assertInertia(fn ($page) => $page->where('flights', [])->where('years', []));

        // En la ficha de su propio viaje sigue visible, marcado como cancelado (gris en el mapa).
        $this->actingAs($this->user)->get("/trips/{$trip->id}")->assertInertia(fn ($page) => $page->where('flights.0.status', 'cancelled'));
    }

    public function test_t5_a_past_standalone_flight_stays_pending_until_confirmed(): void
    {
        // El esquema ya permite trip_id nulo; la interfaz para crearlos llega en F1.
        [$mad, $cdg] = $this->airports();
        $flight = Flight::create([
            'user_id' => $this->user->id,
            'trip_id' => null,
            'origin_airport_id' => $mad->id,
            'destination_airport_id' => $cdg->id,
            'flight_number' => 'S1',
            'departure_at' => '2026-05-01 08:00:00',
            'arrival_at' => '2026-05-01 10:00:00',
        ]);

        $this->assertSame(Flight::PENDING, $flight->load('trip')->status());
        $this->assertSame([], $this->flownFlights()->all());

        // No cuenta y no se dibuja como realizado en el mapa de la Home.
        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.flights', 0)
            ->where('recentFlights', [])
            ->where('map.flights', []));

        // En el historial aparece, marcado como pendiente ("Unconfirmed").
        $this->actingAs($this->user)->get('/flight-history')->assertInertia(fn ($page) => $page
            ->has('flights', 1)
            ->where('flights.0.id', $flight->id)
            ->where('flights.0.status', 'pending'));

        $this->actingAs($this->user)->get('/passport')->assertInertia(fn ($page) => $page->where('flights', []));
    }

    public function test_recent_photos_skip_cancelled_trips_without_touching_the_photos(): void
    {
        $completed = $this->photo($this->trip('2026-09-01', '2026-09-05'), 'completed.jpg');
        $planned = $this->photo($this->trip('2026-11-01', '2026-11-05'), 'planned.jpg');
        $ongoing = $this->photo($this->trip('2026-10-10', '2026-10-20'), 'ongoing.jpg');
        $loose = $this->photo(null, 'no-trip.jpg');
        $cancelledTrip = $this->trip('2026-08-01', '2026-08-05', Trip::CANCELLED);
        $cancelled = $this->photo($cancelledTrip, 'cancelled.jpg');

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('recentPhotos', fn ($photos) => collect($photos)->pluck('id')->sort()->values()->all()
                === collect([$completed->id, $planned->id, $ongoing->id, $loose->id])->sort()->values()->all()));

        // La foto sigue existiendo y asociada a su viaje.
        $this->assertSame($cancelledTrip->id, $cancelled->fresh()->trip_id);
    }

    // F1 (D6): una sola definición del estado (Flight::status()), sin scope SQL.
    private function flownFlights()
    {
        return Flight::withStatuses(Flight::with(['trip', 'originAirport'])->get())
            ->where('flight_status', Flight::FLOWN)
            ->pluck('flight_number')
            ->values();
    }

    private function trip(string $start, string $end, ?string $status = null, string $name = 'Trip'): Trip
    {
        return Trip::create(array_filter([
            'user_id' => $this->user->id,
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
        ]));
    }

    /**
     * @return array{Airport, Airport}
     */
    private function airports(): array
    {
        return [
            $this->airport('MAD', 'Europe/Madrid', $this->city('Spain', 'ES', 'Madrid')),
            $this->airport('CDG', 'Europe/Paris', $this->city('France', 'FR', 'Paris')),
        ];
    }

    private function photo(?Trip $trip, string $filename): Photo
    {
        $directory = 'photos/' . bin2hex(random_bytes(4));

        return Photo::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip?->id,
            'path' => "{$directory}/full.webp",
            'thumbnail_path' => "{$directory}/thumb.webp",
            'original_filename' => $filename,
            'processing_status' => 'ready',
        ]);
    }

    private function city(string $countryName, string $iso, string $name): City
    {
        $country = Country::firstOrCreate(['iso_code' => $iso], ['name' => $countryName]);

        return City::firstOrCreate(['country_id' => $country->id, 'name' => $name], ['latitude' => 1, 'longitude' => 1]);
    }

    private function visit(Trip $trip, City $city, string $date): Visit
    {
        return Visit::create(['user_id' => $trip->user_id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => $date, 'visited_until' => $date]);
    }

    private function airport(string $iata, string $timezone, City $city): Airport
    {
        return Airport::create(['city_id' => $city->id, 'name' => "{$iata} Airport", 'iata_code' => $iata, 'latitude' => 40, 'longitude' => 2, 'timezone' => $timezone]);
    }

    private function flight(Trip $trip, Airport $origin, Airport $destination, string $departure, string $number): Flight
    {
        return Flight::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'flight_number' => $number,
            'departure_at' => $departure,
            'arrival_at' => Carbon::parse($departure)->addHours(2),
        ]);
    }
}
