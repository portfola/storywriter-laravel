<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\PromptBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

        // Log everything being sent
        \Log::info('=== INCOMING REQUEST ===');
        \Log::info('Headers:', $request->headers->all());
        \Log::info('All Input:', $request->all());

        set_time_limit(120); // Allow script to run for 2 minutes

        $validated = $request->validate([
            'transcript' => 'required|string',
            'options' => 'nullable|array',
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
                    'content' => $validated['transcript'],
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
        // STEP 3: MERGE & SAVE
        // ---------------------------------------------------------

        // --- DATABASE SAVE ---
        try {
            // Extract title BEFORE adding the image to avoid parsing the image URL as the title
            $title = strtok($storyText, "\n");
            $title = str_replace(['Title:', '"', '#', '*', '![]', '(', ')'], '', $title);

            // Inject the image at the top of the story text if we got one
            if ($imageUrl) {
                $storyText = "![]( $imageUrl )\n\n".$storyText;
            }

            $storyEntry = Story::create([
                'user_id' => auth()->id() ?? 1,
                'name' => trim($title) ?: 'New Story',
                'slug' => Str::slug(trim($title) ?: 'story').'-'.Str::random(4),
                'body' => $storyText,
                'prompt' => $validated['transcript'],
            ]);

        } catch (\Throwable $e) {
            \Log::error('DB SAVE ERROR: '.$e->getMessage());
        }

        return response()->json([
            'data' => [
                'story' => $storyText,
            ],
        ]);
    }
}
