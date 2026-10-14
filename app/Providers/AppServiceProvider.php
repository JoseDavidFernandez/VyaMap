<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Inertia DevTools graba las props de cada página y en local sirve
        // /_inertia/devtools sin autenticación. Fuera de local se fuerza a
        // desactivado aunque INERTIA_DEVTOOLS_ENABLED diga lo contrario; se
        // hace en register() porque su proveedor registra las rutas en boot().
        if (! $this->app->environment('local')) {
            config(['inertia.devtools.enabled' => false]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registro, alta por consola y restablecimiento usan Password::default().
        // uncompromised() consulta Have I Been Pwned (k-anonimato: solo se envía
        // el prefijo del hash); si el servicio no responde, no bloquea.
        Password::defaults(fn () => Password::min(10)->uncompromised());

        $this->configureAuthRateLimiting();
    }

    /**
     * Limitadores de las rutas de Fortify sin límite propio
     * (ver App\Http\Middleware\GuardAuthEndpoints).
     */
    private function configureAuthRateLimiting(): void
    {
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by('minute|' . $request->ip()),
            Limit::perHour(20)->by('hour|' . $request->ip()),
        ]);

        RateLimiter::for('password-email', fn (Request $request) => [
            Limit::perMinute(5)->by('ip|' . $request->ip()),
            // Evita inundar de correos una misma dirección desde varias IP.
            Limit::perMinutes(15, 3)->by('email|' . Str::lower((string) $request->input('email'))),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);
    }
}
