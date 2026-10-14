<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege las rutas de autenticación que Fortify registra sin límites.
 *
 * Se aplica a todas las rutas de Fortify (config/fortify.php) y a la página
 * de registro; actúa según el nombre de la ruta para que funcione también
 * con las rutas cacheadas.
 *
 * - Registro: 404 si está desactivado (auth.registration_enabled).
 * - Registro, enlace de recuperación y restablecimiento: limitadores
 *   definidos en AppServiceProvider.
 */
class GuardAuthEndpoints
{
    private const LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'password-email',
        'password.update' => 'password-reset',
    ];

    public function __construct(private ThrottleRequests $throttle)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if (in_array($name, ['register', 'register.store'], true) && ! config('auth.registration_enabled')) {
            abort(404);
        }

        if (isset(self::LIMITERS[$name])) {
            return $this->throttle->handle($request, $next, self::LIMITERS[$name]);
        }

        return $next($request);
    }
}
