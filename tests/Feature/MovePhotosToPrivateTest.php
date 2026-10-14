<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovePhotosToPrivateTest extends TestCase
{
    use RefreshDatabase;

    private Photo $photo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->photo = Photo::create([
            'user_id' => User::factory()->create()->id,
            'path' => '',
            'original_filename' => 'legacy.jpg',
            'processing_status' => 'ready',
        ]);

        $this->photo->update([
            'path' => "photos/{$this->photo->id}/photo_a.webp",
            'thumbnail_path' => "photos/{$this->photo->id}/photo_a_thumb.webp",
        ]);

        Storage::disk('public')->put($this->photo->path, 'FULL');
        Storage::disk('public')->put($this->photo->thumbnail_path, 'THUMB');
        Storage::disk('public')->put('photos/999/orphan.webp', 'ORPHAN');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $before = $this->state();

        $this->artisan('photos:move-to-private')
            ->expectsOutputToContain('Simulación: no se ha modificado nada.')
            ->expectsOutputToContain('1 archivos públicos no pertenecen a ninguna foto')
            ->assertSuccessful();

        $this->assertSame($before, $this->state());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_full_migration_and_revert_round_trip(): void
    {
        $original = $this->state();

        // Fase 1: copia verificada; el disco público no cambia.
        $this->artisan('photos:move-to-private', ['--copy' => true])->assertSuccessful();
        Storage::disk('photos')->assertExists([$this->photo->path, $this->photo->thumbnail_path]);
        Storage::disk('public')->assertExists([$this->photo->path, $this->photo->thumbnail_path]);
        Storage::disk('photos')->assertMissing('photos/999/orphan.webp');

        // Fase 2: borrado de las copias públicas (con confirmación).
        $this->artisan('photos:move-to-private', ['--remove-public' => true])
            ->expectsConfirmation('¿Borrar 2 copias públicas? Las privadas son idénticas.', 'yes')
            ->assertSuccessful();
        Storage::disk('public')->assertMissing([$this->photo->path, $this->photo->thumbnail_path]);
        Storage::disk('public')->assertExists('photos/999/orphan.webp');
        $this->assertSame('FULL', Storage::disk('photos')->get($this->photo->path));

        // La base de datos no cambia y la foto se sigue sirviendo.
        $this->assertSame("photos/{$this->photo->id}/photo_a.webp", $this->photo->fresh()->path);
        $this->actingAs($this->photo->user)->get($this->photo->fileUrl())->assertOk();

        // Revertir en orden inverso deja todo como al principio.
        [$copyManifest, $removeManifest] = $this->manifests();
        $this->artisan('photos:move-to-private', ['--revert' => $removeManifest])->assertSuccessful();
        $this->artisan('photos:move-to-private', ['--revert' => $copyManifest])->assertSuccessful();

        $this->assertSame($original, $this->state());
    }

    public function test_public_copies_are_kept_when_removal_is_not_confirmed(): void
    {
        $this->artisan('photos:move-to-private', ['--copy' => true])->assertSuccessful();

        $this->artisan('photos:move-to-private', ['--remove-public' => true])
            ->expectsConfirmation('¿Borrar 2 copias públicas? Las privadas son idénticas.', 'no')
            ->assertFailed();

        Storage::disk('public')->assertExists([$this->photo->path, $this->photo->thumbnail_path]);
    }

    public function test_public_copy_is_not_removed_when_the_private_one_differs(): void
    {
        Storage::disk('photos')->put($this->photo->path, 'TAMPERED');

        $this->artisan('photos:move-to-private', ['--remove-public' => true])
            ->expectsOutputToContain('DIFERENTE en ambos discos')
            ->expectsOutputToContain('No hay copias públicas verificadas que borrar.')
            ->assertSuccessful();

        $this->assertSame('FULL', Storage::disk('public')->get($this->photo->path));
    }

    public function test_only_one_phase_can_run_at_a_time(): void
    {
        $this->artisan('photos:move-to-private', ['--copy' => true, '--remove-public' => true])->assertFailed();

        Storage::disk('photos')->assertMissing($this->photo->path);
    }

    private function state(): array
    {
        $read = fn (string $disk) => collect(Storage::disk($disk)->allFiles())
            ->mapWithKeys(fn ($path) => [$path => Storage::disk($disk)->get($path)])
            ->sortKeys()
            ->all();

        return ['public' => $read('public'), 'photos' => $read('photos'), 'db' => Photo::all()->toArray()];
    }

    /**
     * @return array{string, string} Manifiestos de copy y remove-public.
     */
    private function manifests(): array
    {
        $files = collect(Storage::disk('local')->files('photo-migration'))->map(fn ($path) => basename($path));

        return [
            $files->first(fn ($name) => str_ends_with($name, '-copy.json')),
            $files->first(fn ($name) => str_ends_with($name, '-remove-public.json')),
        ];
    }
}
