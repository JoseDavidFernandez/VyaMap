<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use App\Rules\ImageWithinPixelLimit;
use App\Services\AvatarImageService;
use App\Support\SovereignCountries;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /*
     * Página de la cuenta. Las cifras generales viven en el dashboard, el
     * progreso geográfico en Passport y la consulta de países en Countries;
     * aquí solo se envían los países visitados para el globo.
     */
    public function __invoke(): Response
    {
        $user = request()->user();

        // Solo lugares visitados (viajes completados).
        $countries = Visit::query()
            ->visitedBy($user->id)
            ->with('city.country')
            ->get()
            ->filter(fn ($visit) => $visit->city?->country)
            ->map(fn ($visit) => [
                'name' => $visit->city->country->name,
                'iso_code' => $visit->city->country->iso_code,
                'country_status' => SovereignCountries::classify($visit->city->country->iso_code),
            ])
            ->unique('iso_code')
            ->sortBy('name')
            ->values();

        return Inertia::render('Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'member_since' => $user->created_at?->format('Y-m-d'),
            ],

            'countries' => $countries,
        ]);
    }

    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('Profile/Edit', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
    }

    public function update(Request $request, AvatarImageService $avatars)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Con las subidas desactivadas se rechaza también en el servidor.
            'avatar' => config('uploads.enabled') ? [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                new ImageWithinPixelLimit(ImageWithinPixelLimit::AVATAR_MAX_PIXELS),
            ] : ['prohibited'],
        ]);

        $previousAvatar = $user->avatar;

        if ($request->hasFile('avatar')) {
            // Se guarda recodificado (sin EXIF); el anterior se borra solo
            // cuando el nuevo ya está guardado y asignado.
            $validated['avatar'] = $avatars->store($request->file('avatar'));
        } else {
            unset($validated['avatar']);
        }

        $user->update($validated);

        if (isset($validated['avatar']) && $previousAvatar !== $validated['avatar']) {
            $avatars->delete($previousAvatar);
        }

        return redirect()
            ->route('profile')
            ->with('success', 'Profile updated successfully.');
    }
}