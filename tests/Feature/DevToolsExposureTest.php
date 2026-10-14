<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\DevTools\DevTools;
use Tests\TestCase;

/**
 * Inertia DevTools solo puede estar activo en local.
 */
class DevToolsExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_devtools_routes_are_not_registered_outside_local(): void
    {
        $this->assertFalse(DevTools::enabled());

        $this->get('/_inertia/devtools/entries')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/_inertia/devtools/entries')->assertNotFound();
    }

    /**
     * @return array<string, array{string, ?bool, bool}>
     */
    public static function environments(): array
    {
        return [
            'producción sin flag' => ['production', null, false],
            'producción con flag activado' => ['production', true, false],
            'staging con flag activado' => ['staging', true, false],
            'testing con flag activado' => ['testing', true, false],
            'local sin flag' => ['local', null, true],
            'local con flag desactivado' => ['local', false, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('environments')]
    public function test_devtools_is_forced_off_outside_local(string $environment, ?bool $flag, bool $expected): void
    {
        $original = $this->app['env'];

        try {
            $this->app['env'] = $environment;
            config(['inertia.devtools.enabled' => $flag]);

            (new AppServiceProvider($this->app))->register();

            $this->assertSame($expected, DevTools::enabled());
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_env_example_disables_devtools(): void
    {
        $this->assertMatchesRegularExpression(
            '/^INERTIA_DEVTOOLS_ENABLED=false$/m',
            file_get_contents(base_path('.env.example'))
        );
    }
}
