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

Route::post('/sessions/{session}/transcript', [TranscriptionController::class, 'storeManual'])
    ->name('sessions.transcript.manual');

Route::post('/transcripts/{transcript}/narratives', [NarrativeController::class, 'store'])
    ->name('transcripts.narratives.store');
    
Route::get('/narratives/{narrative}', [NarrativeController::class, 'show'])
    ->name('narratives.show');