<?php

use App\Http\Controllers\BookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($user = $request->user()) {
        return redirect($user->is_admin ? '/bookings' : '/book-a-service');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('book-a-service', [BookingController::class, 'index'])->name('book-a-service');
    Route::post('book-a-service', [BookingController::class, 'store'])->name('book-a-service.store');
    Route::get('history', [BookingController::class, 'history'])->name('history');
    Route::get('bookings', [BookingController::class, 'adminIndex'])->name('bookings');
    Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
    Route::get('bookings/{booking}/design-picture', [BookingController::class, 'designPicture'])->name('bookings.design-picture');
    Route::get('bookings/{booking}/design-picture/download', [BookingController::class, 'downloadDesignPicture'])->name('bookings.design-picture.download');
    Route::inertia('schedules', 'Schedules')->name('schedules');
});

require __DIR__.'/settings.php';
