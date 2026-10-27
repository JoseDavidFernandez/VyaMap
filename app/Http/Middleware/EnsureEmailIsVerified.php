<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as BaseMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige el correo verificado solo si auth.email_verification está activa.
 *
 * Sin verificar: redirección a /email/verify, o 403 en peticiones JSON.
 */
class EnsureEmailIsVerified extends BaseMiddleware
{
    public function handle($request, Closure $next, $redirectToRoute = null): Response
    {
        if (! config('auth.email_verification')) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
