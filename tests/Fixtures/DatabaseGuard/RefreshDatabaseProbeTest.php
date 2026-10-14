<?php

namespace Tests\Fixtures\DatabaseGuard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sonda para TestDatabaseGuardTest (no forma parte de la suite): usa el
 * RefreshDatabase real. Solo se lanza con configuraciones no autorizadas y
 * contra un puerto sin servidor, para comprobar que la protección la detiene
 * antes de migrar.
 */
class RefreshDatabaseProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_body_must_never_run(): void
    {
        fwrite(STDERR, 'PROBE_BODY_REACHED' . PHP_EOL);

        $this->assertTrue(true);
    }
}
