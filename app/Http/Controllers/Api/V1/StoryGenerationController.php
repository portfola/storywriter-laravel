<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Story;
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
            'model' => 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
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
            'model' => 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
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
        // STEP 2: GENERATE COVER IMAGE (Using Flux.1 - Best quality)
        // ---------------------------------------------------------
        $imageUrl = null;

        // Create a simple image prompt based on the user's input
        $imagePrompt = "Children's book illustration, cover art, cute style: ".substr($validated['transcript'], 0, 200);

        try {
            $imageResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.together.xyz/v1/images/generations', [
                'model' => 'black-forest-labs/FLUX.1-schnell',
                'prompt' => $imagePrompt,
                'width' => 1024,
                'height' => 768,
                'steps' => 4, // Low steps = Fast (Schnell model is designed for this)
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

        // ---------------------------------------------------------
        // STEP 3: PARSE INTO STRUCTURED PAGES (before image injection)
        // ---------------------------------------------------------
        $parsed = $this->parseStoryText($storyText, $imageUrl);

        // ---------------------------------------------------------
        // STEP 4: SAVE TO DATABASE
        // ---------------------------------------------------------
        $storyEntry = null;
        try {
            // Inject the image at the top of the body for DB storage
            $bodyForDb = $imageUrl
                ? "![]( $imageUrl )\n\n".$storyText
                : $storyText;

            $storyEntry = Story::create([
                'user_id' => auth()->id() ?? 1,
                'name' => $parsed['title'],
                'slug' => Str::slug($parsed['title'] ?: 'story').'-'.Str::random(4),
                'body' => $bodyForDb,
                'prompt' => $validated['transcript'],
            ]);

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

    /**
     * Parse raw LLM story text into structured pages.
     *
     * The LLM returns text with "Page N" headers separated by "---PAGE BREAK---".
     * This method extracts the title, splits into pages, and attaches the cover
     * image to the first page.
     */
    private function parseStoryText(string $text, ?string $coverImageUrl): array
    {
        // Extract title from the first line
        $firstLine = strtok($text, "\n");
        $title = trim(str_replace(['Title:', '"', '#', '*'], '', $firstLine)) ?: 'New Story';

        // Remove any markdown image tags from the body before splitting
        $body = preg_replace('/!\[.*?\]\(\s*https?:\/\/[^)]+\s*\)/i', '', $text);

        // Remove the title line
        $body = preg_replace('/^.*\n/', '', $body, 1);
        $body = trim($body);

        // Split on ---PAGE BREAK--- separator
        $rawChunks = preg_split('/---PAGE BREAK---/i', $body);

        $pages = [];
        foreach ($rawChunks as $chunk) {
            $chunk = trim($chunk);
            if (strlen($chunk) < 20) {
                continue;
            }

            // Remove "Page N" headers and illustration prompts
            $clean = preg_replace('/^Page\s*\d+[:.]?\s*/im', '', $chunk);
            $clean = preg_replace('/Illustration[:.]?.+/i', '', $clean);
            $clean = trim($clean);

            if (! $clean) {
                continue;
            }

            $pageNum = count($pages) + 1;

            $pages[] = [
                'pageNumber' => $pageNum,
                'content' => $clean,
                'imageUrl' => ($pageNum === 1 && $coverImageUrl) ? $coverImageUrl : null,
            ];
        }

        // Fallback: if parsing produced no pages, use the whole body as one page
        if (empty($pages)) {
            $pages[] = [
                'pageNumber' => 1,
                'content' => $body,
                'imageUrl' => $coverImageUrl,
            ];
        }

        return [
            'title' => $title,
            'pages' => $pages,
        ];
    }
}
