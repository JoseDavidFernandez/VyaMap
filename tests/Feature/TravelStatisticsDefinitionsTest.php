<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Support\SovereignCountries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bloque 1 (coherencia de datos): definiciones únicas de países y
 * territorios, ciudades distintas por identidad, viajes por país y datos que
 * necesita la vista para mostrar los vuelos en la hora local del aeropuerto.
 *
 * - Países: Estados soberanos (miembros de la ONU), sobre 193.
 * - Territorios: territorios dependientes y casos pendientes de decisión.
 * - Códigos especiales (ZZ, XP): no cuentan en ninguno.
 */
class TravelStatisticsDefinitionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        $this->user = User::factory()->create();
    }

    public function test_territories_count_dependent_territories_and_pending_cases_but_not_special_codes(): void
    {
        // ES soberano; PR y GL territorios; VA pendiente; ZZ y XP especiales.
        $codes = ['ES', 'PR', 'PR', 'gl', 'VA', 'ZZ', 'XP', null];

        $this->assertSame(1, SovereignCountries::countVisited($codes));
        $this->assertSame(3, SovereignCountries::countVisitedTerritories($codes));
        $this->assertSame(0, SovereignCountries::countVisitedTerritories(['ES', 'FR', 'ZZ']));
    }

    public function test_dashboard_separates_countries_from_territories_and_sends_what_widgets_need(): void
    {
        $madrid = $this->city('Spain', 'ES', 'Madrid');
        $sanJuan = $this->city('Puerto Rico', 'PR', 'San Juan');
        $unknown = $this->city('Unknown', 'ZZ', 'Nowhere');

        $trip = $this->trip('2025-03-01', '2025-03-10');
        $this->visit($trip, $madrid, '2025-03-01');
        $this->visit($trip, $sanJuan, '2025-03-05');
        $this->visit($trip, $unknown, '2025-03-08');
        $this->flight($trip, $this->airport($madrid, 'MAD', 'Europe/Madrid'), $this->airport($sanJuan, 'SJU', 'America/Puerto_Rico'));

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                // countries sigue contando todos los códigos; las cifras generales usan los otros dos.
                ->where('stats.countries', 3)
                ->where('stats.sovereign_countries', 1)
                ->where('stats.territories', 1)
                ->where('stats.total_sovereign_countries', 193)
                ->where('map.visits.0.trip_id', $trip->id)
                ->where('map.visits.0.city.country_status', 'sovereign')
                ->where('map.visits.1.city.country_status', 'territory')
                ->where('map.visits.2.city.country_status', 'special')
                ->where('recentFlights.0.origin.timezone', 'Europe/Madrid')
                ->where('recentFlights.0.destination.timezone', 'America/Puerto_Rico')
                ->where('map.flights.0.origin.timezone', 'Europe/Madrid')
            );
    }

    public function test_trip_list_counts_distinct_cities_by_identity_not_by_name(): void
    {
        // Dos ciudades distintas con el mismo nombre, y una repetida en dos visitas.
        $santiagoChile = $this->city('Chile', 'CL', 'Santiago');
        $santiagoSpain = $this->city('Spain', 'ES', 'Santiago');
        $trip = $this->trip('2025-05-01', '2025-05-20');
        $this->visit($trip, $santiagoChile, '2025-05-01');
        $this->visit($trip, $santiagoSpain, '2025-05-10');
        $this->visit($trip, $santiagoChile, '2025-05-15');

        $this->actingAs($this->user)
            ->get('/trips')
            ->assertInertia(fn ($page) => $page
                ->where('trips.0.city_ids', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$santiagoChile->id, $santiagoSpain->id])->sort()->values()->all())
                ->where('trips.0.countries', fn ($countries) => collect($countries)->pluck('country_status', 'iso_code')->sortKeys()->all() === ['CL' => 'sovereign', 'ES' => 'sovereign'])
            );
    }

    public function test_trip_page_and_countries_page_send_the_country_classification(): void
    {
        $nuuk = $this->city('Greenland', 'GL', 'Nuuk');
        $trip = $this->trip('2025-07-01', '2025-07-05');
        $this->visit($trip, $nuuk, '2025-07-01');

        $this->actingAs($this->user)
            ->get(route('trips.show', $trip))
            ->assertInertia(fn ($page) => $page->where('visits.0.city.country_status', 'territory'));

        $this->actingAs($this->user)
            ->get('/countries')
            ->assertInertia(fn ($page) => $page
                ->has('countries', 1)
                ->where('countries.0.iso_code', 'GL')
                ->where('countries.0.country_status', 'territory')
            );
    }

    public function test_profile_is_an_account_page_with_the_countries_for_the_globe(): void
    {
        $this->user->forceFill(['created_at' => '2026-09-17 10:00:00'])->save();
        $trip = $this->trip('2025-07-01', '2025-07-05');
        $this->visit($trip, $this->city('Spain', 'ES', 'Madrid'), '2025-07-01');
        $this->visit($trip, $this->city('Puerto Rico', 'PR', 'San Juan'), '2025-07-03');

        // Datos de otro usuario: no aparecen.
        $foreign = Trip::create(['user_id' => User::factory()->create()->id, 'name' => 'Ajeno', 'start_date' => '2025-01-01', 'end_date' => '2025-01-02']);
        $this->visit($foreign, $this->city('France', 'FR', 'Paris'), '2025-01-01');

        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile')
                ->where('user', [
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'avatar' => null,
                    'member_since' => '2026-09-17',
                ])
                ->where('countries', [
                    ['name' => 'Puerto Rico', 'iso_code' => 'PR', 'country_status' => 'territory'],
                    ['name' => 'Spain', 'iso_code' => 'ES', 'country_status' => 'sovereign'],
                ])
                // Las cifras generales viven en el dashboard; los viajes, en /trips.
                ->missing('stats')
                ->missing('trips')
            );
    }

    private function city(string $countryName, string $iso, string $name): City
    {
        $country = Country::firstOrCreate(['iso_code' => $iso], ['name' => $countryName]);

        return City::create(['country_id' => $country->id, 'name' => $name, 'latitude' => 1, 'longitude' => 1]);
    }

    private function trip(string $start, string $end): Trip
    {
        return Trip::create(['user_id' => $this->user->id, 'name' => 'Trip', 'start_date' => $start, 'end_date' => $end]);
    }

    private function visit(Trip $trip, City $city, string $date): Visit
    {
        return Visit::create(['user_id' => $trip->user_id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => $date, 'visited_until' => $date]);
    }

    private function airport(City $city, string $iata, string $timezone): Airport
    {
        return Airport::create(['city_id' => $city->id, 'name' => "{$iata} Airport", 'iata_code' => $iata, 'latitude' => 1, 'longitude' => 1, 'timezone' => $timezone]);
    }

    private function flight(Trip $trip, Airport $origin, Airport $destination): Flight
    {
        return Flight::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $origin->id,
            'destination_airport_id' => $destination->id,
            'flight_number' => 'IB123',
            'departure_at' => $trip->start_date->toDateString() . ' 10:00:00',
            'arrival_at' => $trip->start_date->toDateString() . ' 18:00:00',
        ]);
    }
}
