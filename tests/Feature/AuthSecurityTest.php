<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\NotPwnedVerifier;
use Tests\TestCase;

/**
 * Registro, política de contraseñas, límites de frecuencia y alta por consola.
 */
class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'mi-viaje-a-kioto-2026';

    public function test_registration_is_enabled_by_default_only_in_local(): void
    {
        $this->assertTrue($this->registrationDefaultFor('local'));
        $this->assertFalse($this->registrationDefaultFor('production'));
        $this->assertFalse($this->registrationDefaultFor('staging'));
        $this->assertTrue($this->registrationDefaultFor('production', 'true'));
        $this->assertFalse($this->registrationDefaultFor('local', 'false'));
    }

    public function test_disabled_registration_hides_the_form_and_rejects_new_accounts(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->registration())->assertNotFound();
        $this->assertDatabaseCount('users', 0);

        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', false));
    }

    public function test_login_keeps_working_when_registration_is_disabled(): void
    {
        config(['auth.registration_enabled' => false]);
        $user = User::factory()->create(['password' => self::STRONG_PASSWORD]);

        $this->post('/login', ['email' => $user->email, 'password' => self::STRONG_PASSWORD])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_enabled_registration_creates_the_account(): void
    {
        config(['auth.registration_enabled' => true]);

        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', true));
        $this->get('/register')->assertOk();

        $this->post('/register', $this->registration())->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'new@example.test']);
        $this->assertAuthenticated();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'menos de 10 caracteres' => ['kioto-26'],
            'filtrada' => ['password1234'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('weakPasswords')]
    public function test_registration_rejects_weak_passwords(string $password): void
    {
        config(['auth.registration_enabled' => true]);

        $this->from('/register')
            ->post('/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('weakPasswords')]
    public function test_password_reset_rejects_weak_passwords(string $password): void
    {
        $user = User::factory()->create(['password' => self::STRONG_PASSWORD]);
        $token = PasswordBroker::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertTrue(password_verify(self::STRONG_PASSWORD, $user->fresh()->password));
    }

    public function test_real_verifier_sends_only_the_hash_prefix_and_rejects_leaked_passwords(): void
    {
        $this->app->instance(UncompromisedVerifier::class, new NotPwnedVerifier($this->app->make(HttpFactory::class)));

        $leaked = 'kioto-leaked-2026';
        $hash = strtoupper(sha1($leaked));

        Http::fake([
            'api.pwnedpasswords.com/range/*' => Http::response(substr($hash, 5) . ":42\r\n0000000000000000000000000000000000A:1"),
        ]);

        config(['auth.registration_enabled' => true]);

        $this->post('/register', $this->registration(['password' => $leaked, 'password_confirmation' => $leaked]))
            ->assertSessionHasErrors('password');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.pwnedpasswords.com/range/' . substr($hash, 0, 5)
            && ! str_contains($request->url(), substr($hash, 5)));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        config(['auth.registration_enabled' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['email' => 'not-an-email'])->assertStatus(302);
        }

        $this->post('/register', $this->registration())->assertTooManyRequests();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reset_link_requests_are_rate_limited_per_ip_and_per_email(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => "nobody{$i}@example.test"])->assertStatus(302);
        }
        $this->post('/forgot-password', ['email' => 'other@example.test'])->assertTooManyRequests();

        // Misma dirección desde IP distintas: máximo 3 cada 15 minutos.
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post('/forgot-password', ['email' => 'victim@example.test'])
                ->assertStatus(302);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->post('/forgot-password', ['email' => 'Victim@example.test'])
            ->assertTooManyRequests();
    }

    public function test_password_reset_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', ['token' => 'x', 'email' => 'a@example.test'])->assertStatus(302);
        }

        $this->post('/reset-password', ['token' => 'x', 'email' => 'a@example.test'])->assertTooManyRequests();
    }

    public function test_accounts_can_be_created_from_the_console_with_the_same_rules(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->artisan('users:create', ['name' => 'Ana', 'email' => 'ana@example.test'])
            ->expectsQuestion('Contraseña', 'password1234')
            ->expectsQuestion('Repite la contraseña', 'password1234')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);

        $this->artisan('users:create', ['name' => 'Ana', 'email' => 'ana@example.test'])
            ->expectsQuestion('Contraseña', self::STRONG_PASSWORD)
            ->expectsQuestion('Repite la contraseña', self::STRONG_PASSWORD)
            ->assertSuccessful();

        $user = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertTrue(password_verify(self::STRONG_PASSWORD, $user->password));
    }

    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'New traveller',
            'email' => 'new@example.test',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
            ...$overrides,
        ];
    }

    /**
     * Evalúa config/auth.php con otro APP_ENV (y REGISTRATION_ENABLED opcional).
     */
    private function registrationDefaultFor(string $environment, ?string $flag = null): bool
    {
        $keys = ['APP_ENV' => $environment, 'REGISTRATION_ENABLED' => $flag];
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
            return (require config_path('auth.php'))['registration_enabled'];
        } finally {
            foreach ($backup as $key => [$env, $server, $getenv]) {
                $env === null ? $_ENV = array_diff_key($_ENV, [$key => 1]) : $_ENV[$key] = $env;
                $server === null ? $_SERVER = array_diff_key($_SERVER, [$key => 1]) : $_SERVER[$key] = $server;
                $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");
            }
        }
    }
}
