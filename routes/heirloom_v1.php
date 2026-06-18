<?php

use App\Http\Controllers\Api\Heirloom\V1\DashboardController;
use App\Http\Controllers\Api\Heirloom\V1\SubjectController;
use App\Http\Controllers\Api\Heirloom\V1\SessionController;
use App\Http\Controllers\Api\Heirloom\V1\TranscriptionController;
use App\Http\Controllers\Api\Heirloom\V1\NarrativeController;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::apiResource('/subjects', SubjectController::class);

Route::apiResource('/sessions', SessionController::class);

Route::post('/sessions/{session}/transcribe', [TranscriptionController::class, 'store'])->name('sessions.transcribe');

Route::get('/sessions/{session}/transcript', [TranscriptionController::class, 'show'])
    ->name('sessions.transcript.show');

Route::post('/sessions/{session}/transcript', [TranscriptionController::class, 'storeManual'])
    ->name('sessions.transcript.manual');

Route::post('/subjects/{subject}/narratives', [NarrativeController::class, 'store'])
    ->name('subjects.narratives.store');
    
Route::get('/narratives/{narrative}', [NarrativeController::class, 'show'])
    ->name('narratives.show');