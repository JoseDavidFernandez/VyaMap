<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Con MAIL_MAILER=log los correos no salen: se escriben en el log, y los
 * enlaces de verificación y de recuperación se pueden copiar de ahí
 * (docs/deployment.md, sección 7.3). Aquí se envían de verdad, con el mailer
 * log, a un archivo temporal.
 */
class MailLogLinksTest extends TestCase
{
    use RefreshDatabase;

    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = sys_get_temp_dir() . '/vyamap-mail-log-' . uniqid() . '.log';

        config([
            'auth.email_verification' => true,
            'mail.default' => 'log',
            'mail.mailers.log.channel' => 'mail_test',
            'logging.channels.mail_test' => ['driver' => 'single', 'path' => $this->logFile, 'level' => 'debug'],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);

        parent::tearDown();
    }

    public function test_the_verification_link_can_be_copied_from_the_log(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ana@example.test']);

        $user->sendEmailVerificationNotification();

        $log = (string) file_get_contents($this->logFile);
        $this->assertStringContainsString('To: ana@example.test', $log);
        $this->assertMatchesRegularExpression('#/email/verify/' . $user->id . '/[0-9a-f]{40}\?expires=\d+(&amp;|&)signature=[0-9a-f]{64}#', $log);

        preg_match('#http\S*/email/verify/' . $user->id . '/[0-9a-f]{40}\?expires=\d+(?:&amp;|&)signature=[0-9a-f]{64}#', $log, $match);
        $url = html_entity_decode($match[0]);

        $this->actingAs($user)->get($url)->assertRedirect('/?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_password_reset_link_can_be_copied_from_the_log(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);

        $this->post('/forgot-password', ['email' => 'ana@example.test'])->assertSessionHasNoErrors();

        $log = (string) file_get_contents($this->logFile);
        $this->assertStringContainsString('To: ana@example.test', $log);
        $this->assertMatchesRegularExpression('#/reset-password/[0-9a-f]{64}\?email=ana%40example\.test#', $log);
        $this->assertTrue(Password::broker()->tokenExists(User::first(), $this->tokenFrom($log)));
    }

    private function tokenFrom(string $log): string
    {
        preg_match('#/reset-password/([0-9a-f]{64})#', $log, $match);

        return $match[1];
    }
}
