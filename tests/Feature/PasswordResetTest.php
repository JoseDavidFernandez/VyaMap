<?php

namespace Tests\Feature;

use App\Http\Responses\PasswordResetLinkRequestedResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'old-password-123';

    private const NEW_PASSWORD = 'new-secure-password-456';

    protected function setUp(): void
    {
        parent::setUp();

        // Nunca se envían correos reales.
        Notification::fake();
    }

    public function test_guest_can_open_the_forgot_password_page(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_authenticated_user_is_redirected_away_from_auth_pages(): void
    {
        $user = User::factory()->create();

        foreach (['/login', '/register', '/forgot-password', '/reset-password/some-token'] as $uri) {
            $this->actingAs($user)->get($uri)->assertRedirect('/');
        }
    }

    public function test_reset_link_is_sent_to_an_existing_user(): void
    {
        $user = $this->createUser();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', PasswordResetLinkRequestedResponse::MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_response_without_revealing_it(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody@example.test'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', PasswordResetLinkRequestedResponse::MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_repeated_requests_get_the_same_response(): void
    {
        $user = $this->createUser();

        $this->post('/forgot-password', ['email' => $user->email]);

        // La segunda petición está limitada por el broker, pero la respuesta
        // no cambia (cambiarla revelaría que la cuenta existe).
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', PasswordResetLinkRequestedResponse::MESSAGE)
            ->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_reset_link_opens_the_reset_page_with_the_token(): void
    {
        $user = $this->createUser();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            $this->assertStringContainsString('/reset-password/' . $notification->token, $url);

            $this->get('/reset-password/' . $notification->token . '?email=' . urlencode($user->email))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Auth/ResetPassword')
                    ->where('token', $notification->token)
                    ->where('email', $user->email)
                );

            return true;
        });
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertRedirect('/login')
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));

        // El token es de un solo uso.
        $this->assertFalse(Password::broker()->tokenExists($user->fresh(), $token));

        $this->get('/login')
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->whereType('status', 'string')
            );
    }

    public function test_invalid_token_does_not_change_the_password(): void
    {
        $user = $this->createUser();

        $this->from('/reset-password/invalid-token')
            ->post('/reset-password', [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_expired_token_does_not_change_the_password(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->from('/reset-password/' . $token)
            ->post('/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_token_for_another_user_is_rejected(): void
    {
        $user = $this->createUser();
        $other = $this->createUser('other@example.test');
        $token = Password::broker()->createToken($other);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $other->fresh()->password));
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'something-else-789',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    public function test_new_password_must_meet_the_password_rules(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
    }

    private function createUser(string $email = 'traveller@example.test'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => self::OLD_PASSWORD,
        ]);
    }
}
