<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SyntheticExifJpeg;
use Tests\TestCase;

/**
 * Avatares sin metadatos, límites de dimensiones y de frecuencia de subida.
 */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_profile_avatar_is_reencoded_without_exif(): void
    {
        $user = User::factory()->create();
        Storage::disk('public')->put('avatars/previous.jpg', 'old');
        $user->update(['avatar' => 'avatars/previous.jpg']);

        $this->actingAs($user)
            ->put('/profile', ['name' => 'Ana', 'avatar' => $this->exifJpeg(1200, 900)])
            ->assertRedirect('/profile');

        $avatar = $user->fresh()->avatar;

        $this->assertMatchesRegularExpression('#^avatars/[A-Za-z0-9]{40}\.webp$#', $avatar);
        $this->assertAvatarIsClean($avatar, 512, 384);
        Storage::disk('public')->assertMissing('avatars/previous.jpg');
        $this->assertCount(1, Storage::disk('public')->files('avatars'));
    }

    public function test_registration_avatar_is_reencoded_without_exif(): void
    {
        config(['auth.registration_enabled' => true]);

        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.test',
            'password' => 'mi-viaje-a-kioto-2026',
            'password_confirmation' => 'mi-viaje-a-kioto-2026',
            'avatar' => $this->exifJpeg(64, 48),
        ])->assertRedirect('/');

        $this->assertAvatarIsClean(User::firstOrFail()->avatar, 64, 48);
    }

    public function test_oversized_avatar_is_rejected_and_the_current_one_is_kept(): void
    {
        $user = User::factory()->create(['avatar' => 'avatars/current.webp']);
        Storage::disk('public')->put('avatars/current.webp', 'current');

        $this->actingAs($user)
            ->from('/profile/edit')
            ->put('/profile', ['name' => 'Ana', 'avatar' => $this->pngHeader(6000, 5000)])
            ->assertSessionHasErrors('avatar');

        $this->assertSame('avatars/current.webp', $user->fresh()->avatar);
        Storage::disk('public')->assertExists('avatars/current.webp');
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function oversizedPhotos(): array
    {
        return [
            'más de 50 MP' => [8000, 7000],
            'lado mayor de 12000 px' => [12001, 100],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('oversizedPhotos')]
    public function test_oversized_photos_are_rejected_before_queueing(int $width, int $height): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/photos', ['photo' => $this->pngHeader($width, $height)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('photos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_photo_at_the_limit_is_accepted(): void
    {
        // 50 MP exactos: solo se valida la cabecera, no se procesa aquí.
        config(['queue.default' => 'null']);

        $this->actingAs(User::factory()->create())
            ->postJson('/photos', ['photo' => $this->pngHeader(10000, 5000)])
            ->assertCreated();

        $this->assertSame('pending', Photo::firstOrFail()->processing_status);
    }

    public function test_photo_uploads_are_rate_limited_per_user(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->postJson('/photos', [])->assertUnprocessable();
        }

        $this->actingAs($user)->postJson('/photos', [])->assertTooManyRequests();

        // Otro usuario no se ve afectado.
        $this->actingAs(User::factory()->create())->postJson('/photos', [])->assertUnprocessable();
    }

    private function exifJpeg(int $width, int $height): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.jpg', SyntheticExifJpeg::bytes($width, $height));
    }

    /**
     * PNG válido de cabecera (IHDR + IDAT vacío + IEND) que declara unas
     * dimensiones sin reservar memoria para ellas.
     */
    public static function pngBytes(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress(''))
            . $chunk('IEND', '');
    }

    private function pngHeader(int $width, int $height): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('huge.png', self::pngBytes($width, $height));
    }

    private function assertAvatarIsClean(string $path, int $expectedWidth, int $expectedHeight): void
    {
        $bytes = Storage::disk('public')->get($path);

        $this->assertStringStartsWith('RIFF', $bytes);
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        $this->assertStringNotContainsString('Exif', $bytes);
        $this->assertStringNotContainsString('XMP ', $bytes);

        $size = getimagesizefromstring($bytes);
        $this->assertSame([$expectedWidth, $expectedHeight], [$size[0], $size[1]]);
    }
}
