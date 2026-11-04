<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Máximo de cuentas del registro público (REGISTRATION_MAX_USERS) y
 * users:list.
 */
class RegistrationLimitTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'mi-viaje-a-kioto-2026';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.registration_enabled' => true]);
    }

    public function test_registration_is_unlimited_without_a_maximum(): void
    {
        config(['auth.registration_max_users' => null]);
        User::factory()->count(3)->create();

        $this->post('/register', $this->registration())->assertRedirect('/');

        $this->assertDatabaseCount('users', 4);
    }

    public function test_the_last_free_place_can_be_taken_and_then_registration_closes(): void
    {
        config(['auth.registration_max_users' => 3]);
        User::factory()->count(2)->create();

        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', true));
        $this->post('/register', $this->registration())->assertRedirect('/');
        $this->assertDatabaseCount('users', 3);

        auth()->logout();

        $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', false));
        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->registration(['email' => 'late@example.test']))->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'late@example.test']);
    }

    public function test_a_full_registration_rejects_accounts_even_past_the_middleware(): void
    {
        // Simula dos altas simultáneas: la comprobación del middleware ya pasó.
        config(['auth.registration_max_users' => 1]);
        User::factory()->create();

        try {
            app(CreateNewUser::class)->create($this->registration());
            $this->fail('Debería rechazar el alta con el registro lleno.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_concurrent_registration_with_the_same_email_is_a_validation_error(): void
    {
        // Simula la otra alta: se guarda justo después de que la regla unique
        // haya comprobado que el correo estaba libre.
        $raced = false;
        DB::listen(function (QueryExecuted $query) use (&$raced) {
            if (! $raced && str_contains($query->sql, 'count(*)') && in_array('new@example.test', $query->bindings, true)) {
                $raced = true;
                User::factory()->create(['email' => 'new@example.test']);
            }
        });

        $this->from('/register')
            ->post('/register', $this->registration())
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_a_maximum_of_zero_closes_registration(): void
    {
        config(['auth.registration_max_users' => 0]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->registration())->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_console_can_create_accounts_beyond_the_maximum(): void
    {
        config(['auth.registration_max_users' => 1]);
        User::factory()->create();

        $this->artisan('users:create', ['name' => 'Admin', 'email' => 'admin@example.test'])
            ->expectsQuestion('Contraseña', self::STRONG_PASSWORD)
            ->expectsQuestion('Repite la contraseña', self::STRONG_PASSWORD)
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.test']);
    }

    /**
     * @return array<string, array{?string, ?int}>
     */
    public static function maximumValues(): array
    {
        return [
            'sin definir' => [null, null],
            'vacío' => ['', null],
            'no numérico' => ['muchos', null],
            'cien' => ['100', 100],
            'cero' => ['0', 0],
            'negativo' => ['-5', 0],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maximumValues')]
    public function test_the_maximum_is_read_from_the_environment(?string $value, ?int $expected): void
    {
        $this->assertSame($expected, $this->withEnv('REGISTRATION_MAX_USERS', $value,
            fn () => (require config_path('auth.php'))['registration_max_users']));
    }

    public function test_users_list_shows_accounts_without_credentials(): void
    {
        config(['auth.registration_max_users' => 100]);
        $ana = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.test']);
        Trip::create(['user_id' => $ana->id, 'name' => 'Private trip name', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);
        $luis = User::factory()->unverified()->create(['name' => 'Luis', 'email' => 'luis@example.test']);

        $this->artisan('users:list')
            ->expectsTable(['ID', 'Nombre', 'Correo', 'Verificado', 'Alta', 'Viajes', 'Vuelos', 'Fotos'], [
                [$luis->id, 'Luis', 'luis@example.test', 'no', $luis->created_at->format('Y-m-d H:i'), 0, 0, 0],
                [$ana->id, 'Ana', 'ana@example.test', 'sí', $ana->created_at->format('Y-m-d H:i'), 1, 0, 0],
            ])
            ->expectsOutput('Cuentas: 2 de un máximo de 100 (se muestran 2). Registro: abierto.')
            ->doesntExpectOutputToContain('Private trip name')
            ->doesntExpectOutputToContain('$2y$')
            ->assertSuccessful();
    }

    public function test_users_list_respects_the_limit_and_does_not_change_data(): void
    {
        config(['auth.registration_enabled' => false]);
        User::factory()->count(3)->create();
        $before = User::orderBy('id')->get()->toArray();

        $this->artisan('users:list', ['--limit' => 1])
            ->expectsOutput('Cuentas: 3 (se muestran 1). Registro: cerrado.')
            ->assertSuccessful();

        $this->assertSame($before, User::orderBy('id')->get()->toArray());
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

    private function withEnv(string $key, ?string $value, callable $callback): mixed
    {
        $backup = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            return $callback();
        } finally {
            [$env, $server, $getenv] = $backup;
            $env === null ? $_ENV = array_diff_key($_ENV, [$key => 1]) : $_ENV[$key] = $env;
            $server === null ? $_SERVER = array_diff_key($_SERVER, [$key => 1]) : $_SERVER[$key] = $server;
            $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");
        }
    }
}
