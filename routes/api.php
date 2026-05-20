<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Heirloom\V1\NarrativeController;
use App\Http\Controllers\Api\V1\ElevenLabsController;
use App\Http\Controllers\Api\V1\PageImageController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\StoryGenerationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::get('/health', fn() => response()->json(['status' => 'ok']));
    Route::post('/login', [AuthController::class, 'login']);
    Route::prefix('auth')->group(function () {
        Route::post('/login', LoginController::class);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', fn(Request $request) => $request->user());
        Route::post('/heartbeat', [AuthController::class, 'heartbeat']);
        Route::post('/stories/generate', [StoryGenerationController::class, 'generate']);
        Route::post('/stories/{story:id}/pages/{pageNumber}/image', [PageImageController::class, 'generate']);

        Route::get('/stories/saved', [StoryController::class, 'saved']);
        Route::post('/stories/{story}/save', [StoryController::class, 'save']);
        Route::delete('/stories/{story}/unsave', [StoryController::class, 'unsave']);
        Route::apiResource('/stories', StoryController::class);

        Route::prefix('conversation')->group(function () {
            Route::post('/sdk-credentials', [ElevenLabsController::class, 'sdkCredentials']);
            Route::post('/proxy', [ElevenLabsController::class, 'conversationProxy']);
            Route::post('/tts', [ElevenLabsController::class, 'textToSpeech']);
            Route::get('/voices', [ElevenLabsController::class, 'voices']);
        });
    });
});

// Heirloom routes (Tim's branch)
Route::prefix('heirloom/v1')
    ->name('heirloom.v1.')
    ->middleware('auth:sanctum')
    ->group(base_path('routes/heirloom_v1.php'));

Route::get('/heirloom/share/{token}', [NarrativeController::class, 'showByToken'])
    ->name('narratives.share');
