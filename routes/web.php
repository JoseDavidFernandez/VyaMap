<?php

use App\Http\Controllers\CitySearchController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\VisitController;
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

// LOGIN
Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

//REGISTER
Route::get('/register', function () {
    return Inertia::render('Auth/Register');
})->name('register');


Route::get('/', DashboardController::class)
    ->middleware('auth')
    ->name('home');


Route::middleware('auth')->group(function () {
    Route::post('/trips', [TripController::class, 'store'])
        ->name('trips.store');

    Route::get('/trips/create', fn () => Inertia::render('Trips/Create'))
    ->name('trips.create');    

    Route::get('/trips/{trip}', [TripController::class, 'show'])
    ->name('trips.show');
        
    Route::scopeBindings()->group(function () {
        Route::post('/trips/{trip}/visits', [VisitController::class, 'store'])
            ->name('trips.visits.store');

        Route::put('/trips/{trip}/visits/{visit}', [VisitController::class, 'update'])
            ->name('trips.visits.update');
    });

    Route::post('/flights', [FlightController::class, 'store'])
        ->name('flights.store');

    Route::post('/photos', [PhotoController::class, 'store'])
        ->name('photos.store');

    Route::post('/journal-entries', [JournalEntryController::class, 'store'])
    ->name('journal-entries.store');

    Route::get('/cities/search', CitySearchController::class)
    ->name('cities.search');

    Route::post('/cities', [CityController::class, 'store'])
    ->name('cities.store');
});


