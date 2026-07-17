<?php

// use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\V1\ElevenLabsController;
use App\Http\Controllers\Api\V1\PageImageController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\StoryGenerationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Heirloom\V1\NarrativeController;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
| Single source of truth for login: LoginController checks the password
| properly (Hash::check) and issues a Sanctum token.
|
| AuthController@login was a duplicate, password-less login endpoint
| (firstOrCreate + token, no credential check) — deleted 2026-07.
| Its import is commented out but not yet fully removed from this file;
| see /heartbeat below, which still references it.
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class);
});


// Dead route — AuthController@login removed. Kept commented as a marker
// Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Storywriter) — require a valid Sanctum token
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Primary story-generation endpoint.
    Route::post('stories/generate', [StoryGenerationController::class, 'generate']);

    // TODO: this still calls AuthController::heartbeat, but the AuthController
    // import above is commented out. This route will error if hit until
    // AuthController is either restored (just for heartbeat) or this route
    // is deleted along with the rest of AuthController.
    Route::post('/heartbeat', [AuthController::class, 'heartbeat']);

    // TODO: confirm whether this is still used by the frontend, or a legacy
    // duplicate of stories/generate above (different controller — StoryController,
    // not StoryGenerationController). Two similarly-named endpoints here caused
    // confusion once already; worth consolidating or removing whichever is unused.
    Route::post('/generate-story', [StoryController::class, 'generate'])->middleware('log.story');


    Route::post('stories/{story:id}/pages/{pageNumber}/image', [PageImageController::class, 'generate']);
});


/*
|--------------------------------------------------------------------------
| Storywriter v1 — NOTE: only wraps /stories here, not the whole file.
|--------------------------------------------------------------------------
| This is our local convention. Rindy's main branch wraps ALL Storywriter
| routes in Route::prefix('v1'), which is inconsistent with this file.
| Reconciling these two conventions is an open item before the next merge —
| don't just copy main's api.php over this one without checking Heirloom
| routes still work (they use a separate, unrelated 'heirloom/v1' prefix).
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('/stories', StoryController::class);
});

/*
|--------------------------------------------------------------------------
| ElevenLabs conversation endpoints
|--------------------------------------------------------------------------
*/
Route::prefix('conversation')->middleware('auth:sanctum')->group(function () {
    Route::post('/sdk-credentials', [ElevenLabsController::class, 'sdkCredentials']);
    Route::post('/proxy', [ElevenLabsController::class, 'conversationProxy']);
    Route::post('/tts', [ElevenLabsController::class, 'textToSpeech']);
    Route::get('/voices', [ElevenLabsController::class, 'voices']);
});


/*
|--------------------------------------------------------------------------
| Heirloom (separate product, same backend)
|--------------------------------------------------------------------------
| Own prefix, own route file, own auth middleware. Not nested under
| Storywriter's 'v1' — this is intentional and unrelated to the
| Storywriter v1 inconsistency noted above.
*/
Route::prefix('heirloom/v1')
    ->name('heirloom.v1.')
    ->middleware('auth:sanctum')
    ->group(base_path('routes/heirloom_v1.php'));

Route::get('/heirloom/share/{token}', [NarrativeController::class, 'showByToken'])
    ->name('narratives.share');