<?php

namespace Tests;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public const COMPROMISED_PASSWORDS = ['password1234', 'qwertyuiop'];

    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test debe llamar a servicios externos reales: las llamadas
        // sin Http::fake() fallan. La clave es ficticia.
        Http::preventStrayRequests();

        config(['services.geoapify.key' => 'testing-key']);

        // Password::uncompromised() sin red: solo las contraseñas de
        // COMPROMISED_PASSWORDS cuentan como filtradas. AuthSecurityTest
        // prueba aparte el verificador real con Http::fake().
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data)
            {
                return ! in_array($data['value'], TestCase::COMPROMISED_PASSWORDS, true);
            }
        });

        // Las vistas Inertia no deben depender de un build previo de Vite.
        $this->withoutVite();
    }
}
