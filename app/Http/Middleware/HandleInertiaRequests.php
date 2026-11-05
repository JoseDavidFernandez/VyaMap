<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            // Solo los campos que usa la interfaz (ver resources/js/types):
            // el modelo completo incluiría columnas de 2FA y marcas internas.
            'auth' => [
                'user' => fn () => $request->user()
                    ? [...$request->user()->only(['id', 'name', 'email', 'avatar']), 'is_demo' => $request->user()->isDemo()]
                    : null,
            ],

            // Aviso de la cuenta de demostración cuando el servidor rechaza una
            // escritura (App\Http\Middleware\DemoReadOnly).
            'flash' => [
                'demoNotice' => fn () => $request->session()->get('demo_notice'),
            ],

            // Solo para ocultar los campos: el servidor aplica la restricción.
            'features' => [
                'uploads' => (bool) config('uploads.enabled'),
                // El apartado Photos solo tiene sentido para quien ya tiene
                // fotos: las subidas están desactivadas (config/uploads.php).
                'photos' => (bool) $request->user()?->photos()->exists(),
            ],
        ];
    }
}
