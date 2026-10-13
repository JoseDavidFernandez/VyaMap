<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test debe llamar a servicios externos reales: las llamadas
        // sin Http::fake() fallan. La clave es ficticia.
        Http::preventStrayRequests();

        config(['services.geoapify.key' => 'testing-key']);

        // Las vistas Inertia no deben depender de un build previo de Vite.
        $this->withoutVite();
    }
}
