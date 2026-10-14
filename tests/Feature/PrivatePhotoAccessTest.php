<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\City;
use App\Models\Country;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Services\PhotoFileManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fotos privadas (fase 04, H1): los archivos solo se sirven a su
 * propietario a través de /photos/{photo}/{variant}.
 */
class PrivatePhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->trip = Trip::create(['user_id' => $this->owner->id, 'name' => 'Trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05']);
    }

    public function test_owner_receives_both_variants_with_private_cache_headers(): void
    {
        $photo = $this->readyPhoto();

        foreach (['full' => 'FULL-BYTES', 'thumb' => 'THUMB-BYTES'] as $variant => $bytes) {
            $response = $this->actingAs($this->owner)->get($photo->fileUrl($variant));

            $response->assertOk();
            $this->assertSame($bytes, $response->streamedContent());
            $this->assertSame('image/webp', $response->headers->get('Content-Type'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('max-age=3600', $response->headers->get('Cache-Control'));
            $this->assertStringNotContainsString('public', $response->headers->get('Cache-Control'));
            $this->assertSame("inline; filename=photo-{$photo->id}-{$variant}.webp", $response->headers->get('Content-Disposition'));
            $this->assertNotNull($response->headers->get('Last-Modified'));
            $this->assertNull($response->headers->get('X-Sendfile'));
        }
    }

    public function test_unchanged_files_are_revalidated_with_304(): void
    {
        $photo = $this->readyPhoto();
        $first = $this->actingAs($this->owner)->get($photo->fileUrl());

        $this->actingAs($this->owner)
            ->get($photo->fileUrl(), ['If-Modified-Since' => $first->headers->get('Last-Modified')])
            ->assertStatus(304);
    }

    public function test_guests_are_redirected_or_rejected(): void
    {
        $photo = $this->readyPhoto();

        $this->get($photo->fileUrl())->assertRedirect('/login');
        $this->getJson($photo->fileUrl('thumb'))->assertUnauthorized();
    }

    public function test_other_users_get_the_same_404_as_a_missing_photo(): void
    {
        $photo = $this->readyPhoto();
        $intruder = User::factory()->create();

        $foreign = $this->actingAs($intruder)->get("/photos/{$photo->id}/full");
        $missing = $this->actingAs($intruder)->get('/photos/999999/full');

        $foreign->assertNotFound();
        $missing->assertNotFound();
        $this->assertSame($missing->getContent(), $foreign->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUris(): array
    {
        return [
            'variante inexistente' => ['/photos/{id}/original'],
            'traversal en la variante' => ['/photos/{id}/..%2F..%2F.env'],
            'id no numérico' => ['/photos/abc/full'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidUris')]
    public function test_unknown_variants_and_malformed_ids_return_404(string $uri): void
    {
        $photo = $this->readyPhoto();

        $this->actingAs($this->owner)
            ->get(str_replace('{id}', (string) $photo->id, $uri))
            ->assertNotFound();
    }

    public function test_deleted_photos_are_no_longer_served(): void
    {
        $photo = $this->readyPhoto();
        $url = $photo->fileUrl();

        $this->actingAs($this->owner)->delete("/photos/{$photo->id}")->assertRedirect();

        $this->actingAs($this->owner)->get($url)->assertNotFound();
        Storage::disk('photos')->assertMissing("photos/{$photo->id}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unfinishedStatuses(): array
    {
        return [['pending'], ['processing'], ['failed']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unfinishedStatuses')]
    public function test_photos_that_are_not_ready_are_not_served(string $status): void
    {
        $photo = $this->readyPhoto();
        $photo->update(['processing_status' => $status]);

        $this->assertNull($photo->fileUrl());
        $this->actingAs($this->owner)->get("/photos/{$photo->id}/full")->assertNotFound();
    }

    public function test_missing_files_return_404(): void
    {
        $photo = $this->readyPhoto();
        Storage::disk('photos')->delete($photo->path);

        $this->actingAs($this->owner)->get($photo->fileUrl())->assertNotFound();
    }

    public function test_paths_outside_the_photos_own_directory_are_never_served(): void
    {
        $victim = $this->readyPhoto();
        $photo = $this->readyPhoto();

        foreach ([$victim->path, "photos/{$photo->id}/../{$victim->id}/full.webp", '.env', ''] as $path) {
            $photo->update(['path' => $path]);

            $this->actingAs($this->owner)->get("/photos/{$photo->id}/full")->assertNotFound();
        }
    }

    public function test_photo_linked_to_records_of_another_user_is_not_served(): void
    {
        $other = User::factory()->create();
        $otherTrip = Trip::create(['user_id' => $other->id, 'name' => 'Other', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);
        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Madrid', 'latitude' => 1, 'longitude' => 1]);
        $otherVisit = Visit::create(['user_id' => $other->id, 'trip_id' => $otherTrip->id, 'city_id' => $city->id, 'visited_from' => '2026-01-01', 'visited_until' => '2026-01-01']);

        $cases = [
            'viaje ajeno' => fn (Photo $photo) => $photo->update(['trip_id' => $otherTrip->id]),
            'visita ajena' => fn (Photo $photo) => $photo->update(['visit_id' => $otherVisit->id]),
            'álbum ajeno' => fn (Photo $photo) => Album::create(['user_id' => $other->id, 'name' => 'A'])->photos()->attach($photo->id),
        ];

        foreach ($cases as $case => $corrupt) {
            $photo = $this->readyPhoto();
            $corrupt($photo);

            $this->actingAs($this->owner)
                ->get("/photos/{$photo->id}/full")
                ->assertNotFound();
        }
    }

    public function test_legacy_photos_on_the_public_disk_keep_working_until_migrated(): void
    {
        $photo = $this->readyPhoto(disk: 'public');

        $this->actingAs($this->owner)->get($photo->fileUrl())->assertOk();
        $this->assertSame('THUMB-BYTES', $this->actingAs($this->owner)->get($photo->fileUrl('thumb'))->streamedContent());

        // Si ya existe en el disco privado (migrada), se usa esa copia.
        Storage::disk('photos')->put($photo->path, 'MIGRATED');
        $this->assertSame('MIGRATED', $this->actingAs($this->owner)->get($photo->fileUrl())->streamedContent());

        // Al borrarla desaparecen ambas copias.
        $this->actingAs($this->owner)->delete("/photos/{$photo->id}")->assertRedirect();
        Storage::disk('public')->assertMissing("photos/{$photo->id}");
        Storage::disk('photos')->assertMissing("photos/{$photo->id}");
    }

    public function test_new_uploads_are_processed_into_the_private_disk_only(): void
    {
        config(['queue.default' => 'sync']);

        $this->actingAs($this->owner)
            ->postJson('/photos', ['photo' => UploadedFile::fake()->image('new.jpg', 800, 600), 'trip_id' => $this->trip->id])
            ->assertCreated();

        $photo = Photo::firstOrFail();

        $this->assertSame('ready', $photo->processing_status);
        Storage::disk('photos')->assertExists([$photo->path, $photo->thumbnail_path]);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->actingAs($this->owner)->get($photo->thumbnailUrl())->assertOk();
    }

    public function test_private_disk_is_not_inside_the_public_storage_link(): void
    {
        $root = realpath(dirname(config('filesystems.disks.photos.root'))) . '/' . basename(config('filesystems.disks.photos.root'));

        $this->assertStringStartsNotWith(storage_path('app/public'), $root);
        $this->assertStringStartsNotWith(public_path(), $root);
        $this->assertSame('private', config('filesystems.disks.photos.visibility'));
        $this->assertArrayNotHasKey('url', config('filesystems.disks.photos'));
    }

    /**
     * Páginas que muestran fotos: solo URLs autenticadas, nunca rutas de disco.
     *
     * @return array<string, array{string}>
     */
    public static function pagesWithPhotos(): array
    {
        return array_map(fn ($uri) => [$uri], [
            'dashboard' => '/',
            'viajes' => '/trips',
            'viaje' => '/trips/{trip}',
            'fotos del viaje' => '/trips/{trip}/photos',
            'galería' => '/photos',
            'perfil' => '/profile',
            'país' => '/countries/{country}',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pagesWithPhotos')]
    public function test_pages_expose_authenticated_urls_and_no_storage_paths(string $uri): void
    {
        $photo = $this->readyPhoto();
        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Madrid', 'latitude' => 1, 'longitude' => 1]);
        Visit::create(['user_id' => $this->owner->id, 'trip_id' => $this->trip->id, 'city_id' => $city->id, 'visited_from' => '2026-01-01', 'visited_until' => '2026-01-01']);
        Album::create(['user_id' => $this->owner->id, 'name' => 'A', 'cover_photo_id' => $photo->id])->photos()->attach($photo->id);

        $response = $this->actingAs($this->owner)
            ->get(strtr($uri, ['{trip}' => $this->trip->id, '{country}' => $country->id]))
            ->assertOk();

        $props = json_encode($response->viewData('page')['props'], JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString($photo->path, $props);
        $this->assertStringNotContainsString($photo->thumbnail_path, $props);
        $this->assertStringNotContainsString('/storage/photos', $props);
        $this->assertStringNotContainsString('thumbnail_path', $props);
        $this->assertStringContainsString($photo->thumbnailUrl(), $props);
    }

    private function readyPhoto(string $disk = PhotoFileManager::DISK): Photo
    {
        $photo = Photo::create([
            'user_id' => $this->owner->id,
            'trip_id' => $this->trip->id,
            'path' => '',
            'original_filename' => 'ready.jpg',
            'mime_type' => 'image/jpeg',
            'processing_status' => 'ready',
        ]);

        $path = "photos/{$photo->id}/photo_abc.webp";
        $thumbnail = "photos/{$photo->id}/photo_abc_thumb.webp";

        Storage::disk($disk)->put($path, 'FULL-BYTES');
        Storage::disk($disk)->put($thumbnail, 'THUMB-BYTES');

        $photo->update(['path' => $path, 'thumbnail_path' => $thumbnail]);

        return $photo;
    }
}
