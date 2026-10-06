<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\Trip;
use App\Models\Visit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function __invoke(): Response
    {
        $user = request()->user();

        $trips = Trip::query()
            ->where('user_id', $user->id)
            ->with([
                'visits.city.country',
                'photos' => function ($query) {
                    $query
                        ->where('processing_status', 'ready')
                        ->orderBy('id');
                },
            ])
            ->orderByDesc('start_date')
            ->get();

        $visits = Visit::query()
            ->whereHas('trip', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with('city.country')
            ->get();

        $flightsCount = Flight::query()
            ->where('user_id', $user->id)
            ->count();

        $visitedCities = $visits
            ->unique('city_id')
            ->values();

        $countries = $visits
            ->filter(fn ($visit) => $visit->city?->country)
            ->map(fn ($visit) => [
                'name' => $visit->city->country->name,
                'iso_code' => $visit->city->country->iso_code,
            ])
            ->unique('iso_code')
            ->values();

        return Inertia::render('Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],

            'stats' => [
                'countries' => $countries->count(),
                'cities' => $visitedCities->count(),
                'trips' => $trips->count(),
                'flights' => $flightsCount,
            ],

            'countries' => $countries,

            'trips' => $trips->map(function ($trip) {
                $firstCountry = $trip->visits
                    ->filter(fn ($visit) => $visit->city?->country)
                    ->first();

                return [
                    'id' => $trip->id,
                    'name' => $trip->name,
                    'location' => $firstCountry?->city?->country?->name ?? 'Trip',
                    'year' => $trip->start_date?->format('Y'),
                    'cover' => $trip->photos->first()?->thumbnail_path
                        ?? $trip->photos->first()?->path,
                ];
            })->values(),
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

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        $user->update($validated);

        return redirect()
            ->route('profile')
            ->with('success', 'Profile updated successfully.');
    }
}