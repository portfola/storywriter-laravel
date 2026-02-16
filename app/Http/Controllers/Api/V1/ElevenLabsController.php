<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ElevenLabsUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsController extends Controller
{
    /**
     * Get a signed URL for ElevenLabs Conversational AI.
     *
     * Returns a signed WebSocket URL from ElevenLabs that the frontend can use
     * to connect directly without exposing the API key.
     */
    public function sdkCredentials(Request $request)
    {
        $request->validate([
            'agentId' => 'required|string',
        ]);

        $apiKey = config('services.elevenlabs.api_key');
        if (! $apiKey) {
            Log::error('ElevenLabs API key not configured', [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'error' => 'ELEVENLABS_API_KEY is not configured',
            ], 500);
        }

        // Get signed URL from ElevenLabs
        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
        ])->get('https://api.elevenlabs.io/v1/convai/conversation/get_signed_url', [
            'agent_id' => $request->agentId,
        ]);

        if (! $response->successful()) {
            Log::error('Failed to get ElevenLabs signed URL', [
                'user_id' => $request->user()?->id,
                'agent_id' => $request->agentId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return response()->json([
                'error' => 'Failed to get signed URL from ElevenLabs',
                'details' => $response->json(),
            ], $response->status());
        }

        Log::info('ElevenLabs signed URL generated', [
            'user_id' => $request->user()?->id,
            'agent_id' => $request->agentId,
        ]);

        return response()->json($response->json());
    }

    /**
     * Proxy endpoint for ElevenLabs Conversational AI.
     * Use this instead of client-side SDK to keep API keys secure.
     */
    public function conversationProxy(Request $request)
    {
        $request->validate([
            'sessionId' => 'required|string|uuid',
            'action' => 'required|string|in:start,message,end',
            'agentId' => 'required|string',
            'message' => 'nullable|string|max:5000',
        ]);

        // Validate session
        $session = Cache::get("elevenlabs_session:{$request->sessionId}");
        if (! $session || $session['agent_id'] !== $request->agentId) {
            return response()->json(['error' => 'Invalid or expired session'], 401);
        }

        $apiKey = config('services.elevenlabs.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY is not configured'], 500);
        }

        // Handle different conversation actions
        switch ($request->action) {
            case 'start':
                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://api.elevenlabs.io/v1/convai/conversation', [
                    'agent_id' => $request->agentId,
                ]);
                break;

            case 'message':
                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->post("https://api.elevenlabs.io/v1/convai/conversation/{$request->sessionId}/message", [
                    'message' => $request->message,
                ]);

                // Log usage for conversation messages
                if ($response->successful() && $request->message) {
                    ElevenLabsUsage::logConversationRequest(
                        message: $request->message,
                        agentId: $request->agentId
                    );
                }
                break;

            case 'end':
                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                ])->delete("https://api.elevenlabs.io/v1/convai/conversation/{$request->sessionId}");
                Cache::forget("elevenlabs_session:{$request->sessionId}");
                break;

            default:
                return response()->json(['error' => 'Invalid action'], 400);
        }

        if (! $response->successful()) {
            return response()->json([
                'error' => 'ElevenLabs API request failed',
                'details' => $response->json(),
            ], $response->status());
        }

        return response()->json($response->json());
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

        $apiKey = config('services.elevenlabs.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY missing'], 500);
        }

        $modelId = $request->options['model_id'] ?? config('services.elevenlabs.default_model');

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept' => 'audio/mpeg',
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$request->voiceId}", [
            'text' => $request->text,
            'model_id' => $modelId,
            'voice_settings' => $request->options['voice_settings'] ?? [],
        ]);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'TTS request failed',
                'details' => $response->json(),
            ], $response->status());
        }

        // Log successful TTS request for usage tracking
        ElevenLabsUsage::logTtsRequest(
            text: $request->text,
            voiceId: $request->voiceId,
            modelId: $modelId
        );

        return response($response->body(), 200)
            ->header('Content-Type', 'audio/mpeg');
    }

    /**
     * Fetches voices from ElevenLabs (server-side)
     */
    public function voices()
    {
        $apiKey = config('services.elevenlabs.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'ELEVENLABS_API_KEY missing'], 500);
        }

        $response = Http::withHeaders([
            'xi-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->get('https://api.elevenlabs.io/v1/voices');

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch voices',
                'details' => $response->json(),
            ], $response->status());
        }

        return response()->json($response->json());
    }
}
