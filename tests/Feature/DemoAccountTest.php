<?php

namespace Tests\Feature;

use App\Http\Middleware\DemoReadOnly;
use App\Http\Responses\PasswordResetLinkRequestedResponse;
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
use App\Support\DemoAccount;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cuenta de demostración de solo lectura (DEMO_USER_EMAIL): ve todo, no
 * escribe nada (App\Http\Middleware\DemoReadOnly), no recupera su contraseña
 * y entra con «Try the demo» sin credenciales en el navegador.
 */
class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_EMAIL = 'demo@vyamap.test';

    private const PASSWORD = 'demo-password-2026';

    /**
     * Escrituras de la aplicación: [método, URI]. Los parámetros {x} son
     * datos de la propia cuenta (setUp).
     */
    private const WRITES = [
        'trips.store' => ['POST', '/trips'],
        'trips.update' => ['PUT', '/trips/{trip}'],
        'trips.status' => ['PATCH', '/trips/{trip}/status'],
        'trips.destroy' => ['DELETE', '/trips/{trip}'],
        'trips.visits.store' => ['POST', '/trips/{trip}/visits'],
        'trips.visits.update' => ['PUT', '/trips/{trip}/visits/{visit}'],
        'trips.visits.destroy' => ['DELETE', '/trips/{trip}/visits/{visit}'],
        'trips.flights.store' => ['POST', '/trips/{trip}/flights'],
        'trips.flights.update' => ['PUT', '/trips/{trip}/flights/{flight}'],
        'trips.flights.destroy' => ['DELETE', '/trips/{trip}/flights/{flight}'],
        'trips.flights.attach' => ['POST', '/trips/{trip}/flights/attach'],
        'trips.flights.detach' => ['POST', '/trips/{trip}/flights/{flight}/detach'],
        'flights.store' => ['POST', '/flights'],
        'flights.update' => ['PUT', '/flights/{standaloneFlight}'],
        'flights.destroy' => ['DELETE', '/flights/{standaloneFlight}'],
        'flights.confirmation' => ['PATCH', '/flights/{standaloneFlight}/confirmation'],
        'trips.google-maps.store' => ['POST', '/trips/{trip}/google-maps'],
        'trips.google-maps.attach' => ['POST', '/trips/{trip}/google-maps/{list}'],
        'trips.google-maps.detach' => ['DELETE', '/trips/{trip}/google-maps/{list}'],
        'cities.store' => ['POST', '/cities'],
        'journal-entries.store' => ['POST', '/journal-entries'],
        'profile.update' => ['PUT', '/profile'],
        'photos.store' => ['POST', '/photos'],
        'photos.destroy' => ['DELETE', '/photos/{photo}'],
        'albums.store' => ['POST', '/albums'],
        'albums.update' => ['PUT', '/albums/{album}'],
        'albums.destroy' => ['DELETE', '/albums/{album}'],
        'albums.photos.store' => ['POST', '/albums/{album}/photos'],
        'albums.photos.destroy' => ['DELETE', '/albums/{album}/photos/{photo}'],
        'verification.send' => ['POST', '/email/verification-notification'],
    ];

    /** Escrituras sin sesión, que se prueban aparte. */
    private const GUEST_WRITES = ['login.store', 'register.store', 'password.email', 'password.update', 'demo.login'];

    private User $demo;

    private User $other;

    /** @var array<int, array<string, int|string>> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        config(['demo.email' => self::DEMO_EMAIL]);

        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Madrid', 'latitude' => 40.4, 'longitude' => -3.7]);
        $origin = Airport::create(['city_id' => $city->id, 'name' => 'Origin', 'iata_code' => 'AAA', 'timezone' => 'UTC']);
        $destination = Airport::create(['city_id' => $city->id, 'name' => 'Destination', 'iata_code' => 'BBB', 'timezone' => 'UTC']);
        $this->shared = ['city' => $city->id, 'origin' => $origin->id, 'destination' => $destination->id];

        $this->demo = User::factory()->create(['email' => self::DEMO_EMAIL, 'name' => 'Demo', 'password' => self::PASSWORD]);
        $this->other = User::factory()->create(['email' => 'traveller@example.test', 'password' => self::PASSWORD]);

        foreach ([$this->demo, $this->other] as $user) {
            $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-10']);
            $visit = Visit::create(['user_id' => $user->id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => '2026-01-01', 'visited_until' => '2026-01-02']);
            $flight = Flight::create(['user_id' => $user->id, 'trip_id' => $trip->id, 'origin_airport_id' => $origin->id, 'destination_airport_id' => $destination->id, 'flight_number' => 'VY1', 'departure_date' => '2026-01-01']);
            $standalone = Flight::create(['user_id' => $user->id, 'trip_id' => null, 'origin_airport_id' => $destination->id, 'destination_airport_id' => $origin->id, 'flight_number' => 'VY2', 'departure_date' => '2026-02-01']);
            $photo = Photo::create(['user_id' => $user->id, 'trip_id' => $trip->id, 'path' => "photos/{$user->id}/p.webp", 'original_filename' => 'p.jpg', 'processing_status' => 'ready']);
            $album = Album::create(['user_id' => $user->id, 'name' => 'Album']);
            $album->photos()->attach($photo->id);
            $list = GoogleMapList::create(['user_id' => $user->id, 'name' => 'List', 'url' => 'https://maps.app.goo.gl/list']);
            $trip->googleMapLists()->attach($list->id);
            $day = JournalDay::create(['journal_id' => Journal::create(['trip_id' => $trip->id, 'title' => 'J'])->id, 'day_number' => 1, 'date' => '2026-01-01']);

            $this->ids[$user->id] = [
                'trip' => $trip->getRouteKey(), 'visit' => $visit->id, 'flight' => $flight->id,
                'standaloneFlight' => $standalone->id, 'photo' => $photo->id, 'album' => $album->id,
                'list' => $list->id, 'day' => $day->id,
            ];
        }
    }

    /** @var array<string, int> */
    private array $shared = [];

    /**
     * @return array<string, array{string}>
     */
    public static function writes(): array
    {
        return array_combine(array_keys(self::WRITES), array_map(fn ($name) => [$name], array_keys(self::WRITES)));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('writes')]
    public function test_the_demo_account_cannot_write(string $route): void
    {
        $before = $this->snapshot($this->demo);

        $this->actingAs($this->demo)
            ->from('/trips')
            ->call(...$this->request($route, $this->demo))
            ->assertRedirect('/trips')
            ->assertSessionHas('demo_notice', DemoAccount::NOTICE);

        $this->assertSame($before, $this->snapshot($this->demo), "{$route} ha cambiado datos de la cuenta de demostración");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('writes')]
    public function test_other_accounts_are_not_affected(string $route): void
    {
        $response = $this->actingAs($this->other)->from('/trips')->call(...$this->request($route, $this->other));

        // La respuesta es la de la propia ruta (éxito, validación o, en las
        // subidas desactivadas, su 403), nunca el rechazo de la demo.
        $response->assertSessionMissing('demo_notice');
        $this->assertStringNotContainsString(DemoAccount::NOTICE, (string) $response->getContent(), "{$route} rechazada como demo para una cuenta normal");
    }

    public function test_every_write_route_is_covered(): void
    {
        $classified = array_merge(array_keys(self::WRITES), self::GUEST_WRITES, DemoReadOnly::WRITABLE_ROUTES);
        $unclassified = [];

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), '_ignition') || ! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            if (! in_array($route->getName(), $classified, true)) {
                $unclassified[] = implode('|', $route->methods()) . ' ' . $route->uri() . ' (' . ($route->getName() ?? 'sin nombre') . ')';
            }
        }

        $this->assertSame([], $unclassified, 'Rutas de escritura sin clasificar en DemoAccountTest');
    }

    public function test_json_requests_get_a_403_with_the_notice(): void
    {
        $this->actingAs($this->demo)
            ->deleteJson('/trips/' . $this->ids[$this->demo->id]['trip'])
            ->assertForbidden()
            ->assertJson(['message' => DemoAccount::NOTICE]);

        $this->assertDatabaseCount('trips', 2);
    }

    public function test_the_demo_account_can_read_every_page(): void
    {
        $trip = $this->ids[$this->demo->id]['trip'];

        foreach (['/', '/trips', "/trips/{$trip}", '/flight-history', '/passport', '/countries', '/profile', '/photos'] as $uri) {
            $this->actingAs($this->demo)->get($uri)->assertOk()
                ->assertInertia(fn ($page) => $page->where('auth.user.is_demo', true)->where('flash.demoNotice', null));
        }
    }

    public function test_form_pages_redirect_to_the_page_that_shows_the_data(): void
    {
        $trip = $this->ids[$this->demo->id]['trip'];

        foreach (['/trips/create' => '/trips', "/trips/{$trip}/edit" => "/trips/{$trip}", '/profile/edit' => '/profile'] as $form => $target) {
            $this->actingAs($this->demo)->get($form)
                ->assertRedirect($target)
                ->assertSessionHas('demo_notice', DemoAccount::NOTICE);
        }

        $this->actingAs($this->other)->get('/trips/create')->assertOk();
        $this->actingAs($this->other)->get('/profile/edit')->assertOk();
    }

    public function test_the_notice_reaches_the_interface_after_a_rejected_write(): void
    {
        $this->actingAs($this->demo)->from('/trips')->post('/trips', $this->payload($this->demo));

        $this->actingAs($this->demo)->get('/trips')
            ->assertInertia(fn ($page) => $page->where('flash.demoNotice', DemoAccount::NOTICE));
    }

    public function test_the_demo_account_can_sign_out(): void
    {
        $this->actingAs($this->demo)->post('/logout')->assertRedirect();

        $this->assertGuest();
    }

    public function test_the_demo_account_is_identified_by_configuration_only(): void
    {
        $this->assertTrue($this->demo->isDemo());
        $this->assertFalse($this->other->isDemo());

        config(['demo.email' => strtoupper(self::DEMO_EMAIL)]);
        $this->assertTrue($this->demo->fresh()->isDemo());

        config(['demo.email' => null]);
        $this->assertFalse($this->demo->fresh()->isDemo());
        $this->actingAs($this->demo)->get('/trips/create')->assertOk();
    }

    public function test_a_password_reset_link_is_never_sent_to_the_demo_account(): void
    {
        Notification::fake();

        foreach ([self::DEMO_EMAIL, 'Demo@VyaMap.test'] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertRedirect('/forgot-password')
                ->assertSessionHas('status', PasswordResetLinkRequestedResponse::MESSAGE)
                ->assertSessionHasNoErrors();
        }

        Notification::assertNothingSentTo($this->demo);

        // El resto de cuentas lo siguen recibiendo, con la misma respuesta.
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $this->other->email])
            ->assertSessionHas('status', PasswordResetLinkRequestedResponse::MESSAGE);
        Notification::assertSentTo($this->other, ResetPassword::class);
    }

    public function test_the_demo_password_cannot_be_reset(): void
    {
        $token = Password::broker()->createToken($this->demo);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => self::DEMO_EMAIL,
            'password' => 'another-long-password-2026',
            'password_confirmation' => 'another-long-password-2026',
        ])->assertSessionHasErrors(['email' => DemoAccount::NOTICE]);

        $this->assertTrue(Hash::check(self::PASSWORD, $this->demo->fresh()->password));
    }

    public function test_the_login_page_offers_the_demo_only_when_it_is_configured(): void
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->where('canTryDemo', true));

        $this->demo->delete();
        $this->get('/login')->assertInertia(fn ($page) => $page->where('canTryDemo', false));

        config(['demo.email' => null]);
        $this->get('/login')->assertInertia(fn ($page) => $page->where('canTryDemo', false));
    }

    public function test_try_the_demo_signs_in_without_credentials(): void
    {
        $page = $this->get('/login');
        $this->assertStringNotContainsString(self::PASSWORD, $page->getContent());

        $this->post('/demo-login')->assertRedirect('/');

        $this->assertAuthenticatedAs($this->demo);
    }

    public function test_try_the_demo_returns_404_without_a_demo_account(): void
    {
        config(['demo.email' => null]);
        $this->post('/demo-login')->assertNotFound();

        config(['demo.email' => 'missing@vyamap.test']);
        $this->post('/demo-login')->assertNotFound();

        $this->assertGuest();
    }

    public function test_try_the_demo_has_its_own_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post('/demo-login')->assertRedirect('/');
            Auth::logout();
        }

        $this->from('/login')->post('/demo-login')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('demo');

        $this->assertGuest();
    }

    /**
     * @return array{string, string, array<string, mixed>}
     */
    private function request(string $route, User $user): array
    {
        [$method, $template] = self::WRITES[$route];
        $uri = preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) $this->ids[$user->id][$m[1]], $template);

        return [$method, $uri, $this->payload($user)];
    }

    /**
     * Datos válidos para cualquier ruta.
     */
    private function payload(User $user): array
    {
        $ids = $this->ids[$user->id];

        return [
            'name' => 'Changed', 'email' => $user->email, 'description' => 'x', 'url' => 'https://example.com',
            'start_date' => '2026-01-01', 'end_date' => '2026-01-10', 'status' => 'cancelled', 'confirmed' => true,
            'city_id' => $this->shared['city'], 'visited_from' => '2026-01-03', 'visited_until' => '2026-01-03',
            'origin_airport_id' => $this->shared['origin'], 'destination_airport_id' => $this->shared['destination'],
            'flight_number' => 'XX9', 'departure_date' => '2026-01-03',
            'photo_ids' => [$ids['photo']], 'journal_day_id' => $ids['day'], 'title' => 'T', 'content' => 'C', 'sort_order' => 0,
            'source' => 'catalog', 'external_id' => 'x', 'country_code' => 'ES', 'latitude' => 40.4, 'longitude' => -3.7,
        ];
    }

    /**
     * Estado de los datos de una cuenta.
     */
    private function snapshot(User $user): array
    {
        $tripIds = Trip::where('user_id', $user->id)->pluck('id');

        return [
            'user' => array_intersect_key($user->fresh()->getRawOriginal(), array_flip(['name', 'email', 'password', 'avatar', 'email_verified_at', 'updated_at'])),
            'trips' => Trip::where('user_id', $user->id)->get()->toArray(),
            'visits' => Visit::where('user_id', $user->id)->get()->toArray(),
            'flights' => Flight::where('user_id', $user->id)->get()->toArray(),
            'photos' => Photo::where('user_id', $user->id)->get()->toArray(),
            'albums' => Album::where('user_id', $user->id)->get()->toArray(),
            'album_photo' => DB::table('album_photo')->orderBy('album_id')->orderBy('photo_id')->get()->map(fn ($row) => (array) $row)->all(),
            'lists' => GoogleMapList::where('user_id', $user->id)->get()->toArray(),
            'list_trip' => DB::table('google_map_list_trip')->whereIn('trip_id', $tripIds)->get()->map(fn ($row) => (array) $row)->all(),
            'entries' => DB::table('journal_entries')->count(),
            'cities' => City::count(),
        ];
    }
}
