<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Album;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\GoogleMapList;
use App\Models\Journal;
use App\Models\JournalDay;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Aislamiento entre usuarios en todas las rutas que reciben IDs, accesos sin
 * sesión y límites de validación (auditoría de seguridad, fase 04).
 */
class SecurityIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $victim;

    private User $intruder;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->victim = User::factory()->create();
        $this->intruder = User::factory()->create();

        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Madrid', 'latitude' => 40.4, 'longitude' => -3.7]);
        $origin = Airport::create(['city_id' => $city->id, 'name' => 'Origin', 'iata_code' => 'AAA', 'timezone' => 'UTC']);
        $destination = Airport::create(['city_id' => $city->id, 'name' => 'Destination', 'iata_code' => 'BBB', 'timezone' => 'UTC']);

        $trip = Trip::create(['user_id' => $this->victim->id, 'name' => 'Victim secret trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-10']);
        $visit = Visit::create(['user_id' => $this->victim->id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => '2026-01-01', 'visited_until' => '2026-01-02', 'notes' => 'Victim notes']);
        $flight = Flight::create(['user_id' => $this->victim->id, 'trip_id' => $trip->id, 'origin_airport_id' => $origin->id, 'destination_airport_id' => $destination->id, 'flight_number' => 'VY1', 'departure_at' => '2026-01-01 08:00', 'arrival_at' => '2026-01-01 10:00']);
        $photo = Photo::create(['user_id' => $this->victim->id, 'trip_id' => $trip->id, 'path' => 'photos/1/p.webp', 'original_filename' => 'p.jpg', 'processing_status' => 'ready']);
        $album = Album::create(['user_id' => $this->victim->id, 'name' => 'Victim album', 'cover_photo_id' => $photo->id]);
        $album->photos()->attach($photo->id);
        $list = GoogleMapList::create(['user_id' => $this->victim->id, 'name' => 'Victim list', 'url' => 'https://maps.app.goo.gl/victim']);
        $trip->googleMapLists()->attach($list->id);
        $day = JournalDay::create(['journal_id' => Journal::create(['trip_id' => $trip->id, 'title' => 'J'])->id, 'day_number' => 1, 'date' => '2026-01-01']);

        $ownTrip = Trip::create(['user_id' => $this->intruder->id, 'name' => 'Own trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-10']);
        $ownAlbum = Album::create(['user_id' => $this->intruder->id, 'name' => 'Own album']);

        $this->ids = [
            'trip' => $trip->id, 'visit' => $visit->id, 'flight' => $flight->id, 'photo' => $photo->id,
            'album' => $album->id, 'list' => $list->id, 'day' => $day->id, 'city' => $city->id,
            'country' => $country->id, 'origin' => $origin->id, 'destination' => $destination->id,
            'ownTrip' => $ownTrip->id, 'ownAlbum' => $ownAlbum->id,
        ];
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function intruderRequests(): array
    {
        return [
            'ver viaje' => ['GET', '/trips/{trip}', 403],
            'editar viaje (formulario)' => ['GET', '/trips/{trip}/edit', 403],
            'fotos del viaje' => ['GET', '/trips/{trip}/photos', 403],
            'actualizar viaje' => ['PUT', '/trips/{trip}', 403],
            'borrar viaje' => ['DELETE', '/trips/{trip}', 403],
            'crear visita' => ['POST', '/trips/{trip}/visits', 403],
            'actualizar visita ajena desde viaje propio' => ['PUT', '/trips/{ownTrip}/visits/{visit}', 404],
            'borrar visita ajena desde viaje propio' => ['DELETE', '/trips/{ownTrip}/visits/{visit}', 404],
            'crear vuelo' => ['POST', '/trips/{trip}/flights', 403],
            'actualizar vuelo ajeno desde viaje propio' => ['PUT', '/trips/{ownTrip}/flights/{flight}', 404],
            'borrar vuelo ajeno desde viaje propio' => ['DELETE', '/trips/{ownTrip}/flights/{flight}', 404],
            'crear lista de Google Maps' => ['POST', '/trips/{trip}/google-maps', 403],
            'vincular lista ajena a viaje propio' => ['POST', '/trips/{ownTrip}/google-maps/{list}', 403],
            'desvincular lista de viaje ajeno' => ['DELETE', '/trips/{trip}/google-maps/{list}', 403],
            'actualizar álbum' => ['PUT', '/albums/{album}', 403],
            'borrar álbum' => ['DELETE', '/albums/{album}', 403],
            'añadir fotos a álbum ajeno' => ['POST', '/albums/{album}/photos', 403],
            'quitar foto de álbum ajeno' => ['DELETE', '/albums/{album}/photos/{photo}', 403],
            'borrar foto' => ['DELETE', '/photos/{photo}', 403],
            'entrada en diario ajeno' => ['POST', '/journal-entries', 403],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('intruderRequests')]
    public function test_intruder_cannot_reach_another_users_resources(string $method, string $uri, int $status): void
    {
        $before = $this->snapshot();

        $response = $this->actingAs($this->intruder)->json($method, $this->uri($uri), $this->payload());

        $response->assertStatus($status);
        $this->assertStringNotContainsString('Victim', $response->getContent());
        $this->assertSame($before, $this->snapshot(), 'Los datos de la víctima no deben cambiar');
    }

    public function test_photos_of_other_users_are_not_added_to_an_own_album(): void
    {
        $this->actingAs($this->intruder)
            ->post("/albums/{$this->ids['ownAlbum']}/photos", ['photo_ids' => [$this->ids['photo']]])
            ->assertRedirect();

        $this->assertDatabaseMissing('album_photo', ['album_id' => $this->ids['ownAlbum']]);
    }

    public function test_country_page_only_shows_the_users_own_trips_and_cities(): void
    {
        $this->actingAs($this->intruder)
            ->get("/countries/{$this->ids['country']}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('trips', 0)->has('cities', 0));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privatePages(): array
    {
        return array_map(fn ($uri) => [$uri], [
            'dashboard' => '/',
            'viajes' => '/trips',
            'nuevo viaje' => '/trips/create',
            'viaje' => '/trips/1',
            'fotos' => '/photos',
            'perfil' => '/profile',
            'editar perfil' => '/profile/edit',
            'países' => '/countries',
            'país' => '/countries/1',
            'pasaporte' => '/passport',
            'vuelos' => '/flight-history',
            'buscar ciudades' => '/cities/search?q=ma',
            'buscar aeropuertos' => '/airports/search?q=ma',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('privatePages')]
    public function test_guests_are_sent_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    public function test_guests_cannot_write(): void
    {
        $before = $this->snapshot();

        $this->post('/trips', ['name' => 'x'])->assertRedirect('/login');
        $this->delete("/trips/{$this->ids['trip']}")->assertRedirect('/login');
        $this->delete("/photos/{$this->ids['photo']}")->assertRedirect('/login');
        $this->postJson('/photos', [])->assertUnauthorized();
        $this->postJson('/journal-entries', [])->assertUnauthorized();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_api_user_route_no_longer_exists(): void
    {
        $this->actingAs($this->victim)->getJson('/api/user')->assertNotFound();
    }

    public function test_user_serialization_hides_credentials_and_two_factor_columns(): void
    {
        DB::table('users')->where('id', $this->victim->id)->update([
            'two_factor_secret' => 'secret',
            'two_factor_recovery_codes' => 'codes',
            'remember_token' => 'token',
        ]);

        $serialized = $this->victim->fresh()->toArray();

        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $field) {
            $this->assertArrayNotHasKey($field, $serialized);
        }
    }

    public function test_google_maps_writes_require_the_update_ability(): void
    {
        $trip = Trip::findOrFail($this->ids['ownTrip']);
        $list = GoogleMapList::create(['user_id' => $this->intruder->id, 'name' => 'Own list', 'url' => 'https://maps.app.goo.gl/own']);

        // Un usuario que puede ver el viaje pero no editarlo.
        Gate::before(fn ($user, string $ability) => $ability === 'update' ? false : null);

        $this->actingAs($this->intruder)->post("/trips/{$trip->id}/google-maps", ['name' => 'n', 'url' => 'https://example.com'])->assertForbidden();
        $this->actingAs($this->intruder)->post("/trips/{$trip->id}/google-maps/{$list->id}")->assertForbidden();
        $this->actingAs($this->intruder)->delete("/trips/{$trip->id}/google-maps/{$list->id}")->assertForbidden();
        $this->actingAs($this->intruder)->get("/trips/{$trip->id}")->assertOk();
    }

    public function test_removing_a_photo_that_is_not_in_the_album_returns_not_found(): void
    {
        $own = Photo::create(['user_id' => $this->intruder->id, 'path' => 'photos/2/p.webp', 'original_filename' => 'p.jpg', 'processing_status' => 'ready']);

        $this->actingAs($this->intruder)
            ->delete("/albums/{$this->ids['ownAlbum']}/photos/{$own->id}")
            ->assertNotFound();
    }

    /**
     * @return array<string, array{string, string, string, int}>
     */
    public static function textLimits(): array
    {
        return [
            'descripción del viaje' => ['POST', '/trips', 'description', 5000],
            'notas de la visita' => ['POST', '/trips/{ownTrip}/visits', 'notes', 5000],
            'contenido del diario' => ['POST', '/journal-entries', 'content', 50000],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('textLimits')]
    public function test_long_texts_are_limited(string $method, string $uri, string $field, int $max): void
    {
        $ownDay = JournalDay::create([
            'journal_id' => Journal::create(['trip_id' => $this->ids['ownTrip'], 'title' => 'Own'])->id,
            'day_number' => 1,
            'date' => '2026-01-01',
        ]);

        $base = [
            'name' => 'Trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02',
            'city_id' => $this->ids['city'], 'visited_from' => '2026-01-01', 'visited_until' => '2026-01-01',
            'journal_day_id' => $ownDay->id, 'title' => 'T', 'sort_order' => 0,
        ];

        // Con caracteres de 4 bytes: el límite también cabe en la columna TEXT.
        $this->actingAs($this->intruder)
            ->json($method, $this->uri($uri), [...$base, $field => str_repeat('🌍', $max + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $response = $this->actingAs($this->intruder)
            ->json($method, $this->uri($uri), [...$base, $field => str_repeat('🌍', $max)]);

        $this->assertContains($response->status(), [201, 302]);

        $table = ['description' => 'trips', 'notes' => 'visits', 'content' => 'journal_entries'][$field];
        $this->assertSame($max, (int) DB::table($table)->max(DB::raw("CHAR_LENGTH({$field})")));
    }

    private function uri(string $template): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) $this->ids[$m[1]], $template);
    }

    /**
     * Datos válidos para cualquier ruta: así la respuesta depende solo de la
     * autorización y no de la validación.
     */
    private function payload(): array
    {
        return [
            'name' => 'Changed', 'description' => 'x', 'url' => 'https://example.com',
            'start_date' => '2026-01-01', 'end_date' => '2026-01-10',
            'city_id' => $this->ids['city'], 'visited_from' => '2026-01-03', 'visited_until' => '2026-01-03',
            'origin_airport_id' => $this->ids['origin'], 'destination_airport_id' => $this->ids['destination'],
            'flight_number' => 'XX9', 'departure_at' => '2026-01-03T08:00', 'arrival_at' => '2026-01-03T10:00',
            'photo_ids' => [$this->ids['photo']],
            'journal_day_id' => $this->ids['day'], 'title' => 'T', 'content' => 'C', 'sort_order' => 0,
        ];
    }

    /**
     * Estado de los datos de la víctima.
     */
    private function snapshot(): array
    {
        return [
            'trips' => Trip::where('user_id', $this->victim->id)->get()->toArray(),
            'visits' => Visit::where('user_id', $this->victim->id)->get()->toArray(),
            'flights' => Flight::where('user_id', $this->victim->id)->get()->toArray(),
            'photos' => Photo::where('user_id', $this->victim->id)->get()->toArray(),
            'albums' => Album::where('user_id', $this->victim->id)->get()->toArray(),
            'album_photo' => DB::table('album_photo')->orderBy('album_id')->orderBy('photo_id')->get()->map(fn ($row) => (array) $row)->all(),
            'lists' => GoogleMapList::where('user_id', $this->victim->id)->get()->toArray(),
            'list_trip' => DB::table('google_map_list_trip')->orderBy('trip_id')->get()->map(fn ($row) => (array) $row)->all(),
            'entries' => DB::table('journal_entries')->count(),
        ];
    }
}
