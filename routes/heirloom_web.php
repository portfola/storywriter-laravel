<?php

use App\Http\Controllers\Api\Heirloom\V1\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');