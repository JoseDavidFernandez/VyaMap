<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Journal;
use App\Models\JournalDay;
use App\Models\JournalEntry;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;


class PhotoTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonPath('processing_status', 'pending');

        $this->assertDatabaseHas('photos', [
            'user_id' => $user->id,
            'original_filename' => 'holiday.jpg',
            'processing_status' => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\ProcessPhoto::class);
    }

    public function test_process_photo_creates_optimized_image_and_thumbnail(): void
    {
        $user = User::factory()->create();

        $photo = Photo::create([
            'user_id' => $user->id,
            'path' => '',
            'original_filename' => 'holiday.jpg',
            'mime_type' => 'image/jpeg',
            'processing_status' => 'pending',
        ]);

        $temporaryPath = 'photo-processing/' . $photo->id . '/source.jpg';

        $image = UploadedFile::fake()->image('source.jpg', 1200, 800);

        Storage::disk('local')->putFileAs(
            dirname($temporaryPath),
            $image,
            basename($temporaryPath)
        );

        (new \App\Jobs\ProcessPhoto(
            $photo->id,
            $temporaryPath
        ))->handle();


        $photo->refresh();

        $this->assertSame('ready', $photo->processing_status);

        $this->assertNotEmpty($photo->path);
        $this->assertNotEmpty($photo->thumbnail_path);

        Storage::disk('public')->assertExists($photo->path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);

        Storage::disk('local')->assertMissing($temporaryPath);
    }

    public function test_photo_exif_service_extracts_date_and_coordinates(): void
    {
        $path = storage_path('app/photo-exif-test.jpg');

        $this->assertFileExists($path);

        $data = app(\App\Services\PhotoExifService::class)->extract($path);

        $this->assertNotNull($data['taken_at']);
        $this->assertSame('2026-08-31 18:31:22', $data['taken_at']->format('Y-m-d H:i:s'));
        $this->assertEqualsWithDelta(41.3285167, $data['latitude'], 0.0000001);
        $this->assertEqualsWithDelta(19.8187, $data['longitude'], 0.0000001);
    }

    public function test_process_photo_extracts_and_saves_exif_data(): void
    {
        $user = User::factory()->create();

        $photo = Photo::create([
            'user_id' => $user->id,
            'path' => '',
            'original_filename' => 'photo-exif-test.jpg',
            'mime_type' => 'image/jpeg',
            'processing_status' => 'pending',
        ]);

        $temporaryPath = 'photo-processing/' . $photo->id . '/source.jpg';

        Storage::disk('local')->put(
            $temporaryPath,
            file_get_contents(base_path('tests/Fixtures/photo-exif-test.jpg'))
        );

        (new \App\Jobs\ProcessPhoto(
            $photo->id,
            $temporaryPath
        ))->handle();

        $photo->refresh();

        $this->assertSame('ready', $photo->processing_status);
        $this->assertNotNull($photo->taken_at);
        $this->assertSame(
            '2026-08-31 18:31:22',
            $photo->taken_at->format('Y-m-d H:i:s')
        );
        $this->assertEqualsWithDelta(
            41.3285167,
            (float) $photo->latitude,
            0.0000001
        );
        $this->assertEqualsWithDelta(
            19.8187,
            (float) $photo->longitude,
            0.0000001
        );

        $this->assertNotEmpty($photo->path);
        $this->assertNotEmpty($photo->thumbnail_path);

        Storage::disk('public')->assertExists($photo->path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);

        Storage::disk('local')->assertMissing($temporaryPath);
    }



}