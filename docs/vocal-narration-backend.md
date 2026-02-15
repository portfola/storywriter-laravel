# Vocal Narration - Backend Requirements

## Overview

This document specifies the Laravel backend API endpoints required to support the vocal narration feature in the StoryWriter frontend. The backend acts as a proxy to the ElevenLabs API, providing centralized API key management, request monitoring, and error handling.

**Related Documentation:**
- `docs/vocal-narration.md` - Frontend implementation plan
- `docs/elevenlabs.md` - Frontend service implementation details

---

## Architecture

### Backend Responsibilities

1. **API Key Management**: Store and use ElevenLabs API key securely
2. **Request Proxying**: Forward TTS requests to ElevenLabs API
3. **Error Handling**: Translate ElevenLabs errors to frontend-friendly responses
4. **Rate Limiting**: Implement server-side rate limiting to prevent abuse
5. **Request Logging**: Track TTS usage for monitoring and cost management
6. **Authentication**: Ensure only authenticated users can access TTS endpoints

### Data Flow

```
Frontend (BookReader)
    ↓ HTTP POST /api/voice/tts
Laravel Backend
    ↓ ElevenLabs API Call
ElevenLabs Service
    ↓ MP3 Audio Response
Laravel Backend
    ↓ Binary MP3 Response
Frontend (Audio Player)
```

---

## Required Endpoints

### 1. Generate Speech (Primary Endpoint)

**Endpoint:** `POST /api/voice/tts`

**Purpose:** Generate MP3 audio from text using ElevenLabs TTS API

**Authentication:** Required (Bearer token)

**Request Headers:**
```
Authorization: Bearer {user_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
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

**Request Parameters:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `text` | string | Yes | - | Text to convert to speech (max 5000 chars) |
| `voiceId` | string | No | `56AoDkrOh6qfVPDXZ7Pt` | ElevenLabs voice ID (Cassidy) |
| `options.model_id` | string | No | `eleven_flash_v2_5` | ElevenLabs model to use |
| `options.voice_settings` | object | No | See defaults | Voice configuration |
| `options.voice_settings.stability` | float | No | 0.5 | Voice consistency (0.0-1.0) |
| `options.voice_settings.similarity_boost` | float | No | 0.75 | Voice similarity (0.0-1.0) |
| `options.voice_settings.style` | float | No | 0.0 | Style exaggeration (0.0-1.0) |
| `options.voice_settings.use_speaker_boost` | boolean | No | true | Audio enhancement |

**Validation Rules:**
- `text`: Required, non-empty, max 5000 characters
- `voiceId`: Must be valid ElevenLabs voice ID
- `model_id`: Must be one of: `eleven_multilingual_v2`, `eleven_flash_v2_5`, `eleven_turbo_v2_5`
- Voice settings floats: 0.0-1.0 range

**Success Response:**

**Status:** `200 OK`

**Headers:**
```
Content-Type: audio/mpeg
Content-Length: {audio_size_bytes}
```

**Body:** Binary MP3 audio data

**Error Responses:**

| Status | Error Code | Message | When |
|--------|-----------|---------|------|
| 400 | `INVALID_REQUEST` | "Text is required" | Missing text field |
| 400 | `TEXT_TOO_LONG` | "Text exceeds 5000 character limit" | Text > 5000 chars |
| 400 | `INVALID_PARAMETERS` | "Invalid voice settings" | Invalid voice config |
| 401 | `UNAUTHORIZED` | "Authentication required" | Missing/invalid auth token |
| 401 | `ELEVENLABS_UNAUTHORIZED` | "Invalid ElevenLabs API key" | ElevenLabs API key invalid |
| 429 | `RATE_LIMIT_EXCEEDED` | "Rate limit exceeded. Please try again later." | Too many requests |
| 500 | `ELEVENLABS_ERROR` | "Text-to-speech service unavailable" | ElevenLabs API error |
| 503 | `SERVICE_UNAVAILABLE` | "Service temporarily unavailable" | Backend/network issues |

**Error Response Format:**
```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Rate limit exceeded. Please try again later.",
    "status": 429
  }
}
```

**Implementation Requirements:**

```php
// VoiceController.php

public function textToSpeech(Request $request)
{
    // 1. Validate request
    $validated = $request->validate([
        'text' => 'required|string|max:5000',
        'voiceId' => 'nullable|string',
        'options.model_id' => 'nullable|string|in:eleven_multilingual_v2,eleven_flash_v2_5,eleven_turbo_v2_5',
        'options.voice_settings.stability' => 'nullable|numeric|between:0,1',
        'options.voice_settings.similarity_boost' => 'nullable|numeric|between:0,1',
        'options.voice_settings.style' => 'nullable|numeric|between:0,1',
        'options.voice_settings.use_speaker_boost' => 'nullable|boolean',
    ]);

    // 2. Extract parameters with defaults
    $text = $validated['text'];
    $voiceId = $validated['voiceId'] ?? '56AoDkrOh6qfVPDXZ7Pt'; // Cassidy
    $modelId = $validated['options']['model_id'] ?? 'eleven_flash_v2_5';
    $voiceSettings = $validated['options']['voice_settings'] ?? [
        'stability' => 0.5,
        'similarity_boost' => 0.75,
        'style' => 0.0,
        'use_speaker_boost' => true,
    ];

    try {
        // 3. Call ElevenLabs API
        $audioData = $this->elevenLabsService->generateSpeech(
            $text,
            $voiceId,
            $modelId,
            $voiceSettings
        );

        // 4. Return binary audio response
        return response($audioData)
            ->header('Content-Type', 'audio/mpeg')
            ->header('Content-Length', strlen($audioData));

    } catch (ElevenLabsRateLimitException $e) {
        // 5. Handle rate limiting
        return response()->json([
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Rate limit exceeded. Please try again later.',
                'status' => 429,
            ]
        ], 429);

    } catch (ElevenLabsException $e) {
        // 6. Handle ElevenLabs API errors
        Log::error('ElevenLabs API error', [
            'message' => $e->getMessage(),
            'status' => $e->getStatusCode(),
        ]);

        return response()->json([
            'error' => [
                'code' => 'ELEVENLABS_ERROR',
                'message' => 'Text-to-speech service unavailable',
                'status' => 500,
            ]
        ], 500);

    } catch (\Exception $e) {
        // 7. Handle unexpected errors
        Log::error('TTS request failed', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error' => [
                'code' => 'SERVICE_UNAVAILABLE',
                'message' => 'Service temporarily unavailable',
                'status' => 503,
            ]
        ], 503);
    }
}
```

---

### 2. Get Available Voices (Optional)

**Endpoint:** `GET /api/voice/voices`

**Purpose:** Retrieve list of available ElevenLabs voices

**Authentication:** Required

**Request Headers:**
```
Authorization: Bearer {user_token}
Accept: application/json
```

**Success Response:**

**Status:** `200 OK`

**Body:**
```json
{
  "voices": [
    {
      "voice_id": "56AoDkrOh6qfVPDXZ7Pt",
      "name": "Cassidy",
      "category": "premade",
      "description": "Young, friendly female voice",
      "labels": {
        "age": "young",
        "gender": "female",
        "accent": "american"
      },
      "preview_url": "https://..."
    },
    // ... more voices
  ]
}
```

**Implementation:**
```php
public function getVoices()
{
    try {
        $voices = $this->elevenLabsService->getVoices();
        return response()->json(['voices' => $voices]);
    } catch (ElevenLabsException $e) {
        return response()->json([
            'error' => [
                'code' => 'ELEVENLABS_ERROR',
                'message' => 'Failed to fetch voices',
                'status' => 500,
            ]
        ], 500);
    }
}
```

---

### 3. Get Specific Voice (Optional)

**Endpoint:** `GET /api/voice/{voiceId}`

**Purpose:** Retrieve details for a specific voice

**Authentication:** Required

**Path Parameters:**
- `voiceId` (string): ElevenLabs voice ID

**Success Response:**

**Status:** `200 OK`

**Body:**
```json
{
  "voice_id": "56AoDkrOh6qfVPDXZ7Pt",
  "name": "Cassidy",
  "category": "premade",
  "settings": {
    "stability": 0.5,
    "similarity_boost": 0.75
  },
  "samples": [
    {
      "sample_id": "...",
      "file_name": "...",
      "mime_type": "audio/mpeg"
    }
  ]
}
```

**Error Responses:**
- `404`: Voice not found
- `500`: ElevenLabs API error

**Implementation:**
```php
public function getVoice(string $voiceId)
{
    try {
        $voice = $this->elevenLabsService->getVoice($voiceId);
        return response()->json($voice);
    } catch (ElevenLabsNotFoundException $e) {
        return response()->json([
            'error' => [
                'code' => 'VOICE_NOT_FOUND',
                'message' => 'Voice not found',
                'status' => 404,
            ]
        ], 404);
    } catch (ElevenLabsException $e) {
        return response()->json([
            'error' => [
                'code' => 'ELEVENLABS_ERROR',
                'message' => 'Failed to fetch voice details',
                'status' => 500,
            ]
        ], 500);
    }
}
```

---

## ElevenLabs Service Integration

### Service Class Structure

Create a dedicated service class for ElevenLabs API integration:

```php
// app/Services/ElevenLabsService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.elevenlabs.io/v1';
    private int $timeout = 30; // 30 second timeout

    public function __construct()
    {
        $this->apiKey = config('services.elevenlabs.api_key');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ElevenLabs API key not configured');
        }
    }

    /**
     * Generate speech from text
     *
     * @param string $text Text to convert (max 5000 chars)
     * @param string $voiceId ElevenLabs voice ID
     * @param string $modelId Model to use
     * @param array $voiceSettings Voice configuration
     * @return string Binary MP3 audio data
     * @throws ElevenLabsException
     */
    public function generateSpeech(
        string $text,
        string $voiceId,
        string $modelId,
        array $voiceSettings
    ): string {
        $url = "{$this->baseUrl}/text-to-speech/{$voiceId}";

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'audio/mpeg',
        ])
        ->timeout($this->timeout)
        ->post($url, [
            'text' => $text,
            'model_id' => $modelId,
            'voice_settings' => $voiceSettings,
        ]);

        // Handle response
        if ($response->successful()) {
            return $response->body(); // Binary MP3 data
        }

        // Handle errors
        $statusCode = $response->status();
        $body = $response->json();

        Log::error('ElevenLabs API error', [
            'status' => $statusCode,
            'body' => $body,
            'voice_id' => $voiceId,
            'model_id' => $modelId,
        ]);

        // Throw specific exceptions based on status code
        if ($statusCode === 429) {
            throw new ElevenLabsRateLimitException(
                'Rate limit exceeded',
                429
            );
        }

        if ($statusCode === 401) {
            throw new ElevenLabsAuthException(
                'Invalid API key or unauthorized',
                401
            );
        }

        throw new ElevenLabsException(
            $body['detail']['message'] ?? 'ElevenLabs API request failed',
            $statusCode
        );
    }

    /**
     * Get all available voices
     *
     * @return array
     * @throws ElevenLabsException
     */
    public function getVoices(): array
    {
        $url = "{$this->baseUrl}/voices";

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->get($url);

        if ($response->successful()) {
            return $response->json()['voices'] ?? [];
        }

        throw new ElevenLabsException(
            'Failed to fetch voices',
            $response->status()
        );
    }

    /**
     * Get specific voice details
     *
     * @param string $voiceId
     * @return array
     * @throws ElevenLabsException
     */
    public function getVoice(string $voiceId): array
    {
        $url = "{$this->baseUrl}/voices/{$voiceId}";

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
        ])
        ->timeout($this->timeout)
        ->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        if ($response->status() === 404) {
            throw new ElevenLabsNotFoundException(
                'Voice not found',
                404
            );
        }

        throw new ElevenLabsException(
            'Failed to fetch voice details',
            $response->status()
        );
    }
}
```

### Custom Exception Classes

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

// app/Exceptions/ElevenLabsNotFoundException.php
class ElevenLabsNotFoundException extends ElevenLabsException {}
```

---

## Configuration

### Environment Variables

Add to `.env` file:

```env
# ElevenLabs API Configuration
ELEVENLABS_API_KEY=your_api_key_here
ELEVENLABS_DEFAULT_VOICE_ID=56AoDkrOh6qfVPDXZ7Pt
ELEVENLABS_DEFAULT_MODEL=eleven_flash_v2_5
```

### Service Configuration

Add to `config/services.php`:

```php
return [
    // ... other services

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'default_voice_id' => env('ELEVENLABS_DEFAULT_VOICE_ID', '56AoDkrOh6qfVPDXZ7Pt'),
        'default_model' => env('ELEVENLABS_DEFAULT_MODEL', 'eleven_flash_v2_5'),
        'timeout' => env('ELEVENLABS_TIMEOUT', 30),
    ],
];
```

---

## Routes

Add to `routes/api.php`:

```php
use App\Http\Controllers\VoiceController;

// Protected by auth middleware
Route::middleware(['auth:sanctum'])->group(function () {
    // Text-to-speech
    Route::post('/voice/tts', [VoiceController::class, 'textToSpeech']);

    // Voice management (optional)
    Route::get('/voice/voices', [VoiceController::class, 'getVoices']);
    Route::get('/voice/{voiceId}', [VoiceController::class, 'getVoice']);
});
```

---

## Security Considerations

### 1. API Key Protection

**CRITICAL**: Never expose ElevenLabs API key to frontend

- Store API key in `.env` file
- Never commit `.env` to version control
- Use separate keys for dev, staging, and production
- Rotate keys periodically

### 2. Rate Limiting

Implement server-side rate limiting to prevent abuse:

```php
// In RouteServiceProvider or routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/voice/tts', [VoiceController::class, 'textToSpeech']);
});
```

Recommended limits:
- **Per user**: 60 requests per minute
- **Per IP**: 100 requests per minute
- **Global**: 1000 requests per minute

### 3. Input Validation

Always validate and sanitize input:

```php
// Remove potential script injections
$text = strip_tags($request->input('text'));

// Limit text length
if (strlen($text) > 5000) {
    return response()->json([
        'error' => [
            'code' => 'TEXT_TOO_LONG',
            'message' => 'Text exceeds 5000 character limit',
            'status' => 400,
        ]
    ], 400);
}
```

### 4. Authentication

Require authentication for all endpoints:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    // TTS endpoints
});
```

### 5. Request Logging

Log all TTS requests for monitoring and cost tracking:

```php
Log::info('TTS request', [
    'user_id' => auth()->id(),
    'text_length' => strlen($text),
    'voice_id' => $voiceId,
    'model_id' => $modelId,
    'timestamp' => now(),
]);
```

---

## Cost Management

### Track API Usage

Create a database table to track ElevenLabs usage:

```php
// Migration
Schema::create('tts_usage', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->integer('character_count');
    $table->string('voice_id');
    $table->string('model_id');
    $table->decimal('estimated_cost', 10, 4); // Credits used
    $table->timestamps();
});
```

### Calculate Costs

ElevenLabs pricing (approximate):
- Flash model: ~0.024 credits per character
- Multilingual model: ~0.030 credits per character

```php
// In VoiceController
private function logUsage(string $text, string $voiceId, string $modelId)
{
    $characterCount = strlen($text);
    $costPerChar = $modelId === 'eleven_flash_v2_5' ? 0.024 : 0.030;
    $estimatedCost = $characterCount * $costPerChar / 1000; // Credits

    TtsUsage::create([
        'user_id' => auth()->id(),
        'character_count' => $characterCount,
        'voice_id' => $voiceId,
        'model_id' => $modelId,
        'estimated_cost' => $estimatedCost,
    ]);
}
```

### Implement Usage Limits

Prevent excessive usage per user:

```php
// Check daily usage limit
$dailyUsage = TtsUsage::where('user_id', auth()->id())
    ->whereDate('created_at', today())
    ->sum('character_count');

if ($dailyUsage > 100000) { // 100k chars per day
    return response()->json([
        'error' => [
            'code' => 'DAILY_LIMIT_EXCEEDED',
            'message' => 'Daily usage limit exceeded',
            'status' => 429,
        ]
    ], 429);
}
```

---

## Error Handling Best Practices

### 1. Distinguish Error Types

Provide clear error messages that help frontend handle issues appropriately:

```php
// Network timeout
if ($exception instanceof ConnectionException) {
    return response()->json([
        'error' => [
            'code' => 'NETWORK_TIMEOUT',
            'message' => 'Request timed out. Please try again.',
            'status' => 504,
            'retryable' => true,
        ]
    ], 504);
}

// Rate limit
if ($exception instanceof ElevenLabsRateLimitException) {
    return response()->json([
        'error' => [
            'code' => 'RATE_LIMIT_EXCEEDED',
            'message' => 'Rate limit exceeded. Please try again later.',
            'status' => 429,
            'retryable' => true,
            'retry_after' => 60, // seconds
        ]
    ], 429);
}

// Invalid audio (rare)
if ($response->header('Content-Type') !== 'audio/mpeg') {
    return response()->json([
        'error' => [
            'code' => 'INVALID_AUDIO',
            'message' => 'Generated audio is invalid',
            'status' => 500,
            'retryable' => true,
        ]
    ], 500);
}
```

### 2. Log Errors Appropriately

```php
// Log different severity levels
Log::error('ElevenLabs API failure', [
    'error' => $exception->getMessage(),
    'status' => $exception->getStatusCode(),
    'user_id' => auth()->id(),
    'voice_id' => $voiceId,
    'text_length' => strlen($text),
]);

// For rate limits (info level since expected)
Log::info('Rate limit hit', [
    'user_id' => auth()->id(),
    'endpoint' => '/api/voice/tts',
]);
```

---

## Testing

### Unit Tests

```php
// tests/Unit/ElevenLabsServiceTest.php

namespace Tests\Unit;

use App\Services\ElevenLabsService;
use Tests\TestCase;

class ElevenLabsServiceTest extends TestCase
{
    public function test_generates_speech_successfully()
    {
        $service = new ElevenLabsService();

        $audio = $service->generateSpeech(
            'Test text',
            '56AoDkrOh6qfVPDXZ7Pt',
            'eleven_flash_v2_5',
            [
                'stability' => 0.5,
                'similarity_boost' => 0.75,
                'style' => 0.0,
                'use_speaker_boost' => true,
            ]
        );

        $this->assertNotEmpty($audio);
        $this->assertIsString($audio);
    }

    public function test_throws_exception_for_invalid_api_key()
    {
        config(['services.elevenlabs.api_key' => 'invalid']);

        $this->expectException(ElevenLabsAuthException::class);

        $service = new ElevenLabsService();
        $service->generateSpeech('Test', '56AoDkrOh6qfVPDXZ7Pt', 'eleven_flash_v2_5', []);
    }
}
```

### Integration Tests

```php
// tests/Feature/VoiceControllerTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class VoiceControllerTest extends TestCase
{
    public function test_generates_speech_for_authenticated_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/voice/tts', [
            'text' => 'Once upon a time',
            'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            'options' => [
                'model_id' => 'eleven_flash_v2_5',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_rejects_unauthenticated_requests()
    {
        $response = $this->postJson('/api/voice/tts', [
            'text' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_text_length()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/voice/tts', [
            'text' => str_repeat('a', 5001), // Exceeds limit
        ]);

        $response->assertStatus(422);
    }
}
```

### Manual Testing

Use curl to test endpoints:

```bash
# Test TTS endpoint
curl -X POST https://your-backend.com/api/voice/tts \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "text": "Once upon a time in a magical forest",
    "voiceId": "56AoDkrOh6qfVPDXZ7Pt",
    "options": {
      "model_id": "eleven_flash_v2_5"
    }
  }' \
  --output test-audio.mp3

# Verify the audio file
file test-audio.mp3  # Should show: MPEG ADTS, layer III
mpg123 test-audio.mp3  # Play audio (if mpg123 installed)
```

---

## Performance Optimization

### 1. Response Streaming

For large audio files, consider streaming response:

```php
return response()->stream(function () use ($audioData) {
    echo $audioData;
}, 200, [
    'Content-Type' => 'audio/mpeg',
    'Content-Length' => strlen($audioData),
]);
```

### 2. Caching (Optional)

Cache generated audio for identical requests:

```php
$cacheKey = md5($text . $voiceId . $modelId . json_encode($voiceSettings));

$audio = Cache::remember($cacheKey, 3600, function () use ($service, $params) {
    return $service->generateSpeech(...$params);
});
```

**Note**: Caching is primarily handled by frontend, but backend caching can reduce costs for popular content.

### 3. Queue Long Requests (Future Enhancement)

For very long texts, consider queuing:

```php
// Queue TTS generation for texts > 2000 chars
if (strlen($text) > 2000) {
    $job = GenerateSpeechJob::dispatch($text, $voiceId, $modelId, $voiceSettings);
    return response()->json(['job_id' => $job->id], 202);
}
```

---

## Monitoring & Observability

### 1. Metrics to Track

- Total TTS requests per day/hour
- Average response time
- Error rate by type (rate limit, timeout, auth, etc.)
- Character usage per user
- API cost per day
- Cache hit rate (if backend caching implemented)

### 2. Alerting

Set up alerts for:
- Error rate > 5%
- Daily cost > budget threshold
- API key approaching rate limit
- Service downtime > 2 minutes

### 3. Logging Best Practices

```php
// Structured logging for monitoring
Log::channel('tts')->info('TTS request completed', [
    'user_id' => auth()->id(),
    'text_length' => strlen($text),
    'voice_id' => $voiceId,
    'model_id' => $modelId,
    'response_time_ms' => $responseTime,
    'audio_size_bytes' => strlen($audioData),
    'timestamp' => now()->toIso8601String(),
]);
```

---

## Implementation Checklist

### Phase 1: Core Functionality
- [ ] Create `ElevenLabsService` class
- [ ] Create custom exception classes
- [ ] Create `VoiceController` with `textToSpeech` method
- [ ] Add routes to `routes/api.php`
- [ ] Add configuration to `config/services.php`
- [ ] Add environment variables to `.env`
- [ ] Implement request validation
- [ ] Implement error handling

### Phase 2: Security & Reliability
- [ ] Add authentication middleware
- [ ] Implement rate limiting
- [ ] Add input sanitization
- [ ] Set up request logging
- [ ] Configure CORS headers

### Phase 3: Cost Management
- [ ] Create `tts_usage` database table
- [ ] Implement usage tracking
- [ ] Add daily usage limits
- [ ] Set up cost monitoring

### Phase 4: Testing
- [ ] Write unit tests for `ElevenLabsService`
- [ ] Write integration tests for API endpoints
- [ ] Manual testing with curl/Postman
- [ ] Test error scenarios (rate limit, timeout, etc.)
- [ ] Load testing for performance

### Phase 5: Optional Enhancements
- [ ] Implement `getVoices` endpoint
- [ ] Implement `getVoice` endpoint
- [ ] Add backend caching
- [ ] Set up monitoring dashboards
- [ ] Configure alerting

---

## ElevenLabs API Reference

### Base URL
```
https://api.elevenlabs.io/v1
```

### Authentication
```
Header: xi-api-key: {your_api_key}
```

### Text-to-Speech Endpoint
```
POST /text-to-speech/{voice_id}
```

**Request:**
```json
{
  "text": "Text to convert",
  "model_id": "eleven_flash_v2_5",
  "voice_settings": {
    "stability": 0.5,
    "similarity_boost": 0.75,
    "style": 0.0,
    "use_speaker_boost": true
  }
}
```

**Response:** Binary MP3 audio data

**Models:**
- `eleven_multilingual_v2` - Best quality, 31 languages
- `eleven_flash_v2_5` - Fast, low-latency (recommended for MVP)
- `eleven_turbo_v2_5` - Fastest, real-time streaming

**Voices (StoryWriter):**
- `56AoDkrOh6qfVPDXZ7Pt` - Cassidy (default, young female)

### Official Documentation
- [ElevenLabs API Reference](https://elevenlabs.io/docs/api-reference/text-to-speech)
- [Voice Settings Guide](https://elevenlabs.io/docs/speech-synthesis/voice-settings)
- [Error Codes](https://elevenlabs.io/docs/api-reference/errors)

---

## Troubleshooting

### Common Issues

**"Invalid API key"**
- Verify `.env` contains correct `ELEVENLABS_API_KEY`
- Check API key in ElevenLabs dashboard
- Ensure key has TTS permissions

**"Rate limit exceeded"**
- Check ElevenLabs account quota
- Implement exponential backoff
- Upgrade ElevenLabs plan if needed

**"Request timeout"**
- Increase timeout in `ElevenLabsService` (default 30s)
- Check network connectivity
- Try smaller text chunks

**"Invalid audio response"**
- Verify response Content-Type is `audio/mpeg`
- Check ElevenLabs API status
- Try different voice or model

---

## Support & References

**Related Documentation:**
- [docs/vocal-narration.md](./vocal-narration.md) - Frontend implementation
- [docs/elevenlabs.md](./elevenlabs.md) - Frontend service details

**External Resources:**
- [ElevenLabs API Documentation](https://elevenlabs.io/docs)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)
- [Laravel Validation](https://laravel.com/docs/validation)

**Contact:**
- StoryWriter Development Team
- Backend Repository: [Link to Laravel repo]

---

**Last Updated:** February 15, 2026
**Status:** Specification Document (Implementation Pending)
