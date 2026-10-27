<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\GoogleMapList;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 05, bloque B1: casos positivos de álbumes y listas de Google Maps del
 * propio usuario. Los casos de otros usuarios (403) ya están en
 * DataIntegrityTest y SecurityIsolationTest.
 */
class AlbumAndGoogleMapListTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        $this->user = User::factory()->create();
    }

    public function test_owner_creates_an_album(): void
    {
        $this->actingAs($this->user)
            ->from('/photos')
            ->post('/albums', ['name' => 'Japón 2026', 'description' => 'Templos y ramen'])
            ->assertRedirect('/photos')
            ->assertSessionHasNoErrors();

        $album = Album::sole();
        $this->assertSame($this->user->id, $album->user_id);
        $this->assertSame('Japón 2026', $album->name);
        $this->assertSame('Templos y ramen', $album->description);
        $this->assertNull($album->cover_photo_id);
        $this->assertSame(0, $album->photos()->count());

        $this->actingAs($this->user)
            ->get('/photos')
            ->assertInertia(fn ($page) => $page
                ->component('Photos/Index')
                ->has('albums', 1)
                ->where('albums.0.id', $album->id)
                ->where('albums.0.name', 'Japón 2026')
                ->where('albums.0.description', 'Templos y ramen')
                ->where('albums.0.photos_count', 0)
                ->where('albums.0.cover_photo_id', null)
            );
    }

    public function test_album_description_is_optional(): void
    {
        $this->actingAs($this->user)
            ->post('/albums', ['name' => 'Sin descripción'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('albums', ['user_id' => $this->user->id, 'name' => 'Sin descripción', 'description' => null]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidAlbums(): array
    {
        return [
            'sin nombre' => [['description' => 'x'], 'name'],
            'nombre vacío' => [['name' => ''], 'name'],
            'nombre de 101 caracteres' => [['name' => str_repeat('a', 101)], 'name'],
            'descripción de 1001 caracteres' => [['name' => 'Ok', 'description' => str_repeat('a', 1001)], 'description'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAlbums')]
    public function test_invalid_albums_are_not_created(array $data, string $field): void
    {
        $this->actingAs($this->user)
            ->post('/albums', $data)
            ->assertSessionHasErrors($field);

        $this->assertSame(0, Album::count());
    }

    public function test_album_limits_are_inclusive(): void
    {
        $this->actingAs($this->user)
            ->post('/albums', ['name' => str_repeat('a', 100), 'description' => str_repeat('b', 1000)])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Album::count());
    }

    public function test_owner_edits_an_album_without_touching_its_photos_or_cover(): void
    {
        $photo = Photo::create([
            'user_id' => $this->user->id,
            'path' => 'photos/a.jpg',
            'original_filename' => 'a.jpg',
            'processing_status' => 'ready',
        ]);
        $album = Album::create(['user_id' => $this->user->id, 'name' => 'Antes', 'description' => 'Vieja', 'cover_photo_id' => $photo->id]);
        $album->photos()->attach($photo->id);

        $this->actingAs($this->user)
            ->from('/photos')
            ->put("/albums/{$album->id}", ['name' => 'Después', 'description' => 'Nueva'])
            ->assertRedirect('/photos')
            ->assertSessionHasNoErrors();

        $album->refresh();
        $this->assertSame('Después', $album->name);
        $this->assertSame('Nueva', $album->description);
        $this->assertSame($this->user->id, $album->user_id);
        $this->assertSame($photo->id, $album->cover_photo_id);
        $this->assertSame([$photo->id], $album->photos()->pluck('photos.id')->all());
        $this->assertSame(1, Album::count());
    }

    public function test_editing_an_album_can_clear_its_description(): void
    {
        $album = Album::create(['user_id' => $this->user->id, 'name' => 'Álbum', 'description' => 'Algo']);

        $this->actingAs($this->user)
            ->put("/albums/{$album->id}", ['name' => 'Álbum', 'description' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($album->fresh()->description);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAlbums')]
    public function test_invalid_edits_leave_the_album_unchanged(array $data, string $field): void
    {
        $album = Album::create(['user_id' => $this->user->id, 'name' => 'Original', 'description' => 'Texto']);
        $before = $album->fresh()->toArray();

        $this->actingAs($this->user)
            ->put("/albums/{$album->id}", $data)
            ->assertSessionHasErrors($field);

        $this->assertSame($before, $album->fresh()->toArray());
    }

    public function test_owner_creates_a_google_maps_list_attached_to_the_trip(): void
    {
        $trip = $this->trip($this->user);

        $this->actingAs($this->user)
            ->from(route('trips.show', $trip))
            ->post("/trips/{$trip->id}/google-maps", ['name' => 'Restaurantes', 'url' => 'https://maps.app.goo.gl/abc123'])
            ->assertRedirect(route('trips.show', $trip))
            ->assertSessionHasNoErrors();

        $list = GoogleMapList::sole();
        $this->assertSame($this->user->id, $list->user_id);
        $this->assertSame('Restaurantes', $list->name);
        $this->assertSame('https://maps.app.goo.gl/abc123', $list->url);
        $this->assertSame([$trip->id], $list->trips()->pluck('trips.id')->all());

        $this->actingAs($this->user)
            ->get(route('trips.show', $trip))
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Show')
                ->has('google_map_lists', 1, fn ($item) => $item
                    ->where('id', $list->id)
                    ->where('name', 'Restaurantes')
                    ->where('url', 'https://maps.app.goo.gl/abc123')
                )
            );
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidGoogleMapLists(): array
    {
        return [
            'sin nombre' => [['url' => 'https://maps.app.goo.gl/x'], 'name'],
            'nombre de 101 caracteres' => [['name' => str_repeat('a', 101), 'url' => 'https://maps.app.goo.gl/x'], 'name'],
            'sin URL' => [['name' => 'Lista'], 'url'],
            'URL no válida' => [['name' => 'Lista', 'url' => 'no es una url'], 'url'],
            'URL de 2049 caracteres' => [['name' => 'Lista', 'url' => 'https://maps.app.goo.gl/' . str_repeat('a', 2025)], 'url'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidGoogleMapLists')]
    public function test_invalid_google_maps_lists_are_not_created_or_attached(array $data, string $field): void
    {
        $trip = $this->trip($this->user);

        $this->actingAs($this->user)
            ->post("/trips/{$trip->id}/google-maps", $data)
            ->assertSessionHasErrors($field);

        $this->assertSame(0, GoogleMapList::count());
        $this->assertSame(0, DB::table('google_map_list_trip')->count());
    }

    public function test_owner_attaches_an_own_list_to_an_own_trip(): void
    {
        $first = $this->trip($this->user);
        $second = $this->trip($this->user);
        $list = $this->list($this->user, 'Museos');
        $first->googleMapLists()->attach($list->id);

        $this->actingAs($this->user)
            ->from(route('trips.show', $second))
            ->post("/trips/{$second->id}/google-maps/{$list->id}")
            ->assertRedirect(route('trips.show', $second));

        // Lista reutilizable: queda en los dos viajes, sin duplicarse.
        $this->assertSame([$first->id, $second->id], $list->trips()->orderBy('trips.id')->pluck('trips.id')->all());
        $this->assertSame(1, GoogleMapList::count());

        $this->actingAs($this->user)
            ->get(route('trips.show', $second))
            ->assertInertia(fn ($page) => $page
                ->has('google_map_lists', 1)
                ->where('google_map_lists.0.id', $list->id)
            );
    }

    public function test_attaching_the_same_list_twice_keeps_a_single_link(): void
    {
        $trip = $this->trip($this->user);
        $list = $this->list($this->user, 'Museos');

        $this->actingAs($this->user)->post("/trips/{$trip->id}/google-maps/{$list->id}")->assertRedirect();
        $this->actingAs($this->user)->post("/trips/{$trip->id}/google-maps/{$list->id}")->assertRedirect();

        $this->assertSame(1, DB::table('google_map_list_trip')->where(['trip_id' => $trip->id, 'google_map_list_id' => $list->id])->count());
    }

    public function test_available_google_map_lists_contains_only_own_lists_sorted_by_name(): void
    {
        $trip = $this->trip($this->user);
        $other = User::factory()->create();

        $zoo = $this->list($this->user, 'Zoos');
        $bars = $this->list($this->user, 'Bares');
        $museums = $this->list($this->user, 'Museos');
        $this->list($other, 'Ajena');
        $this->list($other, 'Aaa de otro');

        // La ya vinculada también se envía: la vista es quien la oculta del selector.
        $trip->googleMapLists()->attach($museums->id);

        $this->actingAs($this->user)
            ->get(route('trips.show', $trip))
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Show')
                ->has('available_google_map_lists', 3)
                ->has('available_google_map_lists.0', fn ($item) => $item
                    ->where('id', $bars->id)
                    ->where('name', 'Bares')
                    ->where('url', $bars->url)
                )
                ->has('available_google_map_lists.1', fn ($item) => $item
                    ->where('id', $museums->id)
                    ->where('name', 'Museos')
                    ->where('url', $museums->url)
                )
                ->has('available_google_map_lists.2', fn ($item) => $item
                    ->where('id', $zoo->id)
                    ->where('name', 'Zoos')
                    ->where('url', $zoo->url)
                )
                ->has('google_map_lists', 1)
            );
    }

    public function test_available_google_map_lists_is_empty_without_own_lists(): void
    {
        $trip = $this->trip($this->user);
        $this->list(User::factory()->create(), 'Ajena');

        $this->actingAs($this->user)
            ->get(route('trips.show', $trip))
            ->assertInertia(fn ($page) => $page
                ->has('available_google_map_lists', 0)
                ->has('google_map_lists', 0)
            );
    }

    private function trip(User $user): Trip
    {
        return Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05']);
    }

    private function list(User $user, string $name): GoogleMapList
    {
        return GoogleMapList::create(['user_id' => $user->id, 'name' => $name, 'url' => 'https://maps.app.goo.gl/' . strtolower($name)]);
    }
}
