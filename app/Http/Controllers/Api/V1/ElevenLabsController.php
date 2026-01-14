<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;


class ElevenLabsController extends Controller
{
    /**
     * Provide temporary SDK credentials to the React Native app.
     */
    public function sdkCredentials(Request $request)
    {
        $request->validate([
            'agentId' => 'required|string',
        ]);

        $apiKey = env('ELEVENLABS_API_KEY');
        if (!$apiKey) {
            return response()->json([
                'error' => 'ELEVENLABS_API_KEY is missing in .env'
            ], 500);
        }

        return response()->json([
            'sessionId' => (string) Str::uuid(),
            'apiKey' => $apiKey,
            'agentId' => $request->agentId,
            'expiresAt' => now()->addMinutes(15)->toISOString(),
        ]);
    }


    /**
     * ElevenLabs Text-to-Speech (server-side version).
     * React Native can call this when the JS SDK is unavailable.
     */
    public function textToSpeech(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:5000',
            'voiceId' => 'required|string',
            'options' => 'nullable|array',
        ]);

        $apiKey = env('ELEVENLABS_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY missing'], 500);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept'     => 'audio/mpeg',
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$request->voiceId}", [
            'text' => $request->text,
            'model_id' => $request->options['model_id'] ?? 'eleven_multilingual_v2',
            'voice_settings' => $request->options['voice_settings'] ?? [],
        ]);

        if (!$response->successful()) {
            return response()->json([
                'error' => 'TTS request failed',
                'details' => $response->json()
            ], $response->status());
        }

        return response($response->body(), 200)
            ->header('Content-Type', 'audio/mpeg');
    }


    /**
     * Fetches voices from ElevenLabs (server-side)
     */
    public function voices()
    {
        $apiKey = env('ELEVENLABS_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY missing'], 500);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->get('https://api.elevenlabs.io/v1/voices');

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch voices',
                'details' => $response->json()
            ], $response->status());
        }

        return response()->json($response->json());
    }
}
