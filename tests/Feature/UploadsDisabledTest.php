<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UPLOADS_ENABLED=false: el servidor rechaza fotos y avatares aunque la
 * petición no venga de la interfaz.
 */
class UploadsDisabledTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'mi-viaje-a-kioto-2026';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        config(['uploads.enabled' => false]);
    }

    public function test_photo_uploads_are_rejected(): void
    {
        $user = User::factory()->create();
        $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);

        $this->actingAs($user)
            ->postJson('/photos', ['trip_id' => $trip->id, 'photo' => UploadedFile::fake()->image('test.jpg')])
            ->assertForbidden();

        $this->assertDatabaseCount('photos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_registration_rejects_an_avatar_but_works_without_one(): void
    {
        config(['auth.registration_enabled' => true]);

        $this->from('/register')
            ->post('/register', [...$this->registration(), 'avatar' => UploadedFile::fake()->image('me.jpg')])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('avatar');
        $this->assertDatabaseCount('users', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->post('/register', $this->registration())->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'avatar' => null]);
    }

    public function test_profile_rejects_a_new_avatar_but_keeps_the_existing_one(): void
    {
        $user = User::factory()->create(['name' => 'Ana', 'avatar' => 'avatars/existing.webp']);

        $this->actingAs($user)
            ->from('/profile/edit')
            ->put('/profile', ['name' => 'Ana', 'avatar' => UploadedFile::fake()->image('new.jpg')])
            ->assertRedirect('/profile/edit')
            ->assertSessionHasErrors('avatar');
        $this->assertSame('avatars/existing.webp', $user->fresh()->avatar);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->actingAs($user)
            ->put('/profile', ['name' => 'Ana María'])
            ->assertRedirect('/profile');
        $this->assertSame('Ana María', $user->fresh()->name);
        $this->assertSame('avatars/existing.webp', $user->fresh()->avatar);
    }

    public function test_existing_photos_are_still_listed(): void
    {
        $user = User::factory()->create();
        Photo::create(['user_id' => $user->id, 'path' => 'photos/1/p.webp', 'original_filename' => 'p.jpg', 'processing_status' => 'ready']);

        $this->actingAs($user)
            ->get('/photos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('photos', 1));
    }

    public function test_the_interface_receives_the_flag(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile/edit')
            ->assertInertia(fn ($page) => $page->where('features.uploads', false));

        config(['uploads.enabled' => true]);

        $this->actingAs($user)->get('/profile/edit')
            ->assertInertia(fn ($page) => $page->where('features.uploads', true));
    }

    /**
     * @return array<string, array{string, ?string, bool}>
     */
    public static function defaults(): array
    {
        return [
            'producción sin definir' => ['production', null, false],
            'local sin definir' => ['local', null, true],
            'testing sin definir' => ['testing', null, true],
            'producción activada' => ['production', 'true', true],
            'local desactivada' => ['local', 'false', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('defaults')]
    public function test_uploads_are_disabled_by_default_in_production(string $environment, ?string $flag, bool $expected): void
    {
        $keys = ['APP_ENV' => $environment, 'UPLOADS_ENABLED' => $flag];
        $backup = [];

        foreach ($keys as $key => $value) {
            $backup[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];

            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        try {
            $this->assertSame($expected, (require config_path('uploads.php'))['enabled']);
        } finally {
            foreach ($backup as $key => [$env, $server, $getenv]) {
                $env === null ? $_ENV = array_diff_key($_ENV, [$key => 1]) : $_ENV[$key] = $env;
                $server === null ? $_SERVER = array_diff_key($_SERVER, [$key => 1]) : $_SERVER[$key] = $server;
                $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");
            }
        }
    }

    private function registration(): array
    {
        return [
            'name' => 'New traveller',
            'email' => 'new@example.test',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ];
    }
}
