<?php

use App\Http\Controllers\AlbumController;
use App\Http\Controllers\AirportSearchController;
use App\Http\Controllers\CitySearchController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\FlightHistoryController;
use App\Http\Controllers\GoogleMapListController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\PassportController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PhotoFileController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\VisitController;
use App\Http\Middleware\GuardAuthEndpoints;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::middleware('guest')->group(function () {
    // LOGIN
    Route::get('/login', fn () => Inertia::render('Auth/Login', [
        'status' => session('status'),
        'canRegister' => (bool) config('auth.registration_enabled'),
    ]))->name('login');

    // REGISTER (desactivado fuera de local: ver auth.registration_enabled)
    Route::get('/register', fn () => Inertia::render('Auth/Register'))
        ->middleware(GuardAuthEndpoints::class)
        ->name('register');

    // PASSWORD RESET (los POST los registra Fortify)
    Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword', [
        'status' => session('status'),
    ]))->name('password.request');

    Route::get('/reset-password/{token}', fn (Request $request, string $token) => Inertia::render('Auth/ResetPassword', [
        'token' => $token,
        'email' => (string) $request->query('email', ''),
    ]))->name('password.reset');
});


Route::get('/', DashboardController::class)
    ->middleware('auth')
    ->name('home');


Route::middleware('auth')->group(function () {
    
//TRIPS
    Route::post('/trips', [TripController::class, 'store'])
    ->name('trips.store');

    Route::get('/trips', [TripController::class, 'index'])
    ->name('trips.index');
    
    Route::get('/trips/create', fn () => Inertia::render('Trips/Create'))
    ->name('trips.create');    

    Route::get('/trips/{trip}/edit', [TripController::class, 'edit'])
    ->name('trips.edit');

    Route::put('/trips/{trip}', [TripController::class, 'update'])
    ->name('trips.update');

    Route::delete('/trips/{trip}', [TripController::class, 'destroy'])
    ->name('trips.destroy');

    Route::get('/trips/{trip}', [TripController::class, 'show'])
    ->name('trips.show');

    Route::get('/trips/{trip}/photos', [TripController::class, 'photos'])
    ->name('trips.photos');
        
    Route::scopeBindings()->group(function () {
        Route::post('/trips/{trip}/visits', [VisitController::class, 'store'])
            ->name('trips.visits.store');

        Route::put('/trips/{trip}/visits/{visit}', [VisitController::class, 'update'])
            ->name('trips.visits.update');

        Route::delete('/trips/{trip}/visits/{visit}', [VisitController::class, 'destroy'])
            ->name('trips.visits.destroy');
    });

    Route::scopeBindings()->group(function () {
        Route::post('/trips/{trip}/flights', [FlightController::class, 'store'])
            ->name('trips.flights.store');

        Route::put('/trips/{trip}/flights/{flight}', [FlightController::class, 'update'])
            ->name('trips.flights.update');

        Route::delete('/trips/{trip}/flights/{flight}', [FlightController::class, 'destroy'])
            ->name('trips.flights.destroy');
    });

    Route::post('/trips/{trip}/google-maps', [GoogleMapListController::class, 'store'])
    ->name('trips.google-maps.store');

    Route::post('/trips/{trip}/google-maps/{googleMapList}', [GoogleMapListController::class, 'attach'])
        ->name('trips.google-maps.attach');

    Route::delete('/trips/{trip}/google-maps/{googleMapList}', [GoogleMapListController::class, 'detach'])
        ->name('trips.google-maps.detach');

//PHOTOS

    Route::post('/journal-entries', [JournalEntryController::class, 'store'])
    ->name('journal-entries.store');

    // Búsquedas y alta de ciudades: limitadas para evitar abusos y proteger
    // la cuota de Geoapify.
    Route::get('/cities/search', CitySearchController::class)
    ->middleware('throttle:60,1')
    ->name('cities.search');

    Route::get('/airports/search', AirportSearchController::class)
    ->middleware('throttle:60,1')
    ->name('airports.search');

    Route::post('/cities', [CityController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('cities.store');

    Route::get('/passport', PassportController::class)
    ->name('passport');

    Route::get('/profile', ProfileController::class)->name('profile');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/flight-history', FlightHistoryController::class)
    ->name('flight-history');

    Route::get('/photos', [PhotoController::class, 'index'])->name('photos.index');

    // Cada subida ocupa disco y un trabajo de la cola.
    Route::post('/photos', [PhotoController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('photos.store');

    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])
    ->name('photos.destroy');

    // Archivos de las fotos (disco privado): solo para su propietario.
    Route::get('/photos/{photo}/{variant}', PhotoFileController::class)
    ->whereNumber('photo')
    ->whereIn('variant', Photo::VARIANTS)
    ->name('photos.file');
    
    Route::post('/albums', [AlbumController::class, 'store'])
    ->name('albums.store');

    Route::put('/albums/{album}', [AlbumController::class, 'update'])
        ->name('albums.update');

    Route::delete('/albums/{album}', [AlbumController::class, 'destroy'])
        ->name('albums.destroy');

    Route::post('/albums/{album}/photos', [AlbumController::class, 'addPhotos'])
        ->name('albums.photos.store');

    Route::delete('/albums/{album}/photos/{photo}', [AlbumController::class, 'removePhoto'])
        ->name('albums.photos.destroy');

    Route::get('/countries', [CountryController::class, 'index'])
    ->name('countries.index');

    Route::get('/countries/{country}', [CountryController::class, 'show'])
        ->name('countries.show');
    
});


