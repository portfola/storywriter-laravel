<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\TogetherAiUsage;
use App\Services\MediaStorageService;
use App\Services\PromptBuilder;
use App\Support\Analytics;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StoryGenerationController extends Controller
{
    public function __construct(
        private PromptBuilder $promptBuilder,
        private MediaStorageService $mediaStorage
    ) {}

    /**
     * Generate a story AND images using Google Flash Image 2.5
     */
    public function generate(Request $request)
    {
        $startTime = microtime(true);

        // Log everything being sent
        \Log::info('=== INCOMING REQUEST ===');
        \Log::info('Headers:', $request->headers->all());
        \Log::info('All Input:', $request->all());

        set_time_limit(120); // Allow script to run for 2 minutes

        $validated = $request->validate([
            'transcript' => 'required|string',
            'options' => 'nullable|array',
        ]);

        $userId = (string) (auth()->id() ?? 1);

        // Enforce per-user daily story-generation cap before spending on Together AI.
        if (TogetherAiUsage::wouldExceedLimit((int) $userId, TogetherAiUsage::SERVICE_STORY)) {
            $limit = TogetherAiUsage::getDailyLimit(TogetherAiUsage::SERVICE_STORY);

            \Log::warning('User exceeded daily story generation limit', [
                'user_id' => $userId,
                'limit' => $limit,
            ]);

            Analytics::capture($userId, 'story_generation_failed', [
                'error_type' => 'daily_limit_reached',
                'limit' => $limit,
            ]);

            return response()->json([
                'error' => 'Daily story limit reached. Please try again tomorrow.',
                'limit_info' => [
                    'stories_used' => TogetherAiUsage::getTodayCount((int) $userId, TogetherAiUsage::SERVICE_STORY),
                    'daily_limit' => $limit,
                ],
            ], 429);
        }

        Analytics::capture($userId, 'story_generation_requested', [
            'transcript_length' => strlen($validated['transcript']),
            'transcript_word_count' => str_word_count($validated['transcript']),
            'user_turns' => substr_count(strtolower($validated['transcript']), 'user:'),
        ]);

        // Build the prompt
        $prompt = $this->promptBuilder->buildStoryPrompt($validated['transcript']);

        \Log::info($prompt);

        $apiKey = config('services.together.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'TOGETHER_API_KEY is not configured'], 500);
        }

        $options = $validated['options'] ?? [];
        $maxTokens = $options['maxTokens'] ?? 2000;
        $temperature = $options['temperature'] ?? 0.7;

        \Log::info('About to call Together AI', [
            'model' => config('services.together.text_model'),
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]);

        // ---------------------------------------------------------
        // STEP 1: GENERATE TEXT (Using Llama 3 - Reliable & Fast)
        // ---------------------------------------------------------
        $textCallStart = microtime(true);

        try {
            $textResponse = Http::connectTimeout(10)
                ->timeout(90)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.together.xyz/v1/chat/completions', [
                    'model' => config('services.together.text_model'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $prompt['system'],
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt['user'],
                        ],
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);
        } catch (ConnectionException $e) {
            $elapsedMs = round((microtime(true) - $textCallStart) * 1000);

            \Log::error('Together AI timeout', [
                'model' => config('services.together.text_model'),
                'elapsed_ms' => $elapsedMs,
                'message' => $e->getMessage(),
            ]);

            Analytics::capture($userId, 'story_generation_failed', [
                'error_type' => 'timeout',
                'elapsed_ms' => $elapsedMs,
            ]);

            return response()->json(['error' => 'Story text generation timed out'], 504);
        }

        \Log::info('Together AI Response Received', [
            'status' => $textResponse->status(),
            'successful' => $textResponse->successful(),
            'elapsed_ms' => round((microtime(true) - $textCallStart) * 1000),
        ]);

        if (! $textResponse->successful()) {
            \Log::error('Text Generation Failed', [
                'error_type' => 'http_error',
                'status' => $textResponse->status(),
                'body' => $textResponse->json(),
            ]);

            Analytics::capture($userId, 'story_generation_failed', [
                'error_type' => 'text_generation',
                'http_status' => $textResponse->status(),
                'generation_time_ms' => round((microtime(true) - $startTime) * 1000),
            ]);

            return response()->json(['error' => 'Story text generation failed'], 503);
        }

        $storyText = $textResponse->json()['choices'][0]['message']['content'] ?? '';

        // Record successful story generation against the user's daily cap.
        TogetherAiUsage::logUsage((int) $userId, TogetherAiUsage::SERVICE_STORY, config('services.together.text_model'));

        \Log::info('Story generated successfully', [
            'length' => strlen($storyText),
        ]);
        // ---------------------------------------------------------
        // STEP 2: PARSE INTO STRUCTURED PAGES
        // ---------------------------------------------------------
        $parsed = $this->promptBuilder->parseStoryOutput($storyText);

        // ---------------------------------------------------------
        // STEP 3: GENERATE PAGE 1 IMAGE (Using Flux.1 - Best quality)
        // ---------------------------------------------------------
        $imageUrl = null;

        $imagePrompt = $this->promptBuilder->buildImagePrompt(
            $parsed['characters'],
            $parsed['pages'][0]['illustrationPrompt']
        );

        // The cover image is optional, so a reached image cap simply skips it
        // rather than failing the whole story.
        if (TogetherAiUsage::wouldExceedLimit((int) $userId, TogetherAiUsage::SERVICE_IMAGE)) {
            \Log::warning('Skipping cover image — user reached daily image limit', [
                'user_id' => $userId,
                'limit' => TogetherAiUsage::getDailyLimit(TogetherAiUsage::SERVICE_IMAGE),
            ]);
        } else {
            try {
                $imageResponse = Http::connectTimeout(10)
                    ->timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$apiKey,
                        'Content-Type' => 'application/json',
                    ])->post('https://api.together.xyz/v1/images/generations', [
                        'model' => config('services.together.image_model'),
                        'prompt' => $imagePrompt,
                        'width' => config('services.together.image_width'),
                        'height' => config('services.together.image_height'),
                        'steps' => config('services.together.image_steps'),
                        'n' => 1,
                    ]);

                if ($imageResponse->successful()) {
                    $imageUrl = $imageResponse->json()['data'][0]['url'] ?? null;
                    TogetherAiUsage::logUsage((int) $userId, TogetherAiUsage::SERVICE_IMAGE, config('services.together.image_model'));
                } else {
                    \Log::error('Image Generation Failed', ['body' => $imageResponse->json()]);
                }

            } catch (\Exception $e) {
                \Log::error('Image Generation Exception: '.$e->getMessage());
                // We don't stop the story if the image fails, we just continue without it.
            }
        }

        // ---------------------------------------------------------
        // STEP 4: SAVE TO DATABASE
        // ---------------------------------------------------------
        $storyEntry = null;
        try {
            // The story is created before the cover image is stored, because its
            // id is part of the storage path. The body picks the image up in the
            // update below, once we know where our copy lives.
            $storyEntry = Story::create([
                'user_id' => auth()->id() ?? 1,
                'name' => $parsed['title'],
                'slug' => Str::slug($parsed['title'] ?: 'story').'-'.Str::random(4),
                'body' => $storyText,
                'prompt' => $validated['transcript'],
                'characters_description' => $parsed['characters'],
            ]);

            // Together's URLs expire after a few hours, so keep our own copy and
            // persist that instead — otherwise saved storybooks go blank later.
            if ($imageUrl) {
                try {
                    $imageUrl = $this->mediaStorage->storeFromUrl(
                        $imageUrl,
                        MediaStorageService::imagePath($storyEntry->id, 1)
                    );
                } catch (\RuntimeException $e) {
                    \Log::error('Failed to store generated cover image: '.$e->getMessage(), [
                        'story_id' => $storyEntry->id,
                    ]);

                    // The cover is optional, so the story still goes out — but
                    // without a URL that is going to die in a few hours.
                    $imageUrl = null;
                }
            }

            // Inject the image at the top of the body for DB storage (backward compat)
            if ($imageUrl) {
                $storyEntry->update(['body' => "![]( $imageUrl )\n\n".$storyText]);
            }

            // Create StoryPage records for each page
            foreach ($parsed['pages'] as $index => $page) {
                StoryPage::create([
                    'story_id' => $storyEntry->id,
                    'page_number' => $index + 1,
                    'content' => $page['content'],
                    'illustration_prompt' => $page['illustrationPrompt'],
                    'image_url' => ($index === 0 && $imageUrl) ? $imageUrl : null,
                ]);
            }

        } catch (\Throwable $e) {
            \Log::error('DB SAVE ERROR: '.$e->getMessage());
        }

        // Map parsed pages to include pageNumber and imageUrl for response. This
        // runs after the save so the response hands back the stored image URL.
        $parsed['pages'] = array_map(function ($page, $index) use ($imageUrl) {
            return [
                'pageNumber' => $index + 1,
                'content' => $page['content'],
                'illustrationPrompt' => $page['illustrationPrompt'],
                'imageUrl' => ($index === 0 && $imageUrl) ? $imageUrl : null,
            ];
        }, $parsed['pages'], array_keys($parsed['pages']));

        Analytics::capture($userId, 'story_generation_completed', [
            'generation_time_ms' => round((microtime(true) - $startTime) * 1000),
            'story_length' => strlen($storyText),
            'has_cover_image' => $imageUrl !== null,
        ]);

        return response()->json([
            'data' => [
                'title' => $parsed['title'],
                'pages' => $parsed['pages'],
                'cover_image' => $imageUrl,
                'story_id' => $storyEntry?->id,
                'page_count' => count($parsed['pages']),
            ],
        ]);
    }
}
