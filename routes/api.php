<?php
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\V1\ElevenLabsController;
use App\Http\Controllers\Api\V1\StoryGenerationController;
use App\Http\Controllers\Api\V1\StoryController; 
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
    
    Route::post('/heartbeat', [AuthController::class, 'heartbeat']);
    
    // Add your app data routes here. WHAT DOES THIS MEAN??
});


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function() {
    Route::apiResource('/stories', StoryController::class );
});

Route::prefix('conversation')->group(function () {
    Route::post('/sdk-credentials', [ElevenLabsController::class, 'sdkCredentials']);
    Route::post('/tts', [ElevenLabsController::class, 'textToSpeech']);
    Route::get('/voices', [ElevenLabsController::class, 'voices']);
});

Route::post('/stories/generate', [StoryGenerationController::class, 'generate']);