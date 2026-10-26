<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Proxies de confianza (config/trustedproxy.php, TRUSTED_PROXIES): detrás de
 * un proxy que termina HTTPS, la aplicación debe ver la petición como HTTPS
 * solo si el proxy es de confianza. Sin base de datos.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__proxy-probe', fn () => response()->json([
            'secure' => request()->isSecure(),
            'url' => url('/trips'),
            'ip' => request()->ip(),
        ]));
    }

    public function test_forwarded_headers_are_ignored_without_trusted_proxies(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->probe()
            ->assertJson(['secure' => false, 'url' => 'http://localhost/trips', 'ip' => '10.0.0.2']);
    }

    public function test_a_trusted_proxy_makes_the_request_https_and_keeps_the_client_ip(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->probe()
            ->assertJson(['secure' => true, 'url' => 'https://localhost/trips', 'ip' => '203.0.113.7']);
    }

    public function test_only_the_listed_proxies_are_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.9, 10.0.0.10']);

        $this->probe()->assertJson(['secure' => false, 'ip' => '10.0.0.2']);
    }

    private function probe()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443', 'X-Forwarded-For' => '203.0.113.7'])
            ->getJson('/__proxy-probe')
            ->assertOk();
    }
}
