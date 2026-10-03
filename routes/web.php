<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\PromoController;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if ($user = $request->user()) {
        return redirect($user->is_admin ? '/bookings' : '/book-a-service');
    }

    return Inertia::render('Welcome', [
        'promos' => Promo::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'title', 'description', 'discount', 'requirements', 'usage', 'image']),
    ]);
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
    Route::get('schedules', [BookingController::class, 'schedules'])->name('schedules');
    Route::get('promos', [PromoController::class, 'index'])->name('promos');
    Route::post('promos', [PromoController::class, 'store'])->name('promos.store');
    Route::post('promos/{promo}', [PromoController::class, 'revise'])->name('promos.revise');
    Route::patch('promos/{promo}', [PromoController::class, 'update'])->name('promos.update');
    Route::delete('promos/{promo}', [PromoController::class, 'destroy'])->name('promos.destroy');
});

require __DIR__.'/settings.php';
