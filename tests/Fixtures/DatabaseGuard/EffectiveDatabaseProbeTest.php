<?php

namespace Tests\Fixtures\DatabaseGuard;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sonda para TestDatabaseGuardTest (no forma parte de la suite): se ejecuta
 * en un proceso PHPUnit aparte y solo informa de la base efectiva. No usa
 * RefreshDatabase ni escribe nada.
 */
class EffectiveDatabaseProbeTest extends TestCase
{
    public function test_report_effective_database(): void
    {
        fwrite(STDERR, 'PROBE_EFFECTIVE_DATABASE=' . DB::selectOne('select database() as name')->name . PHP_EOL);

        $this->assertTrue(true);
    }
}
