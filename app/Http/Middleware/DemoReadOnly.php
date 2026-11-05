<?php

namespace App\Http\Middleware;

use App\Support\DemoAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuenta de demostración de solo lectura (App\Support\DemoAccount).
 *
 * - Cualquier petición que no sea de lectura (GET, HEAD, OPTIONS) se rechaza,
 *   salvo las de WRITABLE_ROUTES. Así quedan cubiertas también las rutas que
 *   se añadan más adelante.
 * - Las páginas de formulario (crear o editar) redirigen a la página que
 *   muestra esos datos.
 * - Respuesta: en Inertia y formularios, redirección con el aviso en la
 *   sesión (`demo_notice`, que muestra AppLayout); en JSON, 403 con el aviso.
 */
class DemoReadOnly
{
    /** Escrituras permitidas a la cuenta de demostración. */
    public const WRITABLE_ROUTES = [
        'logout',
        'password.confirm.store',   // solo comprueba la contraseña
    ];

    /** Páginas de formulario => ruta a la que se redirige. */
    public const FORM_PAGES = [
        'trips.create' => 'trips.index',
        'trips.edit' => 'trips.show',
        'profile.edit' => 'profile',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isDemo()) {
            return $next($request);
        }

        $route = $request->route();
        $name = $route?->getName();

        if (isset(self::FORM_PAGES[$name])) {
            $target = self::FORM_PAGES[$name];
            $parameters = $name === 'trips.edit' ? ['trip' => $route->parameter('trip')] : [];

            return redirect()->route($target, $parameters)->with('demo_notice', DemoAccount::NOTICE);
        }

        if ($request->isMethodSafe() || in_array($name, self::WRITABLE_ROUTES, true)) {
            return $next($request);
        }

        if (! $request->header('X-Inertia') && $request->expectsJson()) {
            return response()->json(['message' => DemoAccount::NOTICE], 403);
        }

        return redirect()->back(fallback: route('home'))->with('demo_notice', DemoAccount::NOTICE);
    }
}
