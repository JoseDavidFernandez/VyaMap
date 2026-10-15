<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 05, bloque B4: widgets del dashboard (DashboardController, página
 * Home): tripsByYear, recentTrips y recentPhotos, y su aislamiento entre
 * usuarios. Los contadores (stats) y nextTrip ya están en DataIntegrityTest y
 * SovereignCountriesTest; el formato de las URL de fotos en
 * PrivatePhotoAccessTest.
 *
 * B4.1: recentTrips solo incluye viajes ya empezados (mismo "hoy" que
 * nextTrip, en app.user_timezone) y cada uno lleva su portada.
 *
 * Fecha fija: 14-10-2026 12:00 UTC (14:00 en Madrid).
 */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-10-14 12:00:00';

    private User $user;

    private User $other;

    private City $madrid;

    private City $seville;

    private City $paris;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        config(['app.user_timezone' => 'Europe/Madrid']);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $this->user = User::factory()->create();
        $this->other = User::factory()->create();

        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $france = Country::create(['name' => 'France', 'iso_code' => 'FR']);
        $this->madrid = City::create(['country_id' => $spain->id, 'name' => 'Madrid']);
        $this->seville = City::create(['country_id' => $spain->id, 'name' => 'Seville']);
        $this->paris = City::create(['country_id' => $france->id, 'name' => 'Paris']);
    }

    public function test_trips_by_year_counts_own_trips_by_start_year_in_ascending_order(): void
    {
        $this->trip($this->user, '2024-11-20', '2024-11-25');
        $this->trip($this->user, '2023-05-01', '2023-05-10');
        $this->trip($this->user, '2024-03-01', '2024-03-05');
        // Cruza el cambio de año: cuenta en el año de inicio, no en el de fin.
        $this->trip($this->user, '2025-12-28', '2026-01-03');

        $this->trip($this->other, '2024-06-01', '2024-06-02');
        $this->trip($this->other, '2022-01-01', '2022-01-02');

        $this->actingAs($this->user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('tripsByYear', [
                    ['year' => 2023, 'count' => 1],
                    ['year' => 2024, 'count' => 2],
                    ['year' => 2025, 'count' => 1],
                ])
            );
    }

    public function test_recent_trips_are_the_four_latest_by_start_date(): void
    {
        $trips = [];

        // Creados fuera de orden: el orden no es el de los id.
        foreach (['2025-02-01', '2024-01-10', '2025-09-15', '2024-06-01', '2025-05-20', '2023-08-08'] as $start) {
            $trips[$start] = $this->trip($this->user, $start, $start);
        }

        // Viaje ajeno más reciente que todos: no aparece.
        $this->trip($this->other, '2026-01-01', '2026-01-05');

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('recentTrips', 4)
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('id')->all() === [
                    $trips['2025-09-15']->id,
                    $trips['2025-05-20']->id,
                    $trips['2025-02-01']->id,
                    $trips['2024-06-01']->id,
                ])
            );
    }

    public function test_recent_trips_show_their_fields_and_distinct_cities_and_countries(): void
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'name' => 'Ruta del sur',
            'description' => 'Andalucía y París',
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-12',
        ]);
        $this->visit($trip, $this->madrid, '2025-04-01');
        $this->visit($trip, $this->seville, '2025-04-03');
        $this->visit($trip, $this->madrid, '2025-04-06');
        $this->visit($trip, $this->paris, '2025-04-09');

        $empty = $this->trip($this->user, '2025-01-01', '2025-01-02');

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('recentTrips', 2)
                ->has('recentTrips.0', fn ($item) => $item
                    ->where('id', $trip->id)
                    ->where('name', 'Ruta del sur')
                    ->where('description', 'Andalucía y París')
                    ->where('start_date', '2025-04-01')
                    ->where('end_date', '2025-04-12')
                    ->where('cover', null)
                    ->where('phase', 'completed')
                    // Sin repetir; el orden de las visitas no está definido en la consulta.
                    ->where('cities', fn ($cities) => collect($cities)->sort()->values()->all() === ['Madrid', 'Paris', 'Seville'])
                    ->where('countries', fn ($countries) => collect($countries)->sort()->values()->all() === ['France', 'Spain'])
                )
                ->has('recentTrips.1', fn ($item) => $item
                    ->where('id', $empty->id)
                    ->where('cover', null)
                    ->where('cities', [])
                    ->where('countries', [])
                    ->etc()
                )
            );
    }

    public function test_recent_photos_are_the_seven_latest_ready_photos_of_the_user(): void
    {
        $ready = [];

        foreach (range(1, 9) as $minute) {
            $this->travelTo(now()->setDate(2026, 3, 1)->setTime(10, $minute));
            $ready[$minute] = $this->photo($this->user, "own-{$minute}.jpg");
        }

        // Más recientes, pero no aparecen: no están listas o son de otro usuario.
        $this->travelTo(now()->setDate(2026, 3, 2)->setTime(10, 0));
        foreach (['pending', 'processing', 'failed'] as $status) {
            $this->photo($this->user, "{$status}.jpg", $status);
        }
        $this->photo($this->other, 'foreign.jpg');
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        $expected = array_map(fn ($minute) => $ready[$minute]->id, [9, 8, 7, 6, 5, 4, 3]);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('recentPhotos', 7)
                ->where('recentPhotos', fn ($photos) => collect($photos)->pluck('id')->all() === $expected)
            );
    }

    public function test_recent_photos_expose_only_authenticated_urls_and_the_filename(): void
    {
        $trip = $this->trip($this->user, '2025-04-01', '2025-04-12');
        $photo = $this->photo($this->user, 'IMG_0001.jpg', trip: $trip);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('recentPhotos', 1)
                ->has('recentPhotos.0', fn ($item) => $item
                    ->where('id', $photo->id)
                    ->where('url', $photo->fileUrl())
                    ->where('thumbnail_url', $photo->thumbnailUrl())
                    ->where('original_filename', 'IMG_0001.jpg')
                )
            );

        $this->assertStringStartsWith("/photos/{$photo->id}/full?v=", $photo->fileUrl());
        $this->assertStringStartsWith("/photos/{$photo->id}/thumb?v=", $photo->thumbnailUrl());
        $this->assertNotSame($photo->fileUrl(), $photo->thumbnailUrl());
    }

    public function test_a_photo_of_another_user_linked_to_an_own_trip_is_not_shown(): void
    {
        $trip = $this->trip($this->user, '2025-04-01', '2025-04-12');
        $own = $this->photo($this->user, 'own.jpg', trip: $trip);
        $this->photo($this->other, 'foreign.jpg', trip: $trip);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('recentPhotos', 1)
                ->where('recentPhotos.0.id', $own->id)
            );
    }

    public function test_each_user_sees_only_their_own_widgets(): void
    {
        $mine = $this->trip($this->user, '2024-05-01', '2024-05-03', 'Mío');
        $this->visit($mine, $this->madrid, '2024-05-01');
        $myPhoto = $this->photo($this->user, 'mine.jpg', trip: $mine);

        $theirs = $this->trip($this->other, '2025-08-01', '2025-08-03', 'Suyo');
        $this->visit($theirs, $this->paris, '2025-08-01');
        $theirPhoto = $this->photo($this->other, 'theirs.jpg', trip: $theirs);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('tripsByYear', [['year' => 2024, 'count' => 1]])
                ->has('recentTrips', 1)
                ->where('recentTrips.0.id', $mine->id)
                ->where('recentTrips.0.cities', ['Madrid'])
                ->has('recentPhotos', 1)
                ->where('recentPhotos.0.id', $myPhoto->id)
            );

        $this->actingAs($this->other)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('tripsByYear', [['year' => 2025, 'count' => 1]])
                ->has('recentTrips', 1)
                ->where('recentTrips.0.id', $theirs->id)
                ->where('recentTrips.0.cities', ['Paris'])
                ->has('recentPhotos', 1)
                ->where('recentPhotos.0.id', $theirPhoto->id)
            );
    }

    public function test_widgets_are_empty_for_a_user_without_data(): void
    {
        $trip = $this->trip($this->other, '2025-08-01', '2025-08-03');
        $this->visit($trip, $this->paris, '2025-08-01');
        $this->photo($this->other, 'theirs.jpg', trip: $trip);

        $this->actingAs($this->user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('tripsByYear', [])
                ->where('recentTrips', [])
                ->where('recentPhotos', [])
            );
    }

    public function test_recent_trips_include_finished_ongoing_and_starting_today_but_not_future_trips(): void
    {
        $finished = $this->trip($this->user, '2026-09-01', '2026-09-05');
        $ongoing = $this->trip($this->user, '2026-10-10', '2026-10-20');
        $today = $this->trip($this->user, '2026-10-14', '2026-10-16');
        $this->trip($this->user, '2026-10-15', '2026-10-18');
        $this->trip($this->user, '2027-01-01', '2027-01-05');

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('id')->all() === [$today->id, $ongoing->id, $finished->id])
                // Bloque 2 (F0): las cifras solo cuentan viajes completados; los
                // próximos se cuentan aparte. En curso y hoy siguen planificados.
                ->where('stats.trips', 1)
                ->where('stats.upcoming_trips', 2)
                ->where('tripsByYear', [['year' => 2026, 'count' => 1]])
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('phase')->all() === ['ongoing', 'ongoing', 'completed'])
                // nextTrip no cambia: el viaje que empieza hoy aparece en ambos widgets.
                ->where('nextTrip.id', $today->id)
            );
    }

    public function test_future_trips_do_not_take_any_of_the_four_slots(): void
    {
        $started = [];

        foreach (['2026-03-01', '2026-10-14', '2025-12-01', '2026-07-15', '2026-01-20'] as $start) {
            $started[$start] = $this->trip($this->user, $start, $start);
        }

        foreach (['2026-10-15', '2026-12-24', '2027-06-01'] as $start) {
            $this->trip($this->user, $start, $start);
        }

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('recentTrips', 4)
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('id')->all() === [
                    $started['2026-10-14']->id,
                    $started['2026-07-15']->id,
                    $started['2026-03-01']->id,
                    $started['2026-01-20']->id,
                ])
                // Completados: los 4 que ya terminaron (el de hoy sigue en curso).
                ->where('stats.trips', 4)
                ->where('stats.upcoming_trips', 3)
            );
    }

    public function test_recent_trips_use_the_calendar_day_of_the_user_timezone(): void
    {
        $tomorrowInUtc = $this->trip($this->user, '2026-10-15', '2026-10-17');
        $this->trip($this->user, '2026-10-16', '2026-10-18');
        $past = $this->trip($this->user, '2026-10-01', '2026-10-03');

        // 22:30 UTC del día 14 = 00:30 del día 15 en Madrid.
        $this->travelTo(Carbon::parse('2026-10-14 22:30:00', 'UTC'));

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('id')->all() === [$tomorrowInUtc->id, $past->id])
            );

        // En UTC todavía es el día 14: ese viaje aún no ha empezado.
        config(['app.user_timezone' => 'UTC']);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('recentTrips', fn ($recent) => collect($recent)->pluck('id')->all() === [$past->id])
            );
    }

    public function test_cover_is_the_first_ready_own_photo_of_the_trip_as_a_private_thumbnail(): void
    {
        $trip = $this->trip($this->user, '2026-09-01', '2026-09-05');
        $otherTrip = $this->trip($this->user, '2026-08-01', '2026-08-05');

        // Ids menores que la portada esperada, pero ninguno es válido para este viaje.
        $photos = [
            $this->photo($this->user, 'other-trip.jpg', trip: $otherTrip),
            $this->photo($this->other, 'foreign.jpg', trip: $trip),
            $this->photo($this->user, 'pending.jpg', 'pending', $trip),
            $this->photo($this->user, 'processing.jpg', 'processing', $trip),
            $this->photo($this->user, 'failed.jpg', 'failed', $trip),
        ];
        $cover = $this->photo($this->user, 'cover.jpg', trip: $trip);
        $photos[] = $cover;
        $photos[] = $this->photo($this->user, 'later.jpg', trip: $trip);

        $response = $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('recentTrips.0.id', $trip->id)
                ->where('recentTrips.0.cover', $cover->thumbnailUrl())
                ->where('recentTrips.1.id', $otherTrip->id)
                ->where('recentTrips.1.cover', $photos[0]->thumbnailUrl())
            );

        $this->assertMatchesRegularExpression("#^/photos/{$cover->id}/thumb\\?v=[0-9a-f]{10}$#", $cover->thumbnailUrl());

        $props = json_encode($response->viewData('page')['props'], JSON_UNESCAPED_SLASHES);

        foreach ($photos as $photo) {
            $this->assertStringNotContainsString($photo->path, $props);
            $this->assertStringNotContainsString($photo->thumbnail_path, $props);
        }
    }

    /**
     * @return array<string, array{list<array{string, string}>}>
     */
    public static function tripsWithoutValidCover(): array
    {
        return [
            'sin fotos' => [[]],
            'solo fotos no listas' => [[['user', 'pending'], ['user', 'processing'], ['user', 'failed']]],
            'solo una foto de otro usuario' => [[['other', 'ready']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tripsWithoutValidCover')]
    public function test_cover_is_null_without_a_valid_photo(array $photos): void
    {
        $trip = $this->trip($this->user, '2026-09-01', '2026-09-05');

        foreach ($photos as [$owner, $status]) {
            $this->photo($owner === 'user' ? $this->user : $this->other, "{$status}.jpg", $status, $trip);
        }

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('recentTrips.0.id', $trip->id)
                ->where('recentTrips.0.cover', null)
            );
    }

    private function trip(User $user, string $start, string $end, string $name = 'Trip'): Trip
    {
        return Trip::create(['user_id' => $user->id, 'name' => $name, 'start_date' => $start, 'end_date' => $end]);
    }

    private function visit(Trip $trip, City $city, string $date): Visit
    {
        return Visit::create(['user_id' => $trip->user_id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => $date, 'visited_until' => $date]);
    }

    private function photo(User $user, string $filename, string $status = 'ready', ?Trip $trip = null): Photo
    {
        $directory = 'photos/' . bin2hex(random_bytes(4));

        return Photo::create([
            'user_id' => $user->id,
            'trip_id' => $trip?->id,
            'path' => "{$directory}/full.webp",
            'thumbnail_path' => "{$directory}/thumb.webp",
            'original_filename' => $filename,
            'processing_status' => $status,
        ]);
    }
}
