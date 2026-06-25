<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\TogetherAiUsage;
use App\Services\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PageImageController extends Controller
{
    public function __construct(
        private PromptBuilder $promptBuilder
    ) {}

    /**
     * Generate an image for a specific story page on demand.
     */
    public function generate(Story $story, int $pageNumber)
    {
        // Authorization: only the story owner can generate images
        if ($story->user_id !== auth()->id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Find the page
        $page = $story->pages()->where('page_number', $pageNumber)->first();

        if (! $page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        // Idempotent: return existing image if already generated
        if ($page->image_url) {
            return response()->json([
                'data' => ['imageUrl' => $page->image_url],
            ]);
        }

        $apiKey = config('services.together.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'TOGETHER_API_KEY is not configured'], 500);
        }

        // Enforce per-user daily image-generation cap before spending on Together AI.
        $userId = (int) auth()->id();
        if (TogetherAiUsage::wouldExceedLimit($userId, TogetherAiUsage::SERVICE_IMAGE)) {
            $limit = TogetherAiUsage::getDailyLimit(TogetherAiUsage::SERVICE_IMAGE);

            Log::warning('User exceeded daily image generation limit', [
                'user_id' => $userId,
                'limit' => $limit,
            ]);

            return response()->json([
                'error' => 'Daily image limit reached. Please try again tomorrow.',
                'limit_info' => [
                    'images_used' => TogetherAiUsage::getTodayCount($userId, TogetherAiUsage::SERVICE_IMAGE),
                    'daily_limit' => $limit,
                ],
            ], 429);
        }

        // Build image prompt from character descriptions + illustration directive
        $imagePrompt = $this->promptBuilder->buildImagePrompt(
            $story->characters_description ?? '',
            $page->illustration_prompt ?? ''
        );

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

            if (! $imageResponse->successful()) {
                \Log::error('Page image generation failed', ['body' => $imageResponse->json()]);

                return response()->json(['error' => 'Image generation failed'], 503);
            }

            $imageUrl = $imageResponse->json()['data'][0]['url'] ?? null;

            if (! $imageUrl) {
                return response()->json(['error' => 'Image generation returned no URL'], 503);
            }

            // Persist the generated image URL
            $page->update(['image_url' => $imageUrl]);

            // Record successful image generation against the user's daily cap.
            TogetherAiUsage::logUsage($userId, TogetherAiUsage::SERVICE_IMAGE, config('services.together.image_model'));

            return response()->json([
                'data' => ['imageUrl' => $imageUrl],
            ]);
        } catch (\Exception $e) {
            \Log::error('Page image generation exception: '.$e->getMessage());

            return response()->json(['error' => 'Image generation failed'], 503);
        }
    }
}
