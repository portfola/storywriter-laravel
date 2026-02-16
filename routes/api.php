<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\V1\ElevenLabsController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\StoryGenerationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route to login to API
Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class);
});

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (The "Sealed Off" Area)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Add your app data routes here.
    Route::post('stories/generate', [StoryGenerationController::class, 'generate']);
    Route::post('/heartbeat', [AuthController::class, 'heartbeat']);

    Route::post('/generate-story', [StoryController::class, 'generate'])->middleware('log.story');
});

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('/stories', StoryController::class);
});

// ElevenLabs conversation endpoints - require authentication
Route::prefix('conversation')->middleware('auth:sanctum')->group(function () {
    Route::post('/sdk-credentials', [ElevenLabsController::class, 'sdkCredentials']); // Deprecated
    Route::post('/proxy', [ElevenLabsController::class, 'conversationProxy']);
    Route::post('/tts', [ElevenLabsController::class, 'textToSpeech']);
    Route::get('/voices', [ElevenLabsController::class, 'voices']);
});
