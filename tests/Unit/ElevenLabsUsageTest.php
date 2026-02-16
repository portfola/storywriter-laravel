<?php

namespace Tests\Unit;

use App\Models\ElevenLabsUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElevenLabsUsageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that logTtsRequest correctly calculates cost for flash model.
     */
    public function test_log_tts_request_calculates_flash_model_cost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $text = str_repeat('a', 50); // Exactly 50 characters
        $voiceId = '56AoDkrOh6qfVPDXZ7Pt';
        $modelId = 'eleven_flash_v2_5';

        $usage = ElevenLabsUsage::logTtsRequest($text, $voiceId, $modelId);

        $this->assertInstanceOf(ElevenLabsUsage::class, $usage);
        $this->assertEquals($user->id, $usage->user_id);
        $this->assertEquals('tts', $usage->service_type);
        $this->assertEquals(50, $usage->character_count);
        $this->assertEquals($voiceId, $usage->voice_id);
        $this->assertEquals($modelId, $usage->model_id);
        // 50 chars * $0.000024 = $0.0012
        $this->assertEquals('0.0012', $usage->estimated_cost);
    }

    /**
     * Test that logTtsRequest correctly calculates cost for multilingual model.
     */
    public function test_log_tts_request_calculates_multilingual_model_cost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $text = str_repeat('a', 1000); // 1000 characters
        $voiceId = '21m00Tcm4TlvDq8ikWAM';
        $modelId = 'eleven_multilingual_v2';

        $usage = ElevenLabsUsage::logTtsRequest($text, $voiceId, $modelId);

        $this->assertEquals(1000, $usage->character_count);
        $this->assertEquals($modelId, $usage->model_id);
        // 1000 chars * $0.000030 = $0.0300
        $this->assertEquals('0.0300', $usage->estimated_cost);
    }

    /**
     * Test that logTtsRequest correctly calculates cost for turbo model.
     */
    public function test_log_tts_request_calculates_turbo_model_cost(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $text = str_repeat('b', 500); // 500 characters
        $voiceId = 'AZnzlk1XvdvUeBnXmlld';
        $modelId = 'eleven_turbo_v2_5';

        $usage = ElevenLabsUsage::logTtsRequest($text, $voiceId, $modelId);

        $this->assertEquals(500, $usage->character_count);
        // 500 chars * $0.000024 = $0.0120
        $this->assertEquals('0.0120', $usage->estimated_cost);
    }

    /**
     * Test that logTtsRequest uses default pricing for unknown models.
     */
    public function test_log_tts_request_uses_default_cost_for_unknown_model(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $text = str_repeat('c', 100); // 100 characters
        $voiceId = 'test-voice-id';
        $modelId = 'unknown_model_v99';

        $usage = ElevenLabsUsage::logTtsRequest($text, $voiceId, $modelId);

        // Should use flash model pricing as default: 100 * $0.000024 = $0.0024
        $this->assertEquals('0.0024', $usage->estimated_cost);
    }

    /**
     * Test that logTtsRequest persists to database.
     */
    public function test_log_tts_request_persists_to_database(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $text = 'Test narration text';
        $voiceId = '56AoDkrOh6qfVPDXZ7Pt';
        $modelId = 'eleven_flash_v2_5';

        $usage = ElevenLabsUsage::logTtsRequest($text, $voiceId, $modelId);

        $this->assertDatabaseHas('elevenlabs_usage', [
            'id' => $usage->id,
            'user_id' => $user->id,
            'service_type' => 'tts',
            'voice_id' => $voiceId,
            'model_id' => $modelId,
        ]);
    }

    /**
     * Test that logConversationRequest correctly logs conversation usage.
     */
    public function test_log_conversation_request_creates_record(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $message = str_repeat('x', 30); // Exactly 30 characters
        $agentId = 'agent-123-abc';

        $usage = ElevenLabsUsage::logConversationRequest($message, $agentId);

        $this->assertInstanceOf(ElevenLabsUsage::class, $usage);
        $this->assertEquals($user->id, $usage->user_id);
        $this->assertEquals('conversation', $usage->service_type);
        $this->assertEquals(30, $usage->character_count);
        $this->assertEquals($agentId, $usage->voice_id); // Agent ID stored in voice_id field
        $this->assertEquals('conversation_agent', $usage->model_id);
        // 30 chars * $0.000024 = $0.0007 (rounded to 4 decimals: 0.0007)
        $this->assertEquals('0.0007', $usage->estimated_cost);
    }

    /**
     * Test belongsTo relationship with User model.
     */
    public function test_belongs_to_user_relationship(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $this->actingAs($user);

        $usage = ElevenLabsUsage::logTtsRequest('test', 'voice-id', 'eleven_flash_v2_5');

        $this->assertInstanceOf(User::class, $usage->user);
        $this->assertEquals('Test User', $usage->user->name);
        $this->assertEquals($user->id, $usage->user->id);
    }

    /**
     * Test hasMany relationship from User model.
     */
    public function test_has_many_relationship_from_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create multiple usage records
        ElevenLabsUsage::logTtsRequest('First request', 'voice-1', 'eleven_flash_v2_5');
        ElevenLabsUsage::logTtsRequest('Second request', 'voice-2', 'eleven_multilingual_v2');
        ElevenLabsUsage::logConversationRequest('Chat message', 'agent-1');

        $this->assertCount(3, $user->elevenLabsUsage);
        $this->assertInstanceOf(ElevenLabsUsage::class, $user->elevenLabsUsage->first());
    }

    /**
     * Test getTodayUsage returns correct character count for today only.
     */
    public function test_get_today_usage_counts_only_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create usage for today
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 500,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.012,
        ]);

        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 300,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.0072,
        ]);

        // Create usage for yesterday (should be excluded)
        $yesterday = ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.024,
        ]);
        $yesterday->created_at = now()->subDay();
        $yesterday->save();

        $todayUsage = ElevenLabsUsage::getTodayUsage($user->id);

        $this->assertEquals(800, $todayUsage); // Only today's 500 + 300
    }

    /**
     * Test getTodayUsage returns zero when no usage today.
     */
    public function test_get_today_usage_returns_zero_when_no_usage(): void
    {
        $user = User::factory()->create();

        $todayUsage = ElevenLabsUsage::getTodayUsage($user->id);

        $this->assertEquals(0, $todayUsage);
    }

    /**
     * Test getTodayUsage isolates by user ID.
     */
    public function test_get_today_usage_isolates_by_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        ElevenLabsUsage::logTtsRequest(str_repeat('a', 500), 'voice-1', 'eleven_flash_v2_5');

        $this->actingAs($user2);
        ElevenLabsUsage::logTtsRequest(str_repeat('b', 300), 'voice-2', 'eleven_flash_v2_5');

        $user1Usage = ElevenLabsUsage::getTodayUsage($user1->id);
        $user2Usage = ElevenLabsUsage::getTodayUsage($user2->id);

        $this->assertEquals(500, $user1Usage);
        $this->assertEquals(300, $user2Usage);
    }

    /**
     * Test getDailyLimit returns configured free tier limit.
     */
    public function test_get_daily_limit_returns_free_tier_limit(): void
    {
        config(['services.elevenlabs.daily_limit_free' => 10000]);

        $user = User::factory()->create();
        $limit = ElevenLabsUsage::getDailyLimit($user->id);

        $this->assertEquals(10000, $limit);
    }

    /**
     * Test wouldExceedLimit correctly identifies when limit would be exceeded.
     */
    public function test_would_exceed_limit_returns_true_when_exceeding(): void
    {
        config(['services.elevenlabs.daily_limit_free' => 10000]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // User has already used 9000 characters today
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 9000,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.216,
        ]);

        // Trying to use 1500 more would exceed (9000 + 1500 = 10500 > 10000)
        $wouldExceed = ElevenLabsUsage::wouldExceedLimit($user->id, 1500);

        $this->assertTrue($wouldExceed);
    }

    /**
     * Test wouldExceedLimit returns false when within limit.
     */
    public function test_would_exceed_limit_returns_false_when_within(): void
    {
        config(['services.elevenlabs.daily_limit_free' => 10000]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // User has used 8000 characters today
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 8000,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.192,
        ]);

        // Trying to use 1500 more is OK (8000 + 1500 = 9500 < 10000)
        $wouldExceed = ElevenLabsUsage::wouldExceedLimit($user->id, 1500);

        $this->assertFalse($wouldExceed);
    }

    /**
     * Test wouldExceedLimit returns true when exactly at limit.
     */
    public function test_would_exceed_limit_returns_true_when_at_exact_limit(): void
    {
        config(['services.elevenlabs.daily_limit_free' => 10000]);

        $user = User::factory()->create();
        $this->actingAs($user);

        // User has already used exactly 10000 characters
        ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 10000,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.24,
        ]);

        // Trying to use even 1 more character should exceed
        $wouldExceed = ElevenLabsUsage::wouldExceedLimit($user->id, 1);

        $this->assertTrue($wouldExceed);
    }

    /**
     * Test getTotalRequests counts requests for 'today' period.
     */
    public function test_get_total_requests_counts_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ElevenLabsUsage::logTtsRequest('test 1', 'voice-1', 'eleven_flash_v2_5');
        ElevenLabsUsage::logTtsRequest('test 2', 'voice-1', 'eleven_flash_v2_5');
        ElevenLabsUsage::logConversationRequest('chat', 'agent-1');

        $total = ElevenLabsUsage::getTotalRequests('today');

        $this->assertEquals(3, $total);
    }

    /**
     * Test getTotalRequests counts requests for 'week' period.
     */
    public function test_get_total_requests_counts_week(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create usage today
        ElevenLabsUsage::logTtsRequest('test today', 'voice-1', 'eleven_flash_v2_5');

        // Create usage 3 days ago
        $threeDaysAgo = ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 500,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.012,
        ]);
        $threeDaysAgo->created_at = now()->subDays(3);
        $threeDaysAgo->save();

        // Create usage 10 days ago (should be excluded)
        $tenDaysAgo = ElevenLabsUsage::create([
            'user_id' => $user->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => 'voice-1',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.024,
        ]);
        $tenDaysAgo->created_at = now()->subDays(10);
        $tenDaysAgo->save();

        $total = ElevenLabsUsage::getTotalRequests('week');

        $this->assertEquals(2, $total); // Only today and 3 days ago
    }

    /**
     * Test getTotalCharacters sums character counts for 'today'.
     */
    public function test_get_total_characters_sums_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ElevenLabsUsage::logTtsRequest(str_repeat('a', 500), 'voice-1', 'eleven_flash_v2_5');
        ElevenLabsUsage::logTtsRequest(str_repeat('b', 300), 'voice-1', 'eleven_flash_v2_5');

        $total = ElevenLabsUsage::getTotalCharacters('today');

        $this->assertEquals(800, $total);
    }

    /**
     * Test getTotalCost sums estimated costs for 'today'.
     */
    public function test_get_total_cost_sums_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1000 chars * $0.000024 = $0.024
        ElevenLabsUsage::logTtsRequest(str_repeat('a', 1000), 'voice-1', 'eleven_flash_v2_5');

        // 500 chars * $0.000030 = $0.015
        ElevenLabsUsage::logTtsRequest(str_repeat('b', 500), 'voice-2', 'eleven_multilingual_v2');

        $totalCost = ElevenLabsUsage::getTotalCost('today');

        // $0.024 + $0.015 = $0.039
        $this->assertEquals(0.039, $totalCost);
    }

    /**
     * Test getTopUsers returns users ordered by total cost.
     */
    public function test_get_top_users_returns_ordered_by_cost(): void
    {
        $user1 = User::factory()->create(['name' => 'Low Usage User']);
        $user2 = User::factory()->create(['name' => 'High Usage User']);

        // User 1: 500 chars * $0.000024 = $0.012
        $this->actingAs($user1);
        ElevenLabsUsage::logTtsRequest(str_repeat('a', 500), 'voice-1', 'eleven_flash_v2_5');

        // User 2: 5000 chars * $0.000024 = $0.12
        $this->actingAs($user2);
        ElevenLabsUsage::logTtsRequest(str_repeat('b', 5000), 'voice-2', 'eleven_flash_v2_5');

        $topUsers = ElevenLabsUsage::getTopUsers(10, 'month');

        $this->assertCount(2, $topUsers);
        $this->assertEquals($user2->id, $topUsers->first()->user_id); // Highest cost first
        $this->assertEquals(5000, $topUsers->first()->total_characters);
        $this->assertEquals(1, $topUsers->first()->request_count);
    }

    /**
     * Test getCostByModel groups by model and sums costs.
     */
    public function test_get_cost_by_model_groups_correctly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Flash model: 1000 chars * $0.000024 = $0.024
        ElevenLabsUsage::logTtsRequest(str_repeat('a', 1000), 'voice-1', 'eleven_flash_v2_5');

        // Multilingual: 500 chars * $0.000030 = $0.015
        ElevenLabsUsage::logTtsRequest(str_repeat('b', 500), 'voice-2', 'eleven_multilingual_v2');

        // Another flash: 500 chars * $0.000024 = $0.012
        ElevenLabsUsage::logTtsRequest(str_repeat('c', 500), 'voice-1', 'eleven_flash_v2_5');

        $costByModel = ElevenLabsUsage::getCostByModel('month');

        $this->assertCount(2, $costByModel);

        // Flash should be first (higher total cost: $0.024 + $0.012 = $0.036)
        $flash = $costByModel->firstWhere('model_id', 'eleven_flash_v2_5');
        $this->assertNotNull($flash);
        $this->assertEquals(1500, $flash->total_characters);
        $this->assertEquals(2, $flash->request_count);

        // Multilingual should be second (lower cost: $0.015)
        $multilingual = $costByModel->firstWhere('model_id', 'eleven_multilingual_v2');
        $this->assertNotNull($multilingual);
        $this->assertEquals(500, $multilingual->total_characters);
        $this->assertEquals(1, $multilingual->request_count);
    }

    /**
     * Test that character count is correctly stored as integer.
     */
    public function test_character_count_casts_to_integer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $usage = ElevenLabsUsage::logTtsRequest('test', 'voice-1', 'eleven_flash_v2_5');

        $this->assertIsInt($usage->character_count);
        $this->assertEquals(4, $usage->character_count);
    }

    /**
     * Test that estimated cost is correctly stored with 4 decimal places.
     */
    public function test_estimated_cost_casts_to_decimal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $usage = ElevenLabsUsage::logTtsRequest(str_repeat('a', 1000), 'voice-1', 'eleven_flash_v2_5');

        $this->assertIsString($usage->estimated_cost); // Decimal cast returns string
        $this->assertEquals('0.0240', $usage->estimated_cost);
    }
}
