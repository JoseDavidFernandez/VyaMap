<?php

namespace Tests\Feature;

use App\Http\Kernel;
use App\Http\Middleware\TrustHosts;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

/**
 * La cabecera Host la controla quien envía la petición: los enlaces de los
 * correos salen siempre de APP_URL (AppServiceProvider) y TrustHosts rechaza
 * cualquier otro host fuera de local.
 */
class HostHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_uses_app_url_even_with_a_foreign_host(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'traveller@example.test']);

        $this->post('http://attacker.example/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            $this->assertStringStartsWith(rtrim(config('app.url'), '/') . '/reset-password/' . $notification->token, $url);
            $this->assertStringNotContainsString('attacker.example', $url);

            return true;
        });
    }

    public function test_trust_hosts_runs_on_every_request(): void
    {
        $this->assertTrue($this->app->make(Kernel::class)->hasMiddleware(TrustHosts::class));
    }

    public function test_trust_hosts_accepts_only_the_application_host(): void
    {
        // El framework no lo aplica en los tests: se fuerza como en producción.
        $middleware = new class($this->app) extends TrustHosts
        {
            protected function shouldSpecifyTrustedHosts(): bool
            {
                return true;
            }
        };

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        try {
            $request = Request::create('https://' . $appHost . '/login');
            $middleware->handle($request, fn () => response('ok'));
            $this->assertSame($appHost, $request->getHost());

            $request = Request::create('https://attacker.example/login');
            $middleware->handle($request, fn () => response('ok'));
            $this->expectException(SuspiciousOperationException::class);
            $request->getHost();
        } finally {
            Request::setTrustedHosts([]);
        }
    }
}
