<?php

namespace Tests\Feature;

use App\Models\ElevenLabsUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardElevenLabsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that admin can access ElevenLabs usage dashboard
     */
    public function test_admin_can_access_elevenlabs_usage_dashboard(): void
    {
        // Create admin user
        $admin = User::factory()->create(['is_admin' => true]);

        // Create some sample usage data
        ElevenLabsUsage::create([
            'user_id' => $admin->id,
            'service_type' => 'tts',
            'character_count' => 500,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.012,
        ]);

        // Access dashboard
        $response = $this->actingAs($admin)
            ->get('/dashboard/elevenlabs-usage');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.elevenlabs-usage');
        $response->assertViewHas('data');

        // Verify data structure
        $data = $response->viewData('data');
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('top_users', $data);
        $this->assertArrayHasKey('cost_by_model', $data);

        // Verify stats structure
        $this->assertArrayHasKey('today', $data['stats']);
        $this->assertArrayHasKey('week', $data['stats']);
        $this->assertArrayHasKey('month', $data['stats']);
    }

    /**
     * Test that non-admin users cannot access ElevenLabs usage dashboard
     */
    public function test_non_admin_cannot_access_elevenlabs_usage_dashboard(): void
    {
        // Create regular user (non-admin)
        $user = User::factory()->create(['is_admin' => false]);

        // Attempt to access dashboard
        $response = $this->actingAs($user)
            ->get('/dashboard/elevenlabs-usage');

        $response->assertStatus(403);
    }

    /**
     * Test that unauthenticated users are redirected
     */
    public function test_unauthenticated_users_are_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard/elevenlabs-usage');

        $response->assertRedirect('/login');
    }

    /**
     * Test dashboard displays correct usage statistics
     */
    public function test_dashboard_displays_correct_usage_statistics(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Create multiple usage records
        ElevenLabsUsage::create([
            'user_id' => $admin->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.024,
            'created_at' => now(),
        ]);

        ElevenLabsUsage::create([
            'user_id' => $admin->id,
            'service_type' => 'conversation',
            'character_count' => 500,
            'voice_id' => 'agent-123',
            'model_id' => 'conversation_agent',
            'estimated_cost' => 0.012,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get('/dashboard/elevenlabs-usage');

        $response->assertStatus(200);

        $data = $response->viewData('data');

        // Verify today's stats
        $this->assertEquals(2, $data['stats']['today']['requests']);
        $this->assertEquals(1500, $data['stats']['today']['characters']);
        $this->assertEqualsWithDelta(0.036, $data['stats']['today']['cost'], 0.0001);
    }

    /**
     * Test dashboard shows top users by usage
     */
    public function test_dashboard_shows_top_users_by_usage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create usage for multiple users
        ElevenLabsUsage::create([
            'user_id' => $user1->id,
            'service_type' => 'tts',
            'character_count' => 5000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.12,
        ]);

        ElevenLabsUsage::create([
            'user_id' => $user2->id,
            'service_type' => 'tts',
            'character_count' => 3000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.072,
        ]);

        $response = $this->actingAs($admin)
            ->get('/dashboard/elevenlabs-usage');

        $response->assertStatus(200);

        $data = $response->viewData('data');
        $topUsers = $data['top_users']->toArray();

        // Verify user1 is ranked higher than user2
        $this->assertEquals($user1->id, $topUsers[0]['user_id']);
        $this->assertEquals($user2->id, $topUsers[1]['user_id']);
    }

    /**
     * Test dashboard shows cost breakdown by model
     */
    public function test_dashboard_shows_cost_breakdown_by_model(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Create usage for different models
        ElevenLabsUsage::create([
            'user_id' => $admin->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_flash_v2_5',
            'estimated_cost' => 0.024,
        ]);

        ElevenLabsUsage::create([
            'user_id' => $admin->id,
            'service_type' => 'tts',
            'character_count' => 1000,
            'voice_id' => '56AoDkrOh6qfVPDXZ7Pt',
            'model_id' => 'eleven_multilingual_v2',
            'estimated_cost' => 0.030,
        ]);

        $response = $this->actingAs($admin)
            ->get('/dashboard/elevenlabs-usage');

        $response->assertStatus(200);

        $data = $response->viewData('data');
        $costByModel = $data['cost_by_model']->keyBy('model_id');

        // Verify both models are present with correct costs
        $this->assertEquals(0.024, $costByModel['eleven_flash_v2_5']['total_cost']);
        $this->assertEquals(0.030, $costByModel['eleven_multilingual_v2']['total_cost']);
    }
}
