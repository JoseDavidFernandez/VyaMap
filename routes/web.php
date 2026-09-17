<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\TripController;
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


Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::post('/trips', [TripController::class, 'store'])
        ->name('trips.store');
        
    Route::post('/visits', [VisitController::class, 'store'])
        ->name('visits.store');

    Route::post('/flights', [FlightController::class, 'store'])
        ->name('flights.store');

    Route::post('/photos', [PhotoController::class, 'store'])
        ->name('photos.store');

    Route::post('/journal-entries', [JournalEntryController::class, 'store'])
    ->name('journal-entries.store');
});


