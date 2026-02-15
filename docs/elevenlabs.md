# ElevenLabs Integration Documentation

## Overview

StoryWriter uses [ElevenLabs](https://elevenlabs.io) AI services for two primary capabilities:

1. **Text-to-Speech (TTS)**: Converting story text into natural-sounding audio narration
2. **Conversational AI Agents**: Interactive voice-based conversations for story interaction

This document provides comprehensive guidance on the current implementation and best practices for adding new features using ElevenLabs services.

**Status**: Production-ready backend implementation ✅
**Last Updated**: February 15, 2026
**ElevenLabs API Version**: v1

---

## Table of Contents

- [Architecture](#architecture)
- [Current Implementation](#current-implementation)
- [Use Case 1: Text-to-Speech](#use-case-1-text-to-speech)
- [Use Case 2: Conversational AI Agents](#use-case-2-conversational-ai-agents)
- [Configuration](#configuration)
- [Security & Best Practices](#security--best-practices)
- [Error Handling](#error-handling)
- [Cost Management](#cost-management)
- [Testing](#testing)
- [Adding New Features](#adding-new-features)
- [Troubleshooting](#troubleshooting)
- [API Reference](#api-reference)

---

## Architecture

### Backend Proxy Pattern

StoryWriter uses a **backend proxy architecture** to interact with ElevenLabs services. This means:

- **Frontend NEVER has direct access to API keys** 🔒
- All ElevenLabs requests flow through Laravel backend
- Backend manages authentication, rate limiting, logging, and cost tracking
- Frontend receives either signed URLs (for WebSocket) or proxied responses

### Data Flow

```
┌─────────────┐
│   Frontend  │ (React Native/Web)
└──────┬──────┘
       │ HTTP/WebSocket
       ▼
┌─────────────┐
│   Laravel   │ (Backend Proxy)
│  Backend    │
└──────┬──────┘
       │ HTTPS + API Key
       ▼
┌─────────────┐
│ ElevenLabs  │ (AI Services)
│     API     │
└─────────────┘
```

### Key Components

**Backend:**
- `ElevenLabsController` - API endpoint controller
- `config/services.php` - Configuration management
- AWS Parameter Store - Secure API key storage (production)
- Route definitions in `routes/api.php`

**ElevenLabs Services Used:**
- Text-to-Speech API (`/v1/text-to-speech`)
- Conversational AI API (`/v1/convai/conversation`)
- Voices API (`/v1/voices`)

---

## Current Implementation

### Implemented Endpoints

All endpoints are protected by `auth:sanctum` middleware and require authenticated users.

| Endpoint | Method | Purpose | Status |
|----------|--------|---------|--------|
| `/api/conversation/sdk-credentials` | POST | Get signed WebSocket URL for agents | ✅ Deprecated |
| `/api/conversation/proxy` | POST | Proxy conversation requests | ✅ Implemented |
| `/api/conversation/tts` | POST | Generate speech from text | ✅ Implemented |
| `/api/conversation/voices` | GET | List available voices | ✅ Implemented |

### Controller Location

**File:** `app/Http/Controllers/Api/V1/ElevenLabsController.php`

**Methods:**
- `sdkCredentials()` - Generate signed WebSocket URLs for direct frontend connection (deprecated)
- `conversationProxy()` - Proxy conversational AI requests with session management
- `textToSpeech()` - Convert text to MP3 audio
- `voices()` - Fetch available voice models

### Current Features

✅ **Implemented:**
- Text-to-speech conversion (MP3 output)
- Voice model listing
- Signed URL generation for WebSocket connections
- Session-based conversation management
- Request validation and error handling
- Logging for monitoring and debugging

🚧 **Not Yet Implemented:**
- Dedicated service class (logic is currently in controller)
- Custom exception classes for granular error handling
- Usage tracking database table
- Cost monitoring dashboard
- Rate limiting (beyond Laravel's default throttling)
- Backend audio caching

---

## Use Case 1: Text-to-Speech

### Purpose

Convert written story text into natural-sounding audio narration for the vocal narration feature.

### Endpoint

**POST** `/api/conversation/tts`

### Request Format

```json
{
  "text": "Once upon a time in a magical forest...",
  "voiceId": "56AoDkrOh6qfVPDXZ7Pt",
  "options": {
    "model_id": "eleven_flash_v2_5",
    "voice_settings": {
      "stability": 0.5,
      "similarity_boost": 0.75,
      "style": 0.0,
      "use_speaker_boost": true
    }
  }
}
```

### Request Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `text` | string | ✅ Yes | - | Text to convert (max 5000 chars) |
| `voiceId` | string | ✅ Yes | - | ElevenLabs voice ID |
| `options.model_id` | string | ❌ No | `eleven_multilingual_v2` | TTS model to use |
| `options.voice_settings` | object | ❌ No | ElevenLabs defaults | Voice configuration |

### Response

**Success (200 OK):**
```
Content-Type: audio/mpeg
Content-Length: {size_in_bytes}

[Binary MP3 audio data]
```

**Error (4xx/5xx):**
```json
{
  "error": "TTS request failed",
  "details": {
    "message": "Error details from ElevenLabs"
  }
}
```

### Available Models

| Model ID | Speed | Quality | Use Case |
|----------|-------|---------|----------|
| `eleven_flash_v2_5` | ⚡ Fastest | Good | Real-time narration (recommended) |
| `eleven_turbo_v2_5` | ⚡⚡ Ultra-fast | Good | Streaming applications |
| `eleven_multilingual_v2` | 🐌 Slower | Best | High-quality pre-generation |

**Recommendation for StoryWriter:** Use `eleven_flash_v2_5` for vocal narration to balance quality and speed.

### Recommended Voices

| Voice ID | Name | Age | Gender | Accent | Use Case |
|----------|------|-----|--------|--------|----------|
| `56AoDkrOh6qfVPDXZ7Pt` | Cassidy | Young | Female | American | Children's stories (default) |
| `21m00Tcm4TlvDq8ikWAM` | Rachel | Adult | Female | American | General narration |
| `AZnzlk1XvdvUeBnXmlld` | Domi | Young | Female | American | Energetic storytelling |

### Voice Settings

```json
{
  "stability": 0.5,           // 0.0-1.0: Higher = more consistent, lower = more varied
  "similarity_boost": 0.75,   // 0.0-1.0: Voice similarity to original sample
  "style": 0.0,               // 0.0-1.0: Style exaggeration (0 for children's content)
  "use_speaker_boost": true   // Audio enhancement (recommended: true)
}
```

**Recommended Settings for Children's Stories:**
- Stability: `0.5` (natural variation)
- Similarity Boost: `0.75` (clear voice match)
- Style: `0.0` (no exaggeration)
- Speaker Boost: `true` (enhanced clarity)

### Implementation Example

```php
// In ElevenLabsController.php (current implementation)
public function textToSpeech(Request $request)
{
    $request->validate([
        'text' => 'required|string|max:5000',
        'voiceId' => 'required|string',
        'options' => 'nullable|array',
    ]);

    $apiKey = config('services.elevenlabs.api_key');
    if (!$apiKey) {
        return response()->json(['error' => 'ELEVENLABS_API_KEY missing'], 500);
    }

    $response = Http::withHeaders([
        'xi-api-key' => $apiKey,
        'Accept' => 'audio/mpeg',
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
```

---

## Use Case 2: Conversational AI Agents

### Purpose

Enable interactive voice conversations with AI agents for story interaction, Q&A, or character dialogue.

### Architecture Overview

ElevenLabs Agents combine four AI technologies:
1. **Speech Recognition (ASR)** - Transcribe user's voice
2. **Language Model (LLM)** - Understand intent and generate responses
3. **Text-to-Speech (TTS)** - Synthesize natural voice output
4. **Conversation Manager** - Handle turn-taking and flow

### Two Implementation Approaches

#### Approach 1: Signed URL (Deprecated)

**Endpoint:** `POST /api/conversation/sdk-credentials`

Frontend receives a signed WebSocket URL and connects directly to ElevenLabs.

**Request:**
```json
{
  "agentId": "your-agent-id"
}
```

**Response:**
```json
{
  "signed_url": "wss://api.elevenlabs.io/v1/convai/conversation?..."
}
```

**Status:** ⚠️ Deprecated - Use backend proxy instead

#### Approach 2: Backend Proxy (Recommended)

**Endpoint:** `POST /api/conversation/proxy`

All conversation messages flow through Laravel backend for better control.

**Request:**
```json
{
  "sessionId": "uuid-v4-session-id",
  "action": "start|message|end",
  "agentId": "your-agent-id",
  "message": "Tell me a story about dragons"
}
```

**Actions:**
- `start` - Initialize a new conversation
- `message` - Send user message to agent
- `end` - Terminate conversation session

**Response:**
```json
{
  "response": "AI-generated response text",
  "audio_url": "https://elevenlabs.io/audio/...",
  "session_id": "uuid-v4-session-id"
}
```

### Session Management

The proxy approach uses Laravel Cache for session tracking:

```php
// Store session on start
Cache::put("elevenlabs_session:{$sessionId}", [
    'agent_id' => $agentId,
    'user_id' => auth()->id(),
    'started_at' => now(),
], now()->addHours(2));

// Validate session on subsequent requests
$session = Cache::get("elevenlabs_session:{$sessionId}");
if (!$session || $session['agent_id'] !== $request->agentId) {
    return response()->json(['error' => 'Invalid or expired session'], 401);
}

// Clear session on end
Cache::forget("elevenlabs_session:{$sessionId}");
```

### Creating an Agent

Agents are created in the [ElevenLabs Dashboard](https://elevenlabs.io/app/conversational-ai):

1. Navigate to "Conversational AI" section
2. Click "Create Agent"
3. Configure:
   - **Name**: "StoryWriter Assistant"
   - **Voice**: Select from 5,000+ voices
   - **LLM**: GPT-4, Claude, or custom
   - **System Prompt**: Define agent behavior
   - **Knowledge Base**: Upload story content (optional)
   - **Tools**: Connect external APIs (optional)
4. Copy the **Agent ID** for use in API calls

### Agent Configuration Example

```yaml
Agent Name: StoryWriter Story Helper
Voice: Cassidy (56AoDkrOh6qfVPDXZ7Pt)
LLM: GPT-4
Languages: English

System Prompt: |
  You are a friendly storytelling assistant for children aged 4-8.
  Help children understand stories, answer questions about characters,
  and encourage imagination. Keep responses short (2-3 sentences),
  use simple language, and maintain a warm, enthusiastic tone.

Tools:
  - get_story_details: Fetch current story metadata
  - suggest_next_page: Recommend next story action

Knowledge Base:
  - StoryWriter story database (RAG-enabled)
```

---

## Configuration

### Environment Variables

**Local Development (`.env`):**
```env
# ElevenLabs API Key (get from https://elevenlabs.io/app/settings/api-keys)
ELEVENLABS_API_KEY=sk_your_api_key_here

# Optional: Default voice and model
ELEVENLABS_DEFAULT_VOICE_ID=56AoDkrOh6qfVPDXZ7Pt
ELEVENLABS_DEFAULT_MODEL=eleven_flash_v2_5
```

**Production/Staging (AWS Parameter Store):**
```bash
# Store in AWS Systems Manager Parameter Store
aws ssm put-parameter \
  --name "/storywriter/production/ELEVENLABS_API_KEY" \
  --value "sk_your_production_key" \
  --type "SecureString" \
  --description "ElevenLabs API key for production"
```

### Service Configuration

**File:** `config/services.php`

```php
return [
    // ... other services

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'default_voice_id' => env('ELEVENLABS_DEFAULT_VOICE_ID', '56AoDkrOh6qfVPDXZ7Pt'),
        'default_model' => env('ELEVENLABS_DEFAULT_MODEL', 'eleven_flash_v2_5'),
        'timeout' => env('ELEVENLABS_TIMEOUT', 30), // seconds
        'base_url' => 'https://api.elevenlabs.io/v1',
    ],
];
```

### Route Configuration

**File:** `routes/api.php`

```php
use App\Http\Controllers\Api\V1\ElevenLabsController;

// ElevenLabs endpoints - require authentication
Route::prefix('conversation')->middleware('auth:sanctum')->group(function () {
    Route::post('/sdk-credentials', [ElevenLabsController::class, 'sdkCredentials']); // Deprecated
    Route::post('/proxy', [ElevenLabsController::class, 'conversationProxy']);
    Route::post('/tts', [ElevenLabsController::class, 'textToSpeech']);
    Route::get('/voices', [ElevenLabsController::class, 'voices']);
});
```

---

## Security & Best Practices

### 1. API Key Protection

**🔴 CRITICAL: Never expose API keys to frontend**

✅ **DO:**
- Store keys in `.env` (local) or AWS Parameter Store (production)
- Use backend proxy for all ElevenLabs requests
- Rotate keys every 90 days
- Use separate keys for dev/staging/production
- Log all API usage for auditing

❌ **DON'T:**
- Hardcode keys in source code
- Commit `.env` to version control
- Send keys to frontend (even temporarily)
- Share keys via email or Slack
- Use production keys in development

### 2. Rate Limiting

Implement server-side rate limiting to prevent abuse:

```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/conversation/tts', [ElevenLabsController::class, 'textToSpeech']);
});
```

**Recommended Limits:**
- Per user: 60 TTS requests per minute
- Per IP: 100 requests per minute
- Daily per user: 1000 TTS requests

### 3. Input Validation

Always validate and sanitize user input:

```php
$request->validate([
    'text' => 'required|string|max:5000',
    'voiceId' => 'required|string|regex:/^[a-zA-Z0-9]+$/',
    'options.model_id' => 'nullable|string|in:eleven_flash_v2_5,eleven_turbo_v2_5,eleven_multilingual_v2',
    'options.voice_settings.stability' => 'nullable|numeric|between:0,1',
    'options.voice_settings.similarity_boost' => 'nullable|numeric|between:0,1',
    'options.voice_settings.style' => 'nullable|numeric|between:0,1',
    'options.voice_settings.use_speaker_boost' => 'nullable|boolean',
]);

// Strip HTML tags from text
$text = strip_tags($request->text);
```

### 4. Request Logging

Log all requests for monitoring and cost tracking:

```php
Log::info('ElevenLabs TTS request', [
    'user_id' => auth()->id(),
    'text_length' => strlen($text),
    'voice_id' => $voiceId,
    'model_id' => $modelId,
    'timestamp' => now()->toIso8601String(),
]);
```

### 5. Timeout Configuration

Set appropriate timeouts to prevent hanging requests:

```php
$response = Http::timeout(30) // 30 seconds
    ->withHeaders([
        'xi-api-key' => $apiKey,
        'Accept' => 'audio/mpeg',
    ])
    ->post($url, $payload);
```

---

## Error Handling

### ElevenLabs Error Codes

| Status | Error | Meaning | Action |
|--------|-------|---------|--------|
| 401 | Unauthorized | Invalid API key | Check key configuration |
| 400 | Bad Request | Invalid parameters | Validate request payload |
| 404 | Not Found | Voice/model doesn't exist | Use valid voice ID |
| 422 | Unprocessable Entity | Text validation failed | Check text content |
| 429 | Rate Limit Exceeded | Too many requests | Implement backoff/retry |
| 500 | Internal Server Error | ElevenLabs service issue | Retry with exponential backoff |
| 503 | Service Unavailable | ElevenLabs downtime | Show user-friendly error |

### Error Response Format

```json
{
  "error": "Human-readable error message",
  "details": {
    "code": "ERROR_CODE",
    "message": "Technical details from ElevenLabs",
    "status": 500
  }
}
```

### Retry Strategy

For transient errors (500, 503, 429), implement exponential backoff:

```php
use Illuminate\Support\Facades\Http;

$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        $response = Http::timeout(30)
            ->withHeaders(['xi-api-key' => $apiKey])
            ->post($url, $payload);

        if ($response->successful()) {
            return $response->body();
        }

        // Don't retry client errors (4xx)
        if ($response->status() >= 400 && $response->status() < 500) {
            throw new Exception('Client error: ' . $response->status());
        }

    } catch (\Exception $e) {
        $attempt++;
        if ($attempt >= $maxRetries) {
            throw $e;
        }

        // Exponential backoff: 1s, 2s, 4s
        sleep(pow(2, $attempt - 1));
    }
}
```

---

## Cost Management

### Pricing Model

ElevenLabs charges based on **character count** (not audio duration):

| Model | Cost per Character | Example: 1000 chars |
|-------|-------------------|---------------------|
| Flash v2.5 | ~$0.024/1000 chars | $0.024 |
| Turbo v2.5 | ~$0.024/1000 chars | $0.024 |
| Multilingual v2 | ~$0.030/1000 chars | $0.030 |

**Note:** Prices are approximate and may vary. Check [ElevenLabs Pricing](https://elevenlabs.io/pricing) for current rates.

### Usage Tracking (Future Enhancement)

Create a database table to track costs:

```php
// Database Migration
Schema::create('elevenlabs_usage', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('service_type'); // 'tts', 'conversation'
    $table->integer('character_count');
    $table->string('voice_id')->nullable();
    $table->string('model_id')->nullable();
    $table->decimal('estimated_cost', 10, 4); // USD
    $table->timestamps();

    $table->index(['user_id', 'created_at']);
});

// Usage Tracking Model
class ElevenLabsUsage extends Model
{
    protected $fillable = [
        'user_id',
        'service_type',
        'character_count',
        'voice_id',
        'model_id',
        'estimated_cost',
    ];

    public static function logTtsRequest(string $text, string $voiceId, string $modelId): void
    {
        $characterCount = strlen($text);
        $costPerChar = $modelId === 'eleven_multilingual_v2' ? 0.000030 : 0.000024;
        $estimatedCost = $characterCount * $costPerChar;

        self::create([
            'user_id' => auth()->id(),
            'service_type' => 'tts',
            'character_count' => $characterCount,
            'voice_id' => $voiceId,
            'model_id' => $modelId,
            'estimated_cost' => $estimatedCost,
        ]);
    }
}
```

### Cost Optimization Tips

1. **Use Flash models** for real-time narration (cheapest)
2. **Cache frequently-used audio** (implement backend caching)
3. **Batch similar requests** when possible
4. **Set daily user limits** to prevent runaway costs
5. **Monitor usage dashboards** regularly
6. **Use text chunking** efficiently (avoid duplicate TTS for same text)

---

## Testing

### Manual Testing with cURL

**Test Text-to-Speech:**
```bash
# Get authentication token first
TOKEN="your_sanctum_token"

# Request TTS audio
curl -X POST http://localhost:8000/api/conversation/tts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "text": "Once upon a time in a magical forest",
    "voiceId": "56AoDkrOh6qfVPDXZ7Pt",
    "options": {
      "model_id": "eleven_flash_v2_5"
    }
  }' \
  --output test-narration.mp3

# Verify audio file
file test-narration.mp3  # Should show: Audio file with ID3 version 2.4.0
ffprobe test-narration.mp3  # Check audio details
```

**Test Voice Listing:**
```bash
curl -X GET http://localhost:8000/api/conversation/voices \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

### Automated Testing

**Feature Test Example:**
```php
// tests/Feature/ElevenLabsControllerTest.php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ElevenLabsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_to_speech_returns_audio()
    {
        $user = User::factory()->create();

        // Mock ElevenLabs API response
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                file_get_contents(base_path('tests/fixtures/sample-audio.mp3')),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Test narration',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
                'options' => [
                    'model_id' => 'eleven_flash_v2_5',
                ],
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_tts_requires_authentication()
    {
        $response = $this->postJson('/api/conversation/tts', [
            'text' => 'Test',
            'voiceId' => 'test-voice',
        ]);

        $response->assertStatus(401);
    }

    public function test_tts_validates_text_length()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => str_repeat('a', 5001), // Exceeds 5000 char limit
                'voiceId' => 'test-voice',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('text');
    }

    public function test_voices_endpoint_returns_list()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/voices' => Http::response([
                'voices' => [
                    [
                        'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
                        'name' => 'Cassidy',
                        'category' => 'premade',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/conversation/voices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'voices' => [
                '*' => ['voice_id', 'name', 'category'],
            ],
        ]);
    }
}
```

---

## Adding New Features

### Guidelines for New ElevenLabs Features

When adding new features that use ElevenLabs services, follow these patterns:

#### 1. Service-Based Architecture (Recommended Refactor)

Instead of putting logic in controllers, create a dedicated service class:

```php
// app/Services/ElevenLabsService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ElevenLabsException;

class ElevenLabsService
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.elevenlabs.api_key');
        $this->baseUrl = config('services.elevenlabs.base_url');
        $this->timeout = config('services.elevenlabs.timeout', 30);

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ElevenLabs API key not configured');
        }
    }

    public function generateSpeech(
        string $text,
        string $voiceId,
        string $modelId = 'eleven_flash_v2_5',
        array $voiceSettings = []
    ): string {
        $url = "{$this->baseUrl}/text-to-speech/{$voiceId}";

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'xi-api-key' => $this->apiKey,
                'Accept' => 'audio/mpeg',
            ])
            ->post($url, [
                'text' => $text,
                'model_id' => $modelId,
                'voice_settings' => $voiceSettings,
            ]);

        if (!$response->successful()) {
            $this->handleError($response);
        }

        return $response->body();
    }

    public function getVoices(): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'xi-api-key' => $this->apiKey,
            ])
            ->get("{$this->baseUrl}/voices");

        if (!$response->successful()) {
            $this->handleError($response);
        }

        return $response->json()['voices'] ?? [];
    }

    private function handleError($response): void
    {
        $statusCode = $response->status();
        $body = $response->json();

        Log::error('ElevenLabs API error', [
            'status' => $statusCode,
            'body' => $body,
        ]);

        throw new ElevenLabsException(
            $body['detail']['message'] ?? 'ElevenLabs API request failed',
            $statusCode
        );
    }
}
```

#### 2. Custom Exception Classes

Create specific exception types for better error handling:

```php
// app/Exceptions/ElevenLabsException.php

namespace App\Exceptions;

class ElevenLabsException extends \Exception
{
    protected int $statusCode;

    public function __construct(string $message, int $statusCode = 500)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

// app/Exceptions/ElevenLabsRateLimitException.php
class ElevenLabsRateLimitException extends ElevenLabsException {}

// app/Exceptions/ElevenLabsAuthException.php
class ElevenLabsAuthException extends ElevenLabsException {}
```

#### 3. Feature-Specific Controllers

Create dedicated controllers for specific features:

```php
// app/Http/Controllers/Api/V1/StoryNarrationController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ElevenLabsService;
use Illuminate\Http\Request;

class StoryNarrationController extends Controller
{
    public function __construct(
        private ElevenLabsService $elevenLabs
    ) {}

    public function narratePage(Request $request)
    {
        $request->validate([
            'story_id' => 'required|exists:stories,id',
            'page_number' => 'required|integer|min:1',
        ]);

        // Get story page text
        $story = Story::findOrFail($request->story_id);
        $pageText = $this->getPageText($story, $request->page_number);

        // Generate audio
        $audio = $this->elevenLabs->generateSpeech(
            $pageText,
            config('services.elevenlabs.default_voice_id'),
            'eleven_flash_v2_5'
        );

        return response($audio)
            ->header('Content-Type', 'audio/mpeg');
    }
}
```

#### 4. Request Validation with Form Requests

Use Form Requests for complex validation:

```php
// app/Http/Requests/TextToSpeechRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TextToSpeechRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string|max:5000',
            'voiceId' => 'required|string|regex:/^[a-zA-Z0-9]+$/',
            'options.model_id' => 'nullable|string|in:eleven_flash_v2_5,eleven_turbo_v2_5,eleven_multilingual_v2',
            'options.voice_settings.stability' => 'nullable|numeric|between:0,1',
            'options.voice_settings.similarity_boost' => 'nullable|numeric|between:0,1',
            'options.voice_settings.style' => 'nullable|numeric|between:0,1',
            'options.voice_settings.use_speaker_boost' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'text.max' => 'Text cannot exceed 5000 characters for TTS conversion.',
            'options.model_id.in' => 'Invalid model selected. Please choose a valid TTS model.',
        ];
    }
}
```

### Example: Adding Voice Cloning Feature

If you want to add voice cloning capabilities:

1. **Research ElevenLabs API:**
   - Read [Voice Cloning Docs](https://elevenlabs.io/docs/api-reference/add-voice)
   - Understand request/response format
   - Check pricing and limits

2. **Plan the Feature:**
   - Decide on user flow (upload samples, train voice, use voice)
   - Design database schema (store voice IDs)
   - Plan authorization (who can clone voices?)

3. **Implement Backend:**
   ```php
   // In ElevenLabsService
   public function cloneVoice(string $name, array $audioFiles): string
   {
       $url = "{$this->baseUrl}/voices/add";

       $multipart = [
           ['name' => 'name', 'contents' => $name],
       ];

       foreach ($audioFiles as $index => $file) {
           $multipart[] = [
               'name' => "files[$index]",
               'contents' => fopen($file, 'r'),
               'filename' => basename($file),
           ];
       }

       $response = Http::timeout(60) // Longer timeout for uploads
           ->withHeaders(['xi-api-key' => $this->apiKey])
           ->attach($multipart)
           ->post($url);

       if (!$response->successful()) {
           $this->handleError($response);
       }

       return $response->json()['voice_id'];
   }
   ```

4. **Create Controller Endpoint:**
   ```php
   Route::post('/conversation/clone-voice', [VoiceCloningController::class, 'clone'])
       ->middleware(['auth:sanctum', 'throttle:5,60']); // 5 clones per hour
   ```

5. **Write Tests:**
   - Test successful voice cloning
   - Test validation (file types, count)
   - Test rate limiting
   - Test error scenarios

6. **Document in This File:**
   - Add new section for voice cloning
   - Document endpoint, parameters, response
   - Update table of contents

---

## Troubleshooting

### Common Issues

#### "ELEVENLABS_API_KEY missing"

**Cause:** API key not configured in environment
**Solution:**
```bash
# Check .env file
grep ELEVENLABS_API_KEY .env

# If missing, add it:
echo "ELEVENLABS_API_KEY=sk_your_key_here" >> .env

# Clear config cache
php artisan config:clear
```

#### "Invalid API key or unauthorized" (401)

**Cause:** API key is invalid or expired
**Solution:**
1. Log into [ElevenLabs Dashboard](https://elevenlabs.io/app/settings/api-keys)
2. Verify key is active
3. Generate new key if needed
4. Update `.env` and clear config cache

#### "Rate limit exceeded" (429)

**Cause:** Too many requests to ElevenLabs API
**Solution:**
- Check account quota in dashboard
- Implement exponential backoff
- Upgrade ElevenLabs plan if needed
- Review usage patterns for optimization

#### "Request timeout"

**Cause:** Request took longer than timeout setting
**Solution:**
```php
// Increase timeout in config/services.php
'elevenlabs' => [
    'timeout' => 60, // Increase to 60 seconds
],

// Or per-request:
Http::timeout(60)->post($url, $payload);
```

#### Audio plays as noise/corrupted

**Cause:** Response is not MP3 data
**Solution:**
```php
// Verify Content-Type before returning
if ($response->header('Content-Type') !== 'audio/mpeg') {
    Log::error('Invalid audio response', [
        'content_type' => $response->header('Content-Type'),
    ]);
    throw new Exception('Received invalid audio format');
}
```

#### "Voice not found" (404)

**Cause:** Voice ID doesn't exist
**Solution:**
- Verify voice ID from `/api/conversation/voices` endpoint
- Check for typos in voice ID
- Ensure voice wasn't deleted from ElevenLabs account

### Debugging Tips

**Enable detailed logging:**
```php
// In ElevenLabsController methods
Log::debug('ElevenLabs request', [
    'url' => $url,
    'payload' => $payload,
    'headers' => [
        'xi-api-key' => substr($apiKey, 0, 10) . '...', // Don't log full key
    ],
]);

Log::debug('ElevenLabs response', [
    'status' => $response->status(),
    'headers' => $response->headers(),
    'body_length' => strlen($response->body()),
]);
```

**Test API directly with curl:**
```bash
# Bypass Laravel to test ElevenLabs API directly
curl -X POST "https://api.elevenlabs.io/v1/text-to-speech/56AoDkrOh6qfVPDXZ7Pt" \
  -H "xi-api-key: $ELEVENLABS_API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: audio/mpeg" \
  -d '{
    "text": "Test",
    "model_id": "eleven_flash_v2_5"
  }' \
  --output direct-test.mp3
```

**Monitor API usage:**
```bash
# Check Laravel logs for ElevenLabs requests
tail -f storage/logs/laravel.log | grep ElevenLabs

# Or use Laravel Pail
php artisan pail --filter=elevenlabs
```

---

## API Reference

### Official ElevenLabs Documentation

- **API Reference:** https://elevenlabs.io/docs/api-reference/text-to-speech
- **Quickstart Guide:** https://elevenlabs.io/docs/eleven-api/quickstart
- **Conversational AI:** https://elevenlabs.io/docs/eleven-agents/overview
- **Voice Settings:** https://elevenlabs.io/docs/speech-synthesis/voice-settings
- **Error Codes:** https://elevenlabs.io/docs/api-reference/errors
- **Pricing:** https://elevenlabs.io/pricing

### ElevenLabs Base URL

```
https://api.elevenlabs.io/v1
```

### Authentication Header

```
xi-api-key: {your_api_key}
```

### Key Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/text-to-speech/{voice_id}` | POST | Generate audio from text |
| `/voices` | GET | List available voices |
| `/voices/{voice_id}` | GET | Get voice details |
| `/convai/conversation/get_signed_url` | GET | Get WebSocket signed URL |
| `/convai/conversation` | POST | Start conversation |
| `/convai/conversation/{id}/message` | POST | Send message to agent |

### Voice IDs (StoryWriter Favorites)

| ID | Name | Description |
|----|------|-------------|
| `56AoDkrOh6qfVPDXZ7Pt` | Cassidy | Young, friendly female (default) |
| `21m00Tcm4TlvDq8ikWAM` | Rachel | Professional adult female |
| `AZnzlk1XvdvUeBnXmlld` | Domi | Energetic young female |
| `ErXwobaYiN019PkySvjV` | Antoni | Calm adult male |

### Rate Limits

| Plan | Characters/Month | Requests/Min |
|------|------------------|--------------|
| Free | 10,000 | 20 |
| Starter | 30,000 | 60 |
| Creator | 100,000 | 120 |
| Pro | 500,000 | 240 |

**Note:** Check [ElevenLabs Pricing](https://elevenlabs.io/pricing) for current limits.

---

## Related Documentation

- **Backend Implementation Plan:** [docs/vocal-narration-backend.md](./vocal-narration-backend.md)
- **Frontend Implementation:** [docs/vocal-narration.md](./vocal-narration.md)
- **Project Overview:** [docs/CLAUDE.md](./CLAUDE.md)
- **API Documentation:** [docs/api-stories-v1.md](./api-stories-v1.md)

---

## Changelog

| Date | Changes |
|------|---------|
| 2026-02-15 | Initial documentation created |

---

**Questions or Issues?**

- Check [ElevenLabs Support](https://help.elevenlabs.io)
- Review Laravel logs: `storage/logs/laravel.log`
- Contact StoryWriter development team

---

**This documentation is maintained by the StoryWriter development team. Please keep it updated when adding new ElevenLabs features.**
