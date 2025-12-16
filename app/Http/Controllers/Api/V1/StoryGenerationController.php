<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StoryGenerationController extends Controller
{

    /**
     * Generate a story using Together AI based on a transcript.
     */
    public function generate(Request $request)
    {

        $validated = $request->validate([
            'transcript' => 'required|string',
            'options' => 'nullable|array',
        ]);

        $apiKey = env('TOGETHER_API_KEY');
        if (!$apiKey) {
            return response()->json([
                'error' => 'TOGETHER_API_KEY missing in .env'
            ], 500);
        }

        // Extract generation options (with sensible defaults)
        $options = $validated['options'] ?? [];
        $maxTokens   = $options['maxTokens']   ?? 1000;
        $temperature = $options['temperature'] ?? 0.7;

        // Prepare Together AI prompt
        $prompt = $validated['transcript'];

          // Debug incoming request
        \Log::info("STORY GEN REQUEST", $request->all());

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.together.xyz/v1/chat/completions', [
            //'model' => 'meta-llama/Meta-Llama-3-8B-Instruct',
            //'model' => 'meta-llama/Llama-3.1-8B-Instruct-Turbo',
            'model' => 'mistralai/Mistral-7B-Instruct-v0.3',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a children’s story generator. Write clear, simple stories.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]);

         // Debug raw TogetherAI output
        \Log::info("TOGETHER RAW", [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $response->json(),
        ]);

        if (!$response->successful()) {
                \Log::error('Together AI error', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
            return response()->json([
                'error' => 'AI generation failed',
                'details' => $response->json(),
            ], $response->status());
        }

        $generatedText = $response->json()['choices'][0]['message']['content'] ?? null;

        if (!$generatedText) {
            return response()->json([
                'error' => 'Invalid AI response',
            ], 500);
        }

        \Log::info('STORY_RESPONSE', [
            'final_json' => [
                'data' => [
                    'story' => $generatedText,
                ]
            ]
        ]);

        // RN expects: { "story": "text..." }
        return response()->json([
            'data' => [
                'story' => $generatedText,
            ]
        ]);
    }
}