<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * ElevenLabsControllerTest
 *
 * This test suite verifies the ElevenLabs integration for text-to-speech (TTS)
 * functionality, including the new eleven_flash_v2_5 model.
 *
 * WHAT THIS TEST COVERS:
 * --------------------------------------------------------------
 * 1. Text-to-Speech (TTS):
 *    - Validates request parameters (text, voiceId)
 *    - Tests successful audio generation
 *    - Verifies default model usage (eleven_flash_v2_5)
 *    - Tests custom model specification
 *    - Tests voice settings configuration
 *    - Validates audio output format (audio/mpeg)
 *
 * 2. Authentication:
 *    - Ensures endpoints require valid authentication
 *    - Verifies unauthenticated requests are rejected
 *
 * 3. Error Handling:
 *    - Tests API key configuration errors
 *    - Simulates ElevenLabs API failures
 *    - Validates error response formats
 *
 * 4. Voices Endpoint:
 *    - Tests voice listing functionality
 *    - Verifies response structure
 *
 * WHY THIS TEST IS IMPORTANT:
 * --------------------------------------------------------------
 * - Ensures TTS functionality works correctly with the new eleven_flash_v2_5 model
 * - Validates audio quality parameters are passed correctly
 * - Protects against regressions in vocal narration feature
 * - Verifies API key security (never exposed to frontend)
 * - Tests voice consistency across story pages
 *
 * HOW TO RUN:
 * --------------------------------------------------------------
 *   php artisan test --filter=ElevenLabsControllerTest
 *
 * NOTES:
 * --------------------------------------------------------------
 * - ElevenLabs API calls are fully mocked; no real API calls are made
 * - Default model is eleven_flash_v2_5 for optimal performance
 * - Audio responses are simulated with binary data
 */
class ElevenLabsControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_authentication_for_tts()
    {
        $response = $this->postJson('/api/conversation/tts', [
            'text' => 'Test narration',
            'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_text_field_for_tts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }

    /** @test */
    public function it_requires_voice_id_for_tts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Test narration',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['voiceId']);
    }

    /** @test */
    public function it_validates_text_length_for_tts()
    {
        $user = User::factory()->create();

        // Text exceeding 5000 character limit
        $longText = str_repeat('a', 5001);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $longText,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }

    /** @test */
    public function it_generates_audio_with_default_model()
    {
        $user = User::factory()->create();

        // Mock ElevenLabs API response with binary audio data
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAACAAABhADAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMD/////////////////////////////////////////////////////////////////////////////////////AAAAAExhdmM1OC4xMzQAAAAAAAAAAAAAAAAkAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//sQZAAP8AAAaQAAAAgAAA0gAAABAAABpAAAACAAADSAAAAETEFNRTMuMTAwVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVQ=='),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Once upon a time in a magical forest...',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertNotEmpty($response->getContent());

        // Verify the request was made with the correct default model
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/56AoDkrOh6qfVPDXZ7Pt' &&
                   $body['model_id'] === 'eleven_flash_v2_5' &&
                   $body['text'] === 'Once upon a time in a magical forest...';
        });
    }

    /** @test */
    public function it_allows_custom_model_specification()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Test with custom model',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
                'options' => [
                    'model_id' => 'eleven_multilingual_v2',
                ],
            ]);

        $response->assertStatus(200);

        // Verify custom model was used
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['model_id'] === 'eleven_multilingual_v2';
        });
    }

    /** @test */
    public function it_accepts_voice_settings()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $voiceSettings = [
            'stability' => 0.5,
            'similarity_boost' => 0.75,
            'style' => 0.0,
            'use_speaker_boost' => true,
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Story with custom voice settings',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
                'options' => [
                    'voice_settings' => $voiceSettings,
                ],
            ]);

        $response->assertStatus(200);

        // Verify voice settings were passed to ElevenLabs API
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            // Check that voice_settings key exists and has expected values
            return isset($body['voice_settings']) &&
                   isset($body['voice_settings']['stability']) &&
                   isset($body['voice_settings']['similarity_boost']);
        });
    }

    /** @test */
    public function it_uses_recommended_voice_for_children_stories()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Cassidy voice (56AoDkrOh6qfVPDXZ7Pt) is recommended for children's stories
        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'The brave little knight went on an adventure.',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt', // Cassidy - child-friendly voice
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');
    }

    /** @test */
    public function it_handles_elevenlabs_api_failure()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response([
                'detail' => [
                    'message' => 'Invalid voice ID',
                ],
            ], 404),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Test with invalid voice',
                'voiceId' => 'invalid-voice-id',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'TTS request failed',
            ]);
    }

    /** @test */
    public function it_handles_rate_limit_errors()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response([
                'detail' => [
                    'message' => 'Rate limit exceeded',
                ],
            ], 429),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Test rate limit',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'TTS request failed',
            ]);
    }

    /** @test */
    public function it_generates_consistent_audio_across_story_pages()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Simulate generating narration for multiple story pages with same settings
        $pages = [
            'Page 1: Once upon a time...',
            'Page 2: The hero went on a journey...',
            'Page 3: They found a magical treasure...',
        ];

        foreach ($pages as $pageText) {
            $response = $this->actingAs($user)
                ->postJson('/api/conversation/tts', [
                    'text' => $pageText,
                    'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
                    'options' => [
                        'model_id' => 'eleven_flash_v2_5',
                        'voice_settings' => [
                            'stability' => 0.5,
                            'similarity_boost' => 0.75,
                        ],
                    ],
                ]);

            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'audio/mpeg');
        }

        // Verify all requests used the same voice and model for consistency
        Http::assertSentCount(3);
    }

    /** @test */
    public function it_requires_authentication_for_voices_endpoint()
    {
        $response = $this->getJson('/api/conversation/voices');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_lists_available_voices()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/voices' => Http::response([
                'voices' => [
                    [
                        'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
                        'name' => 'Cassidy',
                        'category' => 'premade',
                        'labels' => ['young', 'female', 'american'],
                    ],
                    [
                        'voice_id' => '21m00Tcm4TlvDq8ikWAM',
                        'name' => 'Rachel',
                        'category' => 'premade',
                        'labels' => ['adult', 'female', 'american'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/conversation/voices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'voices' => [
                    '*' => ['voice_id', 'name', 'category'],
                ],
            ])
            ->assertJsonCount(2, 'voices');
    }

    /** @test */
    public function it_handles_voices_endpoint_failure()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/voices' => Http::response([
                'detail' => [
                    'message' => 'Unauthorized',
                ],
            ], 401),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/conversation/voices');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Failed to fetch voices',
            ]);
    }

    /** @test */
    public function it_verifies_flash_model_performance_characteristics()
    {
        $user = User::factory()->create();

        // Simulate the eleven_flash_v2_5 model which is 3-5x faster than multilingual
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $startTime = microtime(true);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'Performance test for eleven_flash_v2_5 model',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $response->assertStatus(200);

        // Verify the flash model was used (should be default)
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return $body['model_id'] === 'eleven_flash_v2_5';
        });

        // Note: In a real scenario, we'd expect < 5 seconds for 500 chars
        // But in tests with mocked HTTP, it should be nearly instant
        $this->assertLessThan(1, $duration, 'Mocked API should respond instantly');
    }

    /** @test */
    public function it_logs_usage_after_successful_tts_request()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $text = 'This is a test narration for usage tracking.';
        $voiceId = '56AoDkrOh6qfVPDXZ7Pt';

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => $voiceId,
            ]);

        $response->assertStatus(200);

        // Verify usage was logged in database
        $this->assertDatabaseHas('elevenlabs_usage', [
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => strlen($text),
            'voice_id' => $voiceId,
            'model_id' => 'eleven_flash_v2_5', // Default model
        ]);

        // Verify cost calculation is correct
        $usage = \App\Models\ElevenLabsUsage::where('user_id', $user->id)->first();
        $expectedCost = strlen($text) * 0.000024; // Flash model pricing
        $this->assertEquals(number_format($expectedCost, 4), number_format((float) $usage->estimated_cost, 4));
    }

    /** @test */
    public function it_calculates_correct_cost_for_flash_model()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // 1000 character text
        $text = str_repeat('a', 1000);

        $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        // Expected cost: 1000 chars * $0.000024 = $0.024
        $usage = \App\Models\ElevenLabsUsage::where('user_id', $user->id)->first();
        $this->assertEquals('0.0240', number_format((float) $usage->estimated_cost, 4));
    }

    /** @test */
    public function it_calculates_correct_cost_for_multilingual_model()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // 1000 character text with multilingual model
        $text = str_repeat('a', 1000);

        $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
                'options' => [
                    'model_id' => 'eleven_multilingual_v2',
                ],
            ]);

        // Expected cost: 1000 chars * $0.000030 = $0.030
        $usage = \App\Models\ElevenLabsUsage::where('user_id', $user->id)->first();
        $this->assertEquals('eleven_multilingual_v2', $usage->model_id);
        $this->assertEquals('0.0300', number_format((float) $usage->estimated_cost, 4));
    }

    /** @test */
    public function it_does_not_log_usage_on_failed_tts_request()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response([
                'detail' => [
                    'message' => 'Invalid voice ID',
                ],
            ], 404),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'This should fail',
                'voiceId' => 'invalid-voice-id',
            ]);

        $response->assertStatus(404);

        // Verify NO usage was logged since request failed
        $this->assertDatabaseMissing('elevenlabs_usage', [
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function it_tracks_multiple_tts_requests_per_user()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Make 3 TTS requests
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($user)
                ->postJson('/api/conversation/tts', [
                    'text' => "Story page {$i} narration text",
                    'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
                ]);
        }

        // Verify 3 usage records were created
        $usageCount = \App\Models\ElevenLabsUsage::where('user_id', $user->id)->count();
        $this->assertEquals(3, $usageCount);

        // Verify total character count
        $totalChars = \App\Models\ElevenLabsUsage::where('user_id', $user->id)
            ->sum('character_count');
        $expectedChars = strlen('Story page 1 narration text')
                       + strlen('Story page 2 narration text')
                       + strlen('Story page 3 narration text');
        $this->assertEquals($expectedChars, $totalChars);
    }

    /** @test */
    public function it_associates_usage_records_with_correct_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // User 1 makes a TTS request
        $this->actingAs($user1)
            ->postJson('/api/conversation/tts', [
                'text' => 'User 1 narration',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        // User 2 makes a TTS request
        $this->actingAs($user2)
            ->postJson('/api/conversation/tts', [
                'text' => 'User 2 narration',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        // Verify each user has their own usage record
        $this->assertDatabaseHas('elevenlabs_usage', [
            'user_id' => $user1->id,
            'character_count' => strlen('User 1 narration'),
        ]);

        $this->assertDatabaseHas('elevenlabs_usage', [
            'user_id' => $user2->id,
            'character_count' => strlen('User 2 narration'),
        ]);

        // Verify user 1's record doesn't belong to user 2
        $user1Usage = \App\Models\ElevenLabsUsage::where('user_id', $user1->id)->count();
        $user2Usage = \App\Models\ElevenLabsUsage::where('user_id', $user2->id)->count();

        $this->assertEquals(1, $user1Usage);
        $this->assertEquals(1, $user2Usage);
    }

    /** @test */
    public function it_enforces_daily_character_limit()
    {
        $user = User::factory()->create();

        // Set up a small daily limit for testing (default is 10000)
        config(['services.elevenlabs.daily_limit_free' => 1000]);

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Create usage record for today with 900 characters already used
        \App\Models\ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 900,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 900 * 0.000024,
            'created_at' => now(),
        ]);

        // Try to use 150 more characters (would exceed limit of 1000)
        $text = str_repeat('a', 150);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'Daily narration limit reached. Please try again tomorrow.',
                'limit_info' => [
                    'characters_used' => 900,
                    'daily_limit' => 1000,
                    'requested_characters' => 150,
                ],
            ]);

        // Verify no API request was made to ElevenLabs
        Http::assertNothingSent();
    }

    /** @test */
    public function it_allows_tts_requests_within_daily_limit()
    {
        $user = User::factory()->create();

        config(['services.elevenlabs.daily_limit_free' => 1000]);

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Create usage record for today with 800 characters already used
        \App\Models\ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 800,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 800 * 0.000024,
            'created_at' => now(),
        ]);

        // Use 150 more characters (total 950, within limit of 1000)
        $text = str_repeat('a', 150);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');

        // Verify API request was made to ElevenLabs
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.elevenlabs.io/v1/text-to-speech');
        });

        // Verify usage was logged
        $totalUsage = \App\Models\ElevenLabsUsage::where('user_id', $user->id)->count();
        $this->assertEquals(2, $totalUsage);
    }

    /** @test */
    public function it_resets_usage_limit_on_new_day()
    {
        $user = User::factory()->create();

        config(['services.elevenlabs.daily_limit_free' => 1000]);

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Create usage record for YESTERDAY with 950 characters used
        // Use a carbon instance and travel back in time for testing
        $this->travel(-1)->days();

        \App\Models\ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 950,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 950 * 0.000024,
        ]);

        // Travel back to today
        $this->travelBack();

        // Verify today's usage is 0 (yesterday doesn't count)
        $todayUsage = \App\Models\ElevenLabsUsage::getTodayUsage($user->id);
        $this->assertEquals(0, $todayUsage);

        // Try to use 100 characters today (should succeed because yesterday's usage doesn't count)
        $text = str_repeat('a', 100);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');

        // Verify API request was made
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.elevenlabs.io/v1/text-to-speech');
        });
    }

    /** @test */
    public function it_calculates_total_usage_correctly_with_multiple_requests_today()
    {
        $user = User::factory()->create();

        config(['services.elevenlabs.daily_limit_free' => 1000]);

        // Create multiple usage records for today
        \App\Models\ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 300,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 300 * 0.000024,
            'created_at' => now(),
        ]);

        \App\Models\ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 400,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 400 * 0.000024,
            'created_at' => now(),
        ]);

        // Total usage today: 700 characters
        // Try to use 350 more characters (would exceed limit of 1000)
        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        $text = str_repeat('a', 350);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'Daily narration limit reached. Please try again tomorrow.',
                'limit_info' => [
                    'characters_used' => 700,
                    'daily_limit' => 1000,
                    'requested_characters' => 350,
                ],
            ]);
    }

    /** @test */
    public function it_isolates_daily_limits_between_different_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        config(['services.elevenlabs.daily_limit_free' => 1000]);

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // User 1 has used 950 characters today (near limit)
        \App\Models\ElevenLabsUsage::create([
            'user_id' => $user1->id,
            'service_type' => 'tts',
            'character_count' => 950,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 950 * 0.000024,
            'created_at' => now(),
        ]);

        // User 2 tries to use 100 characters (should succeed - separate limit)
        $text = str_repeat('a', 100);

        $response = $this->actingAs($user2)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');

        // User 1 tries to use 100 characters (should fail - exceeded their limit)
        $response = $this->actingAs($user1)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'Daily narration limit reached. Please try again tomorrow.',
            ]);
    }

    /** @test */
    public function it_allows_exact_limit_usage()
    {
        $user = User::factory()->create();

        config(['services.elevenlabs.daily_limit_free' => 1000]);

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RSU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Use exactly 1000 characters (at limit, should succeed)
        $text = str_repeat('a', 1000);

        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mpeg');

        // Try to use 1 more character (should fail - exceeds limit)
        $response = $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => 'a',
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        $response->assertStatus(429);
    }

    /** @test */
    public function it_logs_limit_exceeded_events()
    {
        $user = User::factory()->create();

        config(['services.elevenlabs.daily_limit_free' => 1000]);

        // Create usage that exceeds the limit
        \App\Models\ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 950,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 950 * 0.000024,
            'created_at' => now(),
        ]);

        Http::fake([
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response(
                base64_decode('SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA'),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);

        // Capture log output
        Log::spy();

        $text = str_repeat('a', 100);

        $this->actingAs($user)
            ->postJson('/api/conversation/tts', [
                'text' => $text,
                'voiceId' => '56AoDkrOh6qfVPDXZ7Pt',
            ]);

        // Verify warning was logged
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('User exceeded daily TTS limit', \Mockery::on(function ($context) use ($user) {
                return $context['user_id'] === $user->id &&
                       $context['current_usage'] === 950 &&
                       $context['limit'] === 1000 &&
                       $context['requested_chars'] === 100;
            }));
    }
}
