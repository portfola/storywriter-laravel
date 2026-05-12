<?php

use App\Http\Controllers\Api\Heirloom\V1\DashboardController;
use App\Http\Controllers\Heirloom\SessionController;
use App\Http\Controllers\Heirloom\TranscriptController;




Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
Route::get('/transcripts', [TranscriptController::class, 'index'])->name('transcripts.index');
Route::get('/transcripts/{transcript}', [TranscriptController::class, 'show'])->name('transcripts.show');