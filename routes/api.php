<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Heirloom\V1\NarrativeController;
use App\Http\Controllers\Api\V1\ElevenLabsController;
use App\Http\Controllers\Api\V1\PageAudioController;
use App\Http\Controllers\Api\V1\PageImageController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\StoryGenerationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::get('/health', fn () => response()->json(['status' => 'ok']));
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        // Credentialed login (Hash::check), shared by the StoryWriter app and
        // Heirloom's login page.
        Route::post('/login', LoginController::class);
        Route::post('/register', RegisterController::class);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', fn (Request $request) => $request->user());
        Route::post('/heartbeat', [AuthController::class, 'heartbeat']);
        Route::post('/stories/generate', [StoryGenerationController::class, 'generate'])
            ->middleware('throttle:ai-generation');
        Route::post('/stories/{story:id}/pages/{pageNumber}/image', [PageImageController::class, 'generate'])
            ->middleware('throttle:ai-generation');
        Route::post('/stories/{story:id}/pages/{pageNumber}/audio', [PageAudioController::class, 'generate'])
            ->middleware('throttle:ai-generation');

        Route::get('/stories/saved', [StoryController::class, 'saved']);
        Route::post('/stories/{story}/save', [StoryController::class, 'save']);
        Route::delete('/stories/{story}/unsave', [StoryController::class, 'unsave']);
        Route::apiResource('/stories', StoryController::class);

        Route::prefix('conversation')->group(function () {
            Route::post('/sdk-credentials', [ElevenLabsController::class, 'sdkCredentials'])
                ->middleware('throttle:conversation-credentials');
            Route::post('/proxy', [ElevenLabsController::class, 'conversationProxy'])
                ->middleware('throttle:conversation');
            Route::post('/tts', [ElevenLabsController::class, 'textToSpeech'])
                ->middleware('throttle:conversation');
            Route::get('/voices', [ElevenLabsController::class, 'voices'])
                ->middleware('throttle:conversation');
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
