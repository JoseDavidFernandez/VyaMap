<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Procesamiento de fotos a través de la cola `database` real (en la base de
 * pruebas), con un worker ejecutado en el propio proceso.
 */
class PhotoQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        config(['queue.default' => 'database']);
    }

    public function test_job_retry_policy(): void
    {
        $job = new ProcessPhoto(1, 'photo-processing/1/original.jpg');

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
        $this->assertSame(120, $job->timeout);
    }

    public function test_uploaded_photo_waits_in_the_queue_until_a_worker_processes_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/photos', ['photo' => UploadedFile::fake()->image('queue.jpg', 1200, 800)])
            ->assertCreated();

        $photo = Photo::firstOrFail();

        // Sin worker: el trabajo espera en la tabla jobs y la foto sigue pendiente.
        $this->assertSame('pending', $photo->processing_status);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->runWorkerOnce();

        $photo->refresh();
        $this->assertSame('ready', $photo->processing_status);
        $this->assertSame(0, DB::table('jobs')->count());
        Storage::disk('public')->assertExists($photo->path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);
        Storage::disk('local')->assertMissing('photo-processing/' . $photo->id . '/original.jpg');
    }

    public function test_failing_photo_stays_pending_between_retries_and_ends_failed(): void
    {
        $photo = Photo::create([
            'user_id' => User::factory()->create()->id,
            'path' => '',
            'original_filename' => 'broken.jpg',
            'mime_type' => 'image/jpeg',
            'processing_status' => 'pending',
        ]);
        $source = 'photo-processing/' . $photo->id . '/original.jpg';
        Storage::disk('local')->put($source, 'not an image');

        ProcessPhoto::dispatch($photo->id, $source);

        // 1.er intento: falla, quedan reintentos.
        $this->runWorkerOnce();
        $this->assertSame('pending', $photo->fresh()->processing_status);
        $this->assertSame(1, DB::table('jobs')->count());
        Storage::disk('local')->assertExists($source);

        // 2.º intento tras la espera de 10 s.
        $this->travel(11)->seconds();
        $this->runWorkerOnce();
        $this->assertSame('pending', $photo->fresh()->processing_status);

        // 3.er intento tras la espera de 60 s: se agotan los reintentos.
        $this->travel(61)->seconds();
        $this->runWorkerOnce();

        $photo->refresh();
        $this->assertSame('failed', $photo->processing_status);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
        Storage::disk('local')->assertMissing($source);
        Storage::disk('public')->assertMissing('photos/' . $photo->id);
    }

    public function test_oversized_original_fails_without_retries_or_decoding(): void
    {
        $photo = Photo::create([
            'user_id' => User::factory()->create()->id,
            'path' => '',
            'original_filename' => 'huge.png',
            'mime_type' => 'image/png',
            'processing_status' => 'pending',
        ]);
        $source = 'photo-processing/' . $photo->id . '/original.png';
        // 20000 x 20000 declarados: decodificarla necesitaría unos 1,8 GB.
        Storage::disk('local')->put($source, UploadSecurityTest::pngBytes(20000, 20000));

        ProcessPhoto::dispatch($photo->id, $source);
        $this->runWorkerOnce();

        $this->assertSame('failed', $photo->fresh()->processing_status);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
        Storage::disk('local')->assertMissing($source);
    }

    public function test_failed_handler_never_downgrades_a_processed_photo(): void
    {
        $photo = Photo::create([
            'user_id' => User::factory()->create()->id,
            'path' => 'photos/1/photo.webp',
            'thumbnail_path' => 'photos/1/photo_thumb.webp',
            'original_filename' => 'ok.jpg',
            'mime_type' => 'image/jpeg',
            'processing_status' => 'ready',
        ]);
        Storage::disk('public')->put('photos/' . $photo->id . '/photo.webp', 'x');

        (new ProcessPhoto($photo->id, 'photo-processing/' . $photo->id . '/original.jpg'))
            ->failed(new RuntimeException('late failure'));

        $this->assertSame('ready', $photo->fresh()->processing_status);
        Storage::disk('public')->assertExists('photos/' . $photo->id . '/photo.webp');
    }

    public function test_trip_page_reports_photos_still_being_processed(): void
    {
        $user = User::factory()->create();
        $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => '2026-09-10', 'end_date' => '2026-09-12']);

        foreach (['pending', 'processing', 'ready', 'failed'] as $status) {
            Photo::create([
                'user_id' => $user->id,
                'trip_id' => $trip->id,
                'path' => $status === 'ready' ? 'photos/x/p.webp' : '',
                'original_filename' => "{$status}.jpg",
                'processing_status' => $status,
            ]);
        }

        $this->actingAs($user)
            ->get("/trips/{$trip->id}")
            ->assertInertia(fn ($page) => $page
                ->where('processing_photos_count', 2)
                ->has('photos', 1)
            );
    }

    private function runWorkerOnce(): void
    {
        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--stop-when-empty' => true,
            '--sleep' => 0,
        ])->assertSuccessful();
    }
}
