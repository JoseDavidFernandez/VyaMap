<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Services\TravelStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Estadísticas de viaje (F3, App\Services\TravelStatistics) y su llegada a
 * las páginas (F4): días, países y primeras visitas por año, continentes y
 * cifras de vuelos. Ver las reglas en el docblock del servicio.
 */
class TravelStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TravelStatistics $statistics;

    /** @var array<string, City> */
    private array $cities = [];

    /** @var array<string, Airport> */
    private array $airports = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        config(['app.user_timezone' => 'Europe/Madrid']);
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'UTC'));

        $this->user = User::factory()->create();
        $this->statistics = app(TravelStatistics::class);

        foreach ([
            ['ES', 'Spain', 'Madrid'], ['GB', 'United Kingdom', 'London'], ['FR', 'France', 'Paris'],
            ['MA', 'Morocco', 'Marrakech'], ['GI', 'Gibraltar', 'Gibraltar'], ['ZZ', 'Unknown', 'Somewhere'],
            ['US', 'United States', 'New York'],
        ] as [$iso, $country, $city]) {
            $this->cities[$iso] = City::create(['country_id' => Country::create(['name' => $country, 'iso_code' => $iso])->id, 'name' => $city]);
        }

        $this->airports = [
            'MAD' => $this->airport('ES', 'MAD', 40.4719, -3.5626, 'Europe/Madrid'),
            'BCN' => $this->airport('ES', 'BCN', 41.2971, 2.0785, 'Europe/Madrid'),
            'JFK' => $this->airport('US', 'JFK', 40.6413, -73.7781, 'America/New_York'),
            'RAK' => $this->airport('MA', 'RAK', 31.6069, -8.0363, 'Africa/Casablanca'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Días y viajes
    |--------------------------------------------------------------------------
    */

    public function test_days_count_completed_trips_once_and_split_across_new_year(): void
    {
        $this->trip('2025-12-30', '2026-01-02');
        $this->trip('2026-01-02', '2026-01-05'); // solapa el 2 de enero
        $this->trip('2026-03-01', '2026-03-10', Trip::PLANNED);
        $this->trip('2026-04-01', '2026-04-03', Trip::CANCELLED);

        $places = $this->statistics->places($this->user->id);

        // 30, 31 de diciembre + del 1 al 5 de enero.
        $this->assertSame(7, $places['total']['days']);
        $this->assertSame(2, $places['total']['trips']);
        $this->assertSame([2026, 2025], array_keys($places['by_year']));
        $this->assertSame(2, $places['by_year'][2025]['days']);
        $this->assertSame(5, $places['by_year'][2026]['days']);
        // Los viajes cuentan en su año de inicio; sus días, en cada año natural.
        $this->assertSame(1, $places['by_year'][2025]['trips']);
        $this->assertSame(1, $places['by_year'][2026]['trips']);
    }

    public function test_a_user_without_trips_has_zero_days_and_no_years(): void
    {
        $places = $this->statistics->places($this->user->id);

        $this->assertSame(0, $places['total']['days']);
        $this->assertSame(0, $places['total']['countries']);
        $this->assertSame([], $places['by_year']);
    }

    /*
    |--------------------------------------------------------------------------
    | Países, territorios y primeras visitas
    |--------------------------------------------------------------------------
    */

    public function test_countries_by_year_first_visits_and_totals_only_use_visits_of_completed_trips(): void
    {
        $this->seedPlaces();

        $places = $this->statistics->places($this->user->id);

        // ES, GB y FR (sin fechas); GI es territorio; ZZ no cuenta; MA solo planificado o volado.
        $this->assertSame(3, $places['total']['countries']);
        $this->assertSame(1, $places['total']['territories']);
        $this->assertSame(5, $places['total']['cities']);
        $this->assertSame(1, $places['undated_visits']);

        $this->assertSame(1, $places['by_year'][2024]['countries']);
        $this->assertSame([['iso_code' => 'ES', 'name' => 'Spain']], $places['by_year'][2024]['new_countries']);

        // La visita de Londres del 30-12 al 02-01 cuenta en 2025 y en 2026; solo es nueva en 2025.
        $this->assertSame(1, $places['by_year'][2025]['countries']);
        $this->assertSame([['iso_code' => 'GB', 'name' => 'United Kingdom']], $places['by_year'][2025]['new_countries']);

        $this->assertSame(2, $places['by_year'][2026]['countries']);
        $this->assertSame(1, $places['by_year'][2026]['territories']);
        $this->assertSame([], $places['by_year'][2026]['new_countries']);
        $this->assertSame(1, $places['by_year'][2026]['new_territories']);
    }

    /*
    |--------------------------------------------------------------------------
    | Continentes
    |--------------------------------------------------------------------------
    */

    public function test_the_continent_table_covers_every_sovereign_state_and_leaves_special_codes_out(): void
    {
        $map = config('continents.countries');

        foreach (config('countries.un_member_states') as $code) {
            $this->assertArrayHasKey($code, $map, "{$code} no tiene continente");
        }

        foreach (config('countries.special_codes') as $code) {
            $this->assertArrayNotHasKey($code, $map);
        }

        $this->assertSame([], array_diff(array_unique(array_values($map)), array_keys(config('continents.names'))));
        $this->assertSame('EU', $map['AX']);
        $this->assertSame('EU', $map['SJ']);
    }

    public function test_continents_count_visited_countries_and_report_codes_without_a_continent(): void
    {
        $this->seedPlaces();

        // Un código fuera del catálogo de continentes: no suma a ninguno y se informa.
        $unknown = City::create(['country_id' => Country::create(['name' => 'Nowhere', 'iso_code' => 'QQ'])->id, 'name' => 'Nowhere']);
        $this->visit($this->trip('2026-06-01', '2026-06-02'), $unknown, '2026-06-01', '2026-06-02');

        $continents = $this->statistics->places($this->user->id)['continents'];
        $europe = collect($continents['items'])->firstWhere('code', 'EU');
        $africa = collect($continents['items'])->firstWhere('code', 'AF');

        $this->assertSame(3, $europe['countries']);
        $this->assertSame(1, $europe['territories']);
        $this->assertSame(
            collect(config('countries.un_member_states'))->filter(fn ($code) => config("continents.countries.{$code}") === 'EU')->count(),
            $europe['total_countries'],
        );
        $this->assertTrue($europe['visited']);
        $this->assertFalse($africa['visited']);
        $this->assertSame(['QQ'], $continents['without_continent']);
        $this->assertSame(1, $this->statistics->places($this->user->id)['total']['continents']);
    }

    /*
    |--------------------------------------------------------------------------
    | Vuelos
    |--------------------------------------------------------------------------
    */

    public function test_flight_statistics_only_count_flown_flights(): void
    {
        $trip = $this->trip('2026-03-10', '2026-03-20');
        $noCoordinates = Airport::create(['city_id' => $this->cities['ES']->id, 'name' => 'No coordinates', 'timezone' => 'Europe/Madrid']);

        $outbound = $this->flight($trip, 'MAD', 'JFK', '2026-03-10', '10:00', '13:30');          // 510 min
        $this->flight($trip, 'JFK', 'MAD', '2026-03-18', '19:00', '08:00', '2026-03-19');        // 480 min, llega al día siguiente
        $this->flight($trip, 'MAD', 'BCN', '2026-03-19');                                         // sin horas
        $this->flight($trip, $noCoordinates, 'BCN', '2026-03-20', '09:00', '10:00');              // sin distancia
        $this->flight(null, 'MAD', 'RAK', '2026-05-01', confirmed: true);                         // independiente confirmado

        $this->flight(null, 'MAD', 'RAK', '2026-05-02');                                           // pendiente
        $this->flight(null, 'MAD', 'BCN', '2026-12-01');                                           // próximo
        $this->flight($this->trip('2026-06-01', '2026-06-05', Trip::CANCELLED), 'MAD', 'BCN', '2026-06-01'); // cancelado

        $stats = $this->statistics->flights($this->loadFlights());
        $km = fn (string $from, string $to) => (int) round(Flight::make(['origin_airport_id' => $this->airports[$from]->id, 'destination_airport_id' => $this->airports[$to]->id])->load('originAirport', 'destinationAirport')->distanceKm());

        $this->assertSame(5, $stats['flights']);
        $this->assertSame(4, $stats['flights_with_distance']);
        $this->assertSame(2 * $km('MAD', 'JFK') + $km('MAD', 'BCN') + $km('MAD', 'RAK'), $stats['distance_km']);
        $this->assertSame(510 + 480 + 60, $stats['air_minutes']);
        $this->assertSame(3, $stats['flights_with_times']);
        $this->assertSame(2, $stats['flights_without_times']);
        // MAD, JFK, BCN, RAK y el aeropuerto sin coordenadas (por identidad, no por código).
        $this->assertSame(5, $stats['airports']);
        // Ida y vuelta empatan en distancia: gana la más antigua.
        $this->assertSame($outbound->id, $stats['longest']['id']);
        $this->assertSame(['MAD', 'JFK'], [$stats['longest']['origin'], $stats['longest']['destination']]);
        $this->assertSame(['MAD', 4], [$stats['top_airports'][0]['code'], $stats['top_airports'][0]['count']]);
    }

    public function test_airports_are_counted_by_identity_not_by_code(): void
    {
        $trip = $this->trip('2026-03-10', '2026-03-20');
        $first = Airport::create(['city_id' => $this->cities['ES']->id, 'name' => 'Airfield without code', 'timezone' => 'Europe/Madrid']);
        $second = Airport::create(['city_id' => $this->cities['ES']->id, 'name' => 'Another airfield without code', 'timezone' => 'Europe/Madrid']);
        $this->flight($trip, $first, 'MAD', '2026-03-10');
        $this->flight($trip, $second, 'MAD', '2026-03-11');

        $stats = $this->statistics->flights($this->loadFlights());

        $this->assertSame(3, $stats['airports']);
        $this->assertSame(['MAD', 2], [$stats['top_airports'][0]['code'], $stats['top_airports'][0]['count']]);
        $this->assertCount(3, $stats['top_airports']);
    }

    public function test_unknown_flight_figures_are_null_not_zero(): void
    {
        $noCoordinates = Airport::create(['city_id' => $this->cities['ES']->id, 'name' => 'No coordinates', 'timezone' => 'Europe/Madrid']);
        $this->flight($this->trip('2026-03-10', '2026-03-20'), $noCoordinates, 'BCN', '2026-03-10');

        $stats = $this->statistics->flights($this->loadFlights());

        $this->assertSame(1, $stats['flights']);
        $this->assertNull($stats['distance_km']);
        $this->assertNull($stats['average_distance_km']);
        $this->assertNull($stats['air_minutes']);
        $this->assertNull($stats['longest']);

        $empty = $this->statistics->flights([]);
        $this->assertSame([0, null, null, []], [$empty['flights'], $empty['distance_km'], $empty['air_minutes'], $empty['top_airports']]);
    }

    /*
    |--------------------------------------------------------------------------
    | Páginas (F4)
    |--------------------------------------------------------------------------
    */

    public function test_pages_receive_the_server_figures_for_each_period(): void
    {
        $this->seedPlaces();
        $trip = $this->trip('2026-03-10', '2026-03-20');
        $this->flight($trip, 'MAD', 'JFK', '2026-03-10', '10:00', '13:30');
        $this->flight(null, 'MAD', 'BCN', '2025-05-01', confirmed: true);

        $this->actingAs($this->user)->get('/trips')->assertInertia(fn ($page) => $page
            ->where('stats.total.countries', 3)
            ->where('stats.by_year.2025.days', 2)
            ->where('stats.by_year.2026.countries', 2));

        $this->actingAs($this->user)->get('/passport')->assertInertia(fn ($page) => $page
            ->where('stats.total.countries', 3)
            ->where('stats.by_year.2025.new_countries.0.iso_code', 'GB')
            // seedPlaces() añade el vuelo realizado MAD → RAK de 2026, sin horas.
            ->where('flightStats.all.flights', 3)
            ->where('flightStats.by_year.2026.flights', 2)
            ->where('flightStats.by_year.2026.air_minutes', 510)
            ->where('flightStats.by_year.2026.flights_without_times', 1));

        $this->actingAs($this->user)->get('/flight-history')->assertInertia(fn ($page) => $page
            ->where('stats.all:all.flights', 3)
            ->where('stats.2026:trip.flights', 2)
            ->where('stats.2026:standalone.flights', 0)
            ->where('stats.2025:standalone.flights', 1));

        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('thisYear.year', 2026)
            ->where('thisYear.countries', 2)
            ->where('thisYear.new_countries', [])
            ->where('stats.flight_km', fn ($km) => is_int($km) && $km > 5000));
    }

    public function test_pages_show_unknown_figures_for_a_user_without_data(): void
    {
        $this->actingAs($this->user)->get('/')->assertInertia(fn ($page) => $page
            ->where('stats.flight_km', null)
            ->where('thisYear.days', 0)
            ->where('thisYear.new_countries', []));

        $this->actingAs($this->user)->get('/passport')->assertInertia(fn ($page) => $page
            ->where('stats.total.days', 0)
            ->where('flightStats.all.distance_km', null)
            ->where('flightStats.by_year', []));

        $this->actingAs($this->user)->get('/flight-history')->assertInertia(fn ($page) => $page
            ->where('stats.all:all.flights', 0)
            ->where('stats.all:all.air_minutes', null));
    }

    /*
    |--------------------------------------------------------------------------
    | Datos de prueba
    |--------------------------------------------------------------------------
    */

    // Visitas de varios años, con un territorio, un código especial, una visita sin fechas
    // y lugares que NO deben contar (viaje planificado y vuelo a Marruecos).
    private function seedPlaces(): void
    {
        $this->visit($this->trip('2024-05-01', '2024-05-05'), $this->cities['ES'], '2024-05-01', '2024-05-03');

        $newYear = $this->trip('2025-12-30', '2026-01-02');
        $this->visit($newYear, $this->cities['GB'], '2025-12-30', '2026-01-02');

        $spring = $this->trip('2026-04-01', '2026-04-10');
        $this->visit($spring, $this->cities['ES'], '2026-04-01', '2026-04-02');
        $this->visit($spring, $this->cities['GI'], '2026-04-03', '2026-04-04');
        $this->visit($spring, $this->cities['ZZ'], '2026-04-05', '2026-04-06');
        Visit::create(['user_id' => $this->user->id, 'trip_id' => $spring->id, 'city_id' => $this->cities['FR']->id]); // sin fechas
        $this->flight($spring, 'MAD', 'RAK', '2026-04-08');

        $this->visit($this->trip('2026-11-01', '2026-11-05', Trip::PLANNED), $this->cities['MA'], '2026-11-01', '2026-11-03');
    }

    private function airport(string $iso, string $code, float $latitude, float $longitude, string $timezone): Airport
    {
        return Airport::create(['city_id' => $this->cities[$iso]->id, 'name' => "{$code} Airport", 'iata_code' => $code, 'latitude' => $latitude, 'longitude' => $longitude, 'timezone' => $timezone]);
    }

    private function trip(string $start, string $end, string $status = Trip::COMPLETED): Trip
    {
        return Trip::create(['user_id' => $this->user->id, 'name' => "Trip {$start}", 'start_date' => $start, 'end_date' => $end, 'status' => $status]);
    }

    private function visit(Trip $trip, City $city, string $from, string $until): Visit
    {
        return Visit::create(['user_id' => $this->user->id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => $from, 'visited_until' => $until]);
    }

    private function flight(?Trip $trip, string|Airport $from, string|Airport $to, string $date, ?string $departure = null, ?string $arrival = null, ?string $arrivalDate = null, bool $confirmed = false): Flight
    {
        $origin = $from instanceof Airport ? $from : $this->airports[$from];
        $destination = $to instanceof Airport ? $to : $this->airports[$to];
        $local = fn (string $day, ?string $time, string $zone) => $time ? Carbon::parse("{$day} {$time}", $zone)->utc() : null;

        return Flight::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip?->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'departure_date' => $date,
            'departure_at' => $local($date, $departure, $origin->timezone),
            'arrival_at' => $local($arrivalDate ?? $date, $arrival, $destination->timezone),
            'confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    private function loadFlights()
    {
        return Flight::withStatuses(Flight::with(['trip', 'originAirport.city', 'destinationAirport.city'])->get());
    }
}
