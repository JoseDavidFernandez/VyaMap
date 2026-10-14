<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Models\City;
use App\Models\Country;
use App\Models\Journal;
use App\Models\JournalDay;
use App\Models\JournalEntry;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Services\PhotoExifService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SyntheticExifJpeg;
use Tests\TestCase;
use Throwable;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'tests/Fixtures/synthetic-exif.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test de fotos debe tocar storage/app ni storage/app/public.
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_user_cannot_attach_photo_to_another_users_trip(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Queue::fake();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user2)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('/photos', [
                'trip_id' => $trip->id,
                'photo' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_id']);

        $this->assertDatabaseMissing('photos', [
            'trip_id' => $trip->id,
        ]);
    }

    public function test_user_cannot_attach_photo_to_another_users_visit(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Queue::fake();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $visit = Visit::create([
            'user_id' => $user1->id,
            'city_id' => $city->id,
            'visited_from' => '2026-09-10',
            'visited_until' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user2)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('/photos', [
                'visit_id' => $visit->id,
                'photo' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['visit_id']);
    }

    public function test_user_cannot_attach_photo_to_another_users_journal_entry(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Queue::fake();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $journal = Journal::create([
            'trip_id' => $trip->id,
            'title' => 'User 1 Journal',
        ]);

        $day = JournalDay::create([
            'journal_id' => $journal->id,
            'day_number' => 1,
            'date' => '2026-09-10',
        ]);

        $entry = JournalEntry::create([
            'journal_day_id' => $day->id,
            'title' => 'Entry',
            'content' => 'Test',
            'city_id' => $city->id,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($user2)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('/photos', [
                'journal_entry_id' => $entry->id,
                'photo' => UploadedFile::fake()->image('test.jpg'),
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['journal_entry_id']);
    }

    public function test_user_can_upload_photo(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $response = $this
            ->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('/photos', [
                'photo' => UploadedFile::fake()->image('holiday.jpg', 1200, 800),
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('photo.processing_status', 'pending')
            ->assertJsonMissingPath('photo.latitude')
            ->assertJsonMissingPath('photo.metadata');

        $photo = Photo::firstOrFail();

        $this->assertSame($user->id, $photo->user_id);
        $this->assertSame('holiday.jpg', $photo->original_filename);
        $this->assertSame('pending', $photo->processing_status);

        $expectedPath = 'photo-processing/' . $photo->id . '/original.jpg';

        Storage::disk('local')->assertExists($expectedPath);

        Queue::assertPushed(
            ProcessPhoto::class,
            fn (ProcessPhoto $job) => $job->photoId === $photo->id
                && $job->temporaryPath === $expectedPath
        );
    }

    public function test_upload_rejects_files_that_are_not_images_despite_their_extension(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        // UploadedFile::fake() deduce el MIME del nombre; un archivo real
        // obliga a detectar el tipo por su contenido, como en producción.
        $path = tempnam(sys_get_temp_dir(), 'vyamap');
        file_put_contents($path, 'not an image');

        $this
            ->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/photos', [
                'photo' => new UploadedFile($path, 'fake.jpg', 'image/jpeg', null, true),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        $this->assertDatabaseCount('photos', 0);
        Queue::assertNothingPushed();
    }

    public function test_photo_uploaded_for_a_visit_inherits_the_visit_trip(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $trip = $this->createTrip($user);

        $visit = Visit::create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'city_id' => $this->createCity()->id,
            'visited_from' => '2026-09-10',
            'visited_until' => '2026-09-11',
        ]);

        $this
            ->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/photos', [
                'visit_id' => $visit->id,
                'photo' => UploadedFile::fake()->image('visit.jpg'),
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('photos', [
            'visit_id' => $visit->id,
            'trip_id' => $trip->id,
        ]);
    }

    public function test_process_photo_creates_optimized_image_and_thumbnail(): void
    {
        $photo = $this->createPendingPhoto('holiday.jpg', 'image/jpeg');

        $temporaryPath = $this->putSource(
            $photo,
            UploadedFile::fake()->image('source.jpg', 1200, 800)->getContent(),
            'jpg'
        );

        ProcessPhoto::dispatchSync($photo->id, $temporaryPath);

        $photo->refresh();

        $this->assertSame('ready', $photo->processing_status);

        $this->assertNotEmpty($photo->path);
        $this->assertNotEmpty($photo->thumbnail_path);
        $this->assertSame(1200, $photo->width);
        $this->assertSame(800, $photo->height);

        Storage::disk('photos')->assertExists($photo->path);
        Storage::disk('photos')->assertExists($photo->thumbnail_path);

        Storage::disk('local')->assertMissing($temporaryPath);
    }

    public function test_synthetic_fixture_contains_only_the_expected_metadata(): void
    {
        $exif = exif_read_data(base_path(self::FIXTURE), null, true);

        $this->assertIsArray($exif);
        $this->assertArrayHasKey('GPS', $exif);
        $this->assertArrayHasKey('DateTimeOriginal', $exif['EXIF']);

        $this->assertArrayNotHasKey('THUMBNAIL', $exif);
        $this->assertArrayNotHasKey('Make', $exif['IFD0']);
        $this->assertArrayNotHasKey('Model', $exif['IFD0']);
        $this->assertArrayNotHasKey('Software', $exif['IFD0']);
        $this->assertArrayNotHasKey('MakerNote', $exif['EXIF']);
        $this->assertArrayNotHasKey('GPSAltitude', $exif['GPS']);
        $this->assertArrayNotHasKey('GPSTimeStamp', $exif['GPS']);

        // El fixture versionado es exactamente el que produce el generador.
        $this->assertSame(
            SyntheticExifJpeg::bytes(),
            file_get_contents(base_path(self::FIXTURE))
        );
    }

    public function test_photo_exif_service_extracts_date_and_coordinates(): void
    {
        $data = app(PhotoExifService::class)->extract(base_path(self::FIXTURE));

        $this->assertNotNull($data['taken_at']);
        $this->assertSame(
            SyntheticExifJpeg::EXPECTED_TAKEN_AT,
            $data['taken_at']->format('Y-m-d H:i:s')
        );
        $this->assertEqualsWithDelta(SyntheticExifJpeg::EXPECTED_LATITUDE, $data['latitude'], 0.000001);
        $this->assertEqualsWithDelta(SyntheticExifJpeg::EXPECTED_LONGITUDE, $data['longitude'], 0.000001);
    }

    public function test_photo_exif_service_returns_null_values_for_images_without_exif(): void
    {
        foreach (['png', 'webp', 'jpg'] as $extension) {
            $file = UploadedFile::fake()->image('plain.' . $extension, 40, 30);

            $data = app(PhotoExifService::class)->extract($file->getRealPath());

            $this->assertSame(
                ['taken_at' => null, 'latitude' => null, 'longitude' => null],
                $data,
                "Formato {$extension}"
            );
        }
    }

    public function test_process_photo_extracts_and_saves_exif_data(): void
    {
        $photo = $this->createPendingPhoto('synthetic-exif.jpg', 'image/jpeg');

        $temporaryPath = $this->putSource(
            $photo,
            file_get_contents(base_path(self::FIXTURE)),
            'jpg'
        );

        ProcessPhoto::dispatchSync($photo->id, $temporaryPath);

        $photo->refresh();

        $this->assertSame('ready', $photo->processing_status);
        $this->assertSame(
            SyntheticExifJpeg::EXPECTED_TAKEN_AT,
            $photo->taken_at->format('Y-m-d H:i:s')
        );
        $this->assertEqualsWithDelta(SyntheticExifJpeg::EXPECTED_LATITUDE, (float) $photo->latitude, 0.0001);
        $this->assertEqualsWithDelta(SyntheticExifJpeg::EXPECTED_LONGITUDE, (float) $photo->longitude, 0.0001);

        Storage::disk('photos')->assertExists($photo->path);
        Storage::disk('photos')->assertExists($photo->thumbnail_path);

        Storage::disk('local')->assertMissing($temporaryPath);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function imagesWithoutExif(): array
    {
        return [
            'png' => ['png', 'image/png'],
            'webp' => ['webp', 'image/webp'],
            'jpeg sin EXIF' => ['jpg', 'image/jpeg'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('imagesWithoutExif')]
    public function test_process_photo_handles_images_without_exif(string $extension, string $mime): void
    {
        $photo = $this->createPendingPhoto('plain.' . $extension, $mime);

        $temporaryPath = $this->putSource(
            $photo,
            UploadedFile::fake()->image('plain.' . $extension, 640, 480)->getContent(),
            $extension
        );

        ProcessPhoto::dispatchSync($photo->id, $temporaryPath);

        $photo->refresh();

        $this->assertSame('ready', $photo->processing_status);
        $this->assertNull($photo->taken_at);
        $this->assertNull($photo->latitude);
        $this->assertNull($photo->longitude);

        Storage::disk('photos')->assertExists($photo->path);
        Storage::disk('photos')->assertExists($photo->thumbnail_path);
        Storage::disk('local')->assertMissing($temporaryPath);
    }

    public function test_failed_processing_marks_photo_failed_and_leaves_no_files(): void
    {
        $photo = $this->createPendingPhoto('broken.jpg', 'image/jpeg');

        $temporaryPath = $this->putSource($photo, 'this is not an image', 'jpg');

        try {
            ProcessPhoto::dispatchSync($photo->id, $temporaryPath);
            $this->fail('El procesamiento debería haber fallado.');
        } catch (Throwable) {
            // Esperado.
        }

        $photo->refresh();

        $this->assertSame('failed', $photo->processing_status);
        $this->assertSame('', $photo->path);

        Storage::disk('photos')->assertMissing('photos/' . $photo->id);
        Storage::disk('local')->assertMissing($temporaryPath);
    }

    public function test_processing_a_deleted_photo_removes_its_temporary_file(): void
    {
        $photo = $this->createPendingPhoto('gone.jpg', 'image/jpeg');

        $temporaryPath = $this->putSource(
            $photo,
            UploadedFile::fake()->image('gone.jpg')->getContent(),
            'jpg'
        );

        $photoId = $photo->id;
        $photo->delete();

        ProcessPhoto::dispatchSync($photoId, $temporaryPath);

        Storage::disk('local')->assertMissing($temporaryPath);
        Storage::disk('photos')->assertMissing('photos/' . $photoId);
    }

    public function test_deleting_a_photo_removes_its_record_and_files(): void
    {
        $user = User::factory()->create();
        $photo = $this->createReadyPhoto($user);

        $this
            ->actingAs($user)
            ->delete('/photos/' . $photo->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('photos')->assertMissing($photo->path);
        Storage::disk('photos')->assertMissing($photo->thumbnail_path);
    }

    public function test_deleting_a_pending_photo_removes_its_original(): void
    {
        $user = User::factory()->create();
        $photo = $this->createPendingPhoto('pending.jpg', 'image/jpeg', $user);

        $temporaryPath = $this->putSource(
            $photo,
            UploadedFile::fake()->image('pending.jpg')->getContent(),
            'jpg'
        );

        $this
            ->actingAs($user)
            ->delete('/photos/' . $photo->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('local')->assertMissing($temporaryPath);
    }

    public function test_user_cannot_delete_another_users_photo(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $photo = $this->createReadyPhoto($owner);

        $this
            ->actingAs($other)
            ->delete('/photos/' . $photo->id)
            ->assertForbidden();

        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
        Storage::disk('photos')->assertExists($photo->path);
    }

    public function test_deleting_a_trip_removes_its_photo_files(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);

        $ready = $this->createReadyPhoto($user, $trip);

        $pending = $this->createPendingPhoto('pending.jpg', 'image/jpeg', $user, $trip);
        $temporaryPath = $this->putSource(
            $pending,
            UploadedFile::fake()->image('pending.jpg')->getContent(),
            'jpg'
        );

        $otherTrip = $this->createTrip($user);
        $unrelated = $this->createReadyPhoto($user, $otherTrip);

        $this
            ->actingAs($user)
            ->delete('/trips/' . $trip->id)
            ->assertRedirect('/trips');

        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
        $this->assertDatabaseMissing('photos', ['id' => $ready->id]);
        $this->assertDatabaseMissing('photos', ['id' => $pending->id]);

        Storage::disk('photos')->assertMissing($ready->path);
        Storage::disk('photos')->assertMissing($ready->thumbnail_path);
        Storage::disk('local')->assertMissing($temporaryPath);

        // Las fotos de otros viajes no se tocan.
        $this->assertDatabaseHas('photos', ['id' => $unrelated->id]);
        Storage::disk('photos')->assertExists($unrelated->path);
    }

    public function test_user_cannot_delete_another_users_trip_or_its_photos(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $trip = $this->createTrip($owner);
        $photo = $this->createReadyPhoto($owner, $trip);

        $this
            ->actingAs($other)
            ->delete('/trips/' . $trip->id)
            ->assertForbidden();

        $this->assertDatabaseHas('trips', ['id' => $trip->id]);
        Storage::disk('photos')->assertExists($photo->path);
    }

    public function test_trip_page_only_lists_processed_photos(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);

        $ready = $this->createReadyPhoto($user, $trip);
        $this->createPendingPhoto('pending.jpg', 'image/jpeg', $user, $trip);

        $this
            ->actingAs($user)
            ->get('/trips/' . $trip->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Show')
                ->has('photos', 1)
                ->where('photos.0.id', $ready->id)
            );
    }

    public function test_photo_gallery_does_not_expose_location_or_internal_metadata(): void
    {
        $user = User::factory()->create();
        $photo = $this->createReadyPhoto($user);

        $photo->update([
            'latitude' => SyntheticExifJpeg::EXPECTED_LATITUDE,
            'longitude' => SyntheticExifJpeg::EXPECTED_LONGITUDE,
            'metadata' => ['processing_error' => 'x'],
        ]);

        $this
            ->actingAs($user)
            ->get('/photos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Photos/Index')
                ->has('photos', 1)
                ->where('photos.0.id', $photo->id)
                ->missing('photos.0.latitude')
                ->missing('photos.0.longitude')
                ->missing('photos.0.metadata')
                ->missing('photos.0.user_id')
                ->etc()
            );
    }

    private function createTrip(User $user): Trip
    {
        return Trip::create([
            'user_id' => $user->id,
            'name' => 'Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);
    }

    private function createCity(): City
    {
        $country = Country::firstOrCreate(
            ['iso_code' => 'ES'],
            ['name' => 'Spain'],
        );

        return City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);
    }

    private function createPendingPhoto(
        string $filename,
        string $mime,
        ?User $user = null,
        ?Trip $trip = null,
    ): Photo {
        return Photo::create([
            'user_id' => ($user ?? User::factory()->create())->id,
            'trip_id' => $trip?->id,
            'path' => '',
            'original_filename' => $filename,
            'mime_type' => $mime,
            'processing_status' => 'pending',
        ]);
    }

    private function createReadyPhoto(User $user, ?Trip $trip = null): Photo
    {
        $photo = Photo::create([
            'user_id' => $user->id,
            'trip_id' => $trip?->id,
            'path' => '',
            'original_filename' => 'ready.jpg',
            'mime_type' => 'image/jpeg',
            'processing_status' => 'ready',
        ]);

        $path = 'photos/' . $photo->id . '/photo.webp';
        $thumbnail = 'photos/' . $photo->id . '/photo_thumb.webp';

        Storage::disk('photos')->put($path, 'image');
        Storage::disk('photos')->put($thumbnail, 'thumbnail');

        $photo->update([
            'path' => $path,
            'thumbnail_path' => $thumbnail,
        ]);

        return $photo;
    }

    private function putSource(Photo $photo, string $contents, string $extension): string
    {
        $temporaryPath = 'photo-processing/' . $photo->id . '/original.' . $extension;

        Storage::disk('local')->put($temporaryPath, $contents);

        return $temporaryPath;
    }
}
