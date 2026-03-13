<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryPage;
use App\Services\PromptBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PostHog\PostHog;

class StoryGenerationController extends Controller
{
    public function __construct(
        private PromptBuilder $promptBuilder
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

        if (config('services.posthog.api_key')) {
            PostHog::capture([
                'distinctId' => $userId,
                'event' => 'story_generation_requested',
                'properties' => [
                    'transcript_length' => strlen($validated['transcript']),
                    'transcript_word_count' => str_word_count($validated['transcript']),
                    'user_turns' => substr_count(strtolower($validated['transcript']), 'user:'),
                ],
            ]);
        }

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
        $textResponse = Http::withHeaders([
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

        \Log::info('Together AI Response Received', [
            'status' => $textResponse->status(),
            'successful' => $textResponse->successful(),
        ]);

        if (! $textResponse->successful()) {
            \Log::error('Text Generation Failed', ['body' => $textResponse->json()]);

            if (config('services.posthog.api_key')) {
                PostHog::capture([
                    'distinctId' => $userId,
                    'event' => 'story_generation_failed',
                    'properties' => [
                        'error_type' => 'text_generation',
                        'http_status' => $textResponse->status(),
                        'generation_time_ms' => round((microtime(true) - $startTime) * 1000),
                    ],
                ]);
            }

            return response()->json(['error' => 'Story text generation failed'], 503);
        }

        $storyText = $textResponse->json()['choices'][0]['message']['content'] ?? '';

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

        try {
            $imageResponse = Http::withHeaders([
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
            } else {
                \Log::error('Image Generation Failed', ['body' => $imageResponse->json()]);
            }

        } catch (\Exception $e) {
            \Log::error('Image Generation Exception: '.$e->getMessage());
            // We don't stop the story if the image fails, we just continue without it.
        }

        // Map parsed pages to include pageNumber and imageUrl for response
        $parsed['pages'] = array_map(function ($page, $index) use ($imageUrl) {
            return [
                'pageNumber' => $index + 1,
                'content' => $page['content'],
                'illustrationPrompt' => $page['illustrationPrompt'],
                'imageUrl' => ($index === 0 && $imageUrl) ? $imageUrl : null,
            ];
        }, $parsed['pages'], array_keys($parsed['pages']));

        // ---------------------------------------------------------
        // STEP 4: SAVE TO DATABASE
        // ---------------------------------------------------------
        $storyEntry = null;
        try {
            // Inject the image at the top of the body for DB storage (backward compat)
            $bodyForDb = $imageUrl
                ? "![]( $imageUrl )\n\n".$storyText
                : $storyText;

            $storyEntry = Story::create([
                'user_id' => auth()->id() ?? 1,
                'name' => $parsed['title'],
                'slug' => Str::slug($parsed['title'] ?: 'story').'-'.Str::random(4),
                'body' => $bodyForDb,
                'prompt' => $validated['transcript'],
                'characters_description' => $parsed['characters'],
            ]);

            // Create StoryPage records for each page
            foreach ($parsed['pages'] as $index => $page) {
                StoryPage::create([
                    'story_id' => $storyEntry->id,
                    'page_number' => $page['pageNumber'],
                    'content' => $page['content'],
                    'illustration_prompt' => $page['illustrationPrompt'],
                    'image_url' => ($index === 0 && $imageUrl) ? $imageUrl : null,
                ]);
            }

        } catch (\Throwable $e) {
            \Log::error('DB SAVE ERROR: '.$e->getMessage());
        }

        if (config('services.posthog.api_key')) {
            PostHog::capture([
                'distinctId' => $userId,
                'event' => 'story_generation_completed',
                'properties' => [
                    'generation_time_ms' => round((microtime(true) - $startTime) * 1000),
                    'story_length' => strlen($storyText),
                    'has_cover_image' => $imageUrl !== null,
                ],
            ]);
        }

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
