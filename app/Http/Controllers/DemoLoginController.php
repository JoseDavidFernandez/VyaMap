<?php

namespace App\Http\Controllers;

use App\Support\DemoAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * «Try the demo»: inicia la sesión de la cuenta de demostración desde el
 * servidor. El navegador no recibe ni envía ninguna credencial.
 *
 * 404 si DEMO_USER_EMAIL no está configurado o la cuenta no existe. Límite
 * propio por IP: al superarlo, error de validación junto al botón (no una
 * página 429).
 */
class DemoLoginController extends Controller
{
    public const MAX_ATTEMPTS_PER_MINUTE = 10;

    public function __invoke(Request $request): RedirectResponse
    {
        $user = DemoAccount::user();

        abort_if($user === null, 404);

        $key = 'demo-login|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'demo' => 'Too many demo sign-ins. Try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ]);
        }

        RateLimiter::hit($key, 60);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
