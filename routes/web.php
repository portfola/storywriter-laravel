<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// use this after testing:
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->name('dashboard.analytics');
    Route::get('/dashboard/stories/{story}', [DashboardController::class, 'show'])->name('dashboard.stories.show');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.analytics');

// Route::get('/dashboard/stories/{story}', [DashboardController::class, 'show'])
//     ->middleware(['auth', 'verified'])
//     ->name('dashboard.stories.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    //    Route::get('/stories/{story:slug}', [DashboardController::class, 'show'])->name('dashboard.story');
    Route::get('/stories/{story}', [DashboardController::class, 'show'])->name('dashboard.story');
});

require __DIR__.'/auth.php';
