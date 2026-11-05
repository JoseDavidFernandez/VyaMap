<?php

namespace App\Http\Middleware;

use App\Http\Responses\PasswordResetLinkRequestedResponse;
use App\Support\DemoAccount;
use App\Support\Registration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege las rutas de autenticación que Fortify registra sin límites.
 *
 * Se aplica a todas las rutas de Fortify (config/fortify.php) y a la página
 * de registro; actúa según el nombre de la ruta para que funcione también
 * con las rutas cacheadas.
 *
 * - Registro: 404 si está desactivado o se ha alcanzado el máximo de cuentas
 *   (App\Support\Registration).
 * - Registro, enlace de recuperación, restablecimiento y reenvío del enlace
 *   de verificación: limitadores definidos en AppServiceProvider.
 * - Cuenta de demostración (App\Support\DemoAccount): no puede pedir el
 *   enlace de recuperación (misma respuesta que cualquier correo, sin envío)
 *   ni restablecer la contraseña.
 */
class GuardAuthEndpoints
{
    private const LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'password-email',
        'password.update' => 'password-reset',
        'verification.send' => 'verification-email',
    ];

    public function __construct(private ThrottleRequests $throttle)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if (in_array($name, ['register', 'register.store'], true) && ! Registration::isOpen()) {
            abort(404);
        }

        // Cuenta de demostración: no se envía el enlace, pero la respuesta (y el
        // limitador) son los de cualquier otro correo, para no revelar cuál es.
        if (DemoAccount::matches((string) $request->input('email'))) {
            if ($name === 'password.email') {
                $next = fn (Request $request) => (new PasswordResetLinkRequestedResponse)->toResponse($request);
            } elseif ($name === 'password.update') {
                throw ValidationException::withMessages(['email' => DemoAccount::NOTICE]);
            }
        }

        if (isset(self::LIMITERS[$name])) {
            return $this->throttle->handle($request, $next, self::LIMITERS[$name]);
        }

        return $next($request);
    }
}
