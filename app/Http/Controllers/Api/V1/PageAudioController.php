<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ElevenLabsUsage;
use App\Models\Story;
use App\Services\MediaStorageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PageAudioController extends Controller
{
    public function __construct(
        private MediaStorageService $mediaStorage
    ) {}

    /**
     * Generate (or return the already-stored) narration audio for a story page.
     *
     * Narration is generated once and kept on the media disk, so replays cost
     * nothing and survive an app restart. That disk is not publicly served —
     * these are recordings of children reading their own stories, so the bytes
     * only ever leave through this endpoint, after the ownership check below.
     */
    public function generate(Story $story, int $pageNumber)
    {
        // Authorization: only the story owner can generate narration
        if ($story->user_id !== auth()->id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Find the page
        $page = $story->pages()->where('page_number', $pageNumber)->first();

        if (! $page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $path = MediaStorageService::audioPath($story->id, $pageNumber);

        // Idempotent: hand back the stored narration without calling ElevenLabs.
        // If the row says we have audio but the file is gone (a disk switched
        // out from under us, say), fall through and generate it again.
        if ($page->audio_url && $this->mediaStorage->mediaExists($path)) {
            return $this->audioResponse($this->mediaStorage->getMedia($path));
        }

        $text = trim((string) $page->content);

        if ($text === '') {
            return response()->json(['error' => 'Page has no text to narrate'], 422);
        }

        $apiKey = config('services.elevenlabs.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY is not configured'], 500);
        }

        // Enforce the per-user daily character cap before spending on ElevenLabs.
        $userId = (int) auth()->id();
        $textLength = strlen($text);

        if (ElevenLabsUsage::wouldExceedLimit($userId, $textLength)) {
            $currentUsage = ElevenLabsUsage::getTodayUsage($userId);
            $limit = ElevenLabsUsage::getDailyLimit($userId);

            Log::warning('User exceeded daily TTS limit', [
                'user_id' => $userId,
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'requested_chars' => $textLength,
            ]);

            return response()->json([
                'error' => 'Daily narration limit reached. Please try again tomorrow.',
                'limit_info' => [
                    'characters_used' => $currentUsage,
                    'daily_limit' => $limit,
                    'requested_characters' => $textLength,
                ],
            ], 429);
        }

        $voiceId = config('services.elevenlabs.default_voice_id');
        $modelId = config('services.elevenlabs.default_model');

        try {
            $response = Http::connectTimeout(10)
                ->timeout(config('services.elevenlabs.timeout'))
                ->withHeaders([
                    'xi-api-key' => $apiKey,
                    'Accept' => 'audio/mpeg',
                ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                    'text' => $text,
                    'model_id' => $modelId,
                ]);
        } catch (ConnectionException $e) {
            Log::error('Page narration connection error', [
                'story_id' => $story->id,
                'page_number' => $pageNumber,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Narration request failed'], 503);
        }

        if (! $response->successful()) {
            $logLevel = $response->status() === 429 ? 'warning' : 'error';

            Log::log($logLevel, 'Page narration request failed', [
                'story_id' => $story->id,
                'page_number' => $pageNumber,
                'status_code' => $response->status(),
                'rate_limited' => $response->status() === 429,
            ]);

            return response()->json([
                'error' => 'Narration request failed',
                'details' => $response->json(),
            ], $response->status());
        }

        $audio = $response->body();

        try {
            $this->mediaStorage->storeMediaBytes($audio, $path);
        } catch (\RuntimeException $e) {
            Log::error('Failed to store page narration: '.$e->getMessage(), [
                'story_id' => $story->id,
                'page_number' => $pageNumber,
            ]);

            return response()->json(['error' => 'Narration storage failed'], 503);
        }

        // The stored file has no public URL any more, so audio_url points back at
        // this endpoint — the one route that will hand the bytes over, and only
        // to the owner. It doubles as the "we already have narration" marker.
        $page->update(['audio_url' => self::audioUrl($story->id, $pageNumber)]);

        // Record the spend against the user's daily cap.
        ElevenLabsUsage::logTtsRequest(text: $text, voiceId: $voiceId, modelId: $modelId);

        Log::info('Page narration generated', [
            'story_id' => $story->id,
            'page_number' => $pageNumber,
            'character_count' => $textLength,
            'audio_size_bytes' => strlen($audio),
        ]);

        return $this->audioResponse($audio);
    }

    /**
     * The authenticated URL a client fetches a page's narration from.
     */
    public static function audioUrl(int $storyId, int $pageNumber): string
    {
        return route('stories.pages.audio', ['story' => $storyId, 'pageNumber' => $pageNumber]);
    }

    private function audioResponse(string $audio)
    {
        return response($audio, 200)->header('Content-Type', 'audio/mpeg');
    }
}
