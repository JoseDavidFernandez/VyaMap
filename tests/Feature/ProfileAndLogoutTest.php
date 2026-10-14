<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 05, bloque B2: cambio del nombre del perfil y cierre de sesión.
 *
 * El avatar ya está cubierto en UploadSecurityTest. El logout es el de
 * Fortify (AuthenticatedSessionController@destroy).
 */
class ProfileAndLogoutTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'mi-viaje-a-kioto-2026';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('vyamap_testing', DB::selectOne('select database() as d')->d);

        $this->user = User::factory()->create(['name' => 'Nombre inicial', 'password' => self::PASSWORD]);
    }

    public function test_owner_changes_the_profile_name_as_the_edit_form_sends_it(): void
    {
        $other = User::factory()->create(['name' => 'Otra persona']);
        $before = $this->user->fresh()->getAttributes();
        $otherBefore = $other->fresh()->getAttributes();

        // Profile/Edit.vue envía POST con _method=put (FormData).
        $this->actingAs($this->user)
            ->from('/profile/edit')
            ->post('/profile', ['_method' => 'put', 'name' => 'Nombre nuevo'])
            ->assertRedirect('/profile')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Profile updated successfully.');

        $after = $this->user->fresh()->getAttributes();
        $this->assertSame('Nombre nuevo', $after['name']);

        // Solo cambia el nombre (y updated_at).
        unset($before['name'], $before['updated_at'], $after['name'], $after['updated_at']);
        $this->assertSame($before, $after);
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());

        $this->actingAs($this->user->fresh())
            ->get('/profile')
            ->assertInertia(fn ($page) => $page
                ->component('Profile')
                ->where('user.name', 'Nombre nuevo')
                ->where('auth.user.name', 'Nombre nuevo')
            );
    }

    public function test_only_the_name_is_taken_from_the_request(): void
    {
        $other = User::factory()->create();
        $before = $this->user->fresh()->getAttributes();

        $this->actingAs($this->user)
            ->put('/profile', [
                'name' => 'Nombre nuevo',
                'email' => 'cambiado@example.test',
                'password' => 'otra-clave-muy-larga-2026',
                'id' => $other->id,
                'user_id' => $other->id,
            ])
            ->assertRedirect('/profile');

        $after = $this->user->fresh();
        $this->assertSame('Nombre nuevo', $after->name);
        $this->assertSame($before['email'], $after->email);
        $this->assertSame($before['password'], $after->getAttributes()['password']);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->assertSame($other->email, $other->fresh()->email);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidNames(): array
    {
        return [
            'sin nombre' => [[]],
            'nombre vacío' => [['name' => '']],
            'solo espacios' => [['name' => '   ']],
            'nombre de 256 caracteres' => [['name' => str_repeat('a', 256)]],
            'no es texto' => [['name' => ['Ana']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected_and_nothing_changes(array $data): void
    {
        $before = $this->user->fresh()->getAttributes();

        $this->actingAs($this->user)
            ->from('/profile/edit')
            ->put('/profile', $data)
            ->assertRedirect('/profile/edit')
            ->assertSessionHasErrors('name');

        $this->assertSame($before, $this->user->fresh()->getAttributes());
    }

    public function test_name_limit_is_inclusive(): void
    {
        $this->actingAs($this->user)
            ->put('/profile', ['name' => str_repeat('a', 255)])
            ->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('a', 255), $this->user->fresh()->name);
    }

    public function test_guests_cannot_change_a_profile_name(): void
    {
        $this->put('/profile', ['name' => 'Intruso'])->assertRedirect('/login');

        $this->assertSame('Nombre inicial', $this->user->fresh()->name);
    }

    public function test_logout_invalidates_the_session_and_redirects_home(): void
    {
        $this->login();

        $session = $this->app['session.store'];
        $session->put('marker', 'antes del logout');
        $session->save();
        $oldId = $session->getId();

        // El cliente de pruebas no conserva la cookie de sesión entre
        // peticiones: se envía como lo haría el navegador.
        $cookie = config('session.cookie');

        // Control: antes del logout, esa cookie autentica por sí sola.
        $this->app['auth']->forgetGuards();
        $this->withCookie($cookie, $oldId)->get('/')->assertOk();
        $this->assertAuthenticatedAs($this->user);
        $this->assertSame($oldId, $session->getId());
        $oldToken = $session->token();
        $this->assertStringContainsString('marker', $session->getHandler()->read($oldId));

        // Navbar.vue: router.post('/logout') (petición Inertia).
        $this->withCookie($cookie, $oldId)
            ->post('/logout', [], ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($oldId, $session->getId());
        $this->assertNotSame($oldToken, $session->token());
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', (string) $session->token());
        $this->assertFalse($session->has('marker'));
        $this->assertSame('', $session->getHandler()->read($oldId));

        // La cookie antigua ya no autentica (sin el usuario en memoria del guard),
        // y el destino «/» exige sesión: el usuario acaba en el login.
        $this->app['auth']->forgetGuards();
        $this->withCookie($cookie, $oldId)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_rotates_the_remember_me_token(): void
    {
        $this->login(remember: true);
        $rememberToken = $this->user->fresh()->remember_token;
        $this->assertNotEmpty($rememberToken);

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($rememberToken, $this->user->fresh()->remember_token);
    }

    public function test_logout_only_accepts_post_and_requires_a_session(): void
    {
        $this->login();
        $this->get('/logout')->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($this->user);

        $this->post('/logout')->assertRedirect('/');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function login(bool $remember = false): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => self::PASSWORD, 'remember' => $remember ? 'on' : null])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($this->user);
    }
}
