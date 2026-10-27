<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verificación del correo (auth.email_verification): registro, acceso sin
 * verificar, enlace, reenvío con límite y altas por consola.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'mi-viaje-a-kioto-2026';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['auth.email_verification' => true]);
    }

    public function test_registration_sends_the_link_and_asks_for_verification(): void
    {
        config(['auth.registration_enabled' => true]);

        $this->post('/register', $this->registration())->assertRedirect('/');

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $this->get('/')->assertRedirect('/email/verify');
        $this->get('/email/verify')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', 'new@example.test'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privatePages(): array
    {
        return array_map(fn ($uri) => [$uri], [
            'dashboard' => '/',
            'viajes' => '/trips',
            'nuevo viaje' => '/trips/create',
            'fotos' => '/photos',
            'perfil' => '/profile',
            'editar perfil' => '/profile/edit',
            'países' => '/countries',
            'pasaporte' => '/passport',
            'vuelos' => '/flight-history',
            'buscar ciudades' => '/cities/search?q=ma',
            'buscar aeropuertos' => '/airports/search?q=ma',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('privatePages')]
    public function test_an_unverified_account_cannot_open_private_pages(string $uri): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get($uri)
            ->assertRedirect('/email/verify');
    }

    public function test_an_unverified_account_cannot_write(): void
    {
        $user = User::factory()->unverified()->create();
        $trip = Trip::create(['user_id' => $user->id, 'name' => 'Own trip', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);

        $this->actingAs($user)
            ->postJson('/trips', ['name' => 'New', 'start_date' => '2026-02-01', 'end_date' => '2026-02-02'])
            ->assertForbidden();
        $this->actingAs($user)->deleteJson("/trips/{$trip->id}")->assertForbidden();
        $this->actingAs($user)->delete("/trips/{$trip->id}")->assertRedirect('/email/verify');

        $this->assertSame(['Own trip'], Trip::pluck('name')->all());
    }

    public function test_a_verified_account_uses_the_application(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk();
        $this->actingAs($user)->get('/email/verify')->assertRedirect('/');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/email/verify')->assertRedirect('/login');
        $this->post('/email/verification-notification')->assertRedirect('/login');
    }

    public function test_the_emailed_link_verifies_the_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/email/verification-notification');

        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$url) {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->assertStringContainsString('/email/verify/' . $user->id . '/', $url);

        $this->actingAs($user)->get($url)->assertRedirect('/?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get('/')->assertOk();
    }

    public function test_invalid_links_do_not_verify(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $link = fn (User $for, string $hash, int $minutes = 60) => URL::temporarySignedRoute(
            'verification.verify', now()->addMinutes($minutes), ['id' => $for->id, 'hash' => $hash],
        );

        $this->actingAs($user)->get($link($user, sha1('wrong@example.test')))->assertForbidden();
        $this->actingAs($user)->get($link($other, sha1($other->email)))->assertForbidden();
        $this->actingAs($user)->get($link($user, sha1($user->email), -1))->assertForbidden();
        $this->actingAs($user)->get($link($user, sha1($user->email)) . 'x')->assertForbidden();
        $this->actingAs($user)->get("/email/verify/{$user->id}/" . sha1($user->email))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_the_link_can_be_resent_with_a_limit(): void
    {
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)
                ->from('/email/verify')
                ->post('/email/verification-notification')
                ->assertRedirect('/email/verify')
                ->assertSessionHas('status', 'verification-link-sent');
        }

        $this->actingAs($user)->post('/email/verification-notification')->assertTooManyRequests();

        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
    }

    public function test_a_verified_account_gets_no_new_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/email/verification-notification')->assertRedirect('/');

        Notification::assertNothingSent();
    }

    public function test_unverified_accounts_can_sign_out(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_without_verification_nothing_is_required_or_sent(): void
    {
        config(['auth.email_verification' => false, 'auth.registration_enabled' => true]);

        $this->post('/register', $this->registration())->assertRedirect('/');
        $this->get('/')->assertOk();
        $this->get('/email/verify')->assertRedirect('/');

        Notification::assertNothingSent();
    }

    public function test_console_accounts_are_verified(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->artisan('users:create', ['name' => 'Ana', 'email' => 'ana@example.test'])
            ->expectsQuestion('Contraseña', self::STRONG_PASSWORD)
            ->expectsQuestion('Repite la contraseña', self::STRONG_PASSWORD)
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'ana@example.test')->firstOrFail()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_users_verify_marks_only_that_account(): void
    {
        $ana = User::factory()->unverified()->create(['email' => 'ana@example.test']);
        $luis = User::factory()->unverified()->create(['email' => 'luis@example.test']);

        $ana->forceFill(['updated_at' => '2026-01-01 10:00:00'])->saveQuietly();
        $before = DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->artisan('users:verify', ['email' => 'ana@example.test'])
            ->expectsConfirmation("¿Marcar como verificado el correo de la cuenta {$ana->id} (ana@example.test)?", 'no')
            ->assertFailed();
        $this->assertFalse($ana->fresh()->hasVerifiedEmail());

        $this->artisan('users:verify', ['email' => 'ana@example.test', '--force' => true])->assertSuccessful();

        // Solo cambia email_verified_at de esa fila: ni updated_at ni otras cuentas.
        $after = DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $this->assertNotNull($after[0]['email_verified_at']);
        $after[0]['email_verified_at'] = null;
        $this->assertSame($before, $after);
        $this->assertSame('2026-01-01 10:00:00', (string) DB::table('users')->where('id', $ana->id)->value('updated_at'));
        $this->assertFalse($luis->fresh()->hasVerifiedEmail());

        $this->artisan('users:verify', ['email' => 'ana@example.test'])
            ->expectsOutput('El correo de esa cuenta ya está verificado. No se ha cambiado nada.')
            ->assertSuccessful();
        $this->artisan('users:verify', ['email' => 'nadie@example.test'])->assertFailed();
    }

    /**
     * @return array<string, array{string, ?string, bool}>
     */
    public static function defaults(): array
    {
        return [
            'producción sin definir' => ['production', null, true],
            'testing sin definir' => ['testing', null, true],
            'manual sin definir' => ['manual', null, true],
            'local sin definir' => ['local', null, false],
            'producción desactivada' => ['production', 'false', false],
            'local activada' => ['local', 'true', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('defaults')]
    public function test_verification_is_required_by_default_outside_local(string $environment, ?string $flag, bool $expected): void
    {
        $keys = ['APP_ENV' => $environment, 'EMAIL_VERIFICATION' => $flag];
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
            $this->assertSame($expected, (require config_path('auth.php'))['email_verification']);
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
