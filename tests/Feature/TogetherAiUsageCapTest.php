<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\TogetherAiUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TogetherAiUsageCapTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_logs_usage_when_a_story_is_generated()
    {
        $user = User::factory()->create();

        Http::fake([
            'api.together.xyz/*' => Http::response([
                'choices' => [['message' => ['content' => "Title: A Tale\n\nOnce upon a time..."]]],
            ], 200),
        ]);

        $this->actingAs($user)->postJson('/api/v1/stories/generate', [
            'transcript' => 'Tell me a story.',
        ])->assertStatus(200);

        $this->assertDatabaseHas('together_ai_usage', [
            'user_id' => $user->id,
            'service_type' => TogetherAiUsage::SERVICE_STORY,
        ]);
    }

    /** @test */
    public function it_blocks_story_generation_once_the_daily_limit_is_reached()
    {
        config(['services.together.daily_story_limit_free' => 2]);
        $user = User::factory()->create();

        // Pre-fill the day's quota.
        TogetherAiUsage::factory()->count(2)->create([
            'user_id' => $user->id,
            'service_type' => TogetherAiUsage::SERVICE_STORY,
        ]);

        Http::fake();

        $response = $this->actingAs($user)->postJson('/api/v1/stories/generate', [
            'transcript' => 'Tell me a story.',
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('limit_info.daily_limit', 2);

        // No Together AI call should have been made.
        Http::assertNothingSent();
    }

    /** @test */
    public function it_blocks_page_image_generation_once_the_daily_limit_is_reached()
    {
        config(['services.together.daily_image_limit_free' => 1]);
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => null,
        ]);

        TogetherAiUsage::factory()->create([
            'user_id' => $user->id,
            'service_type' => TogetherAiUsage::SERVICE_IMAGE,
        ]);

        Http::fake();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/stories/{$story->id}/pages/{$page->page_number}/image");

        $response->assertStatus(429);
        Http::assertNothingSent();
    }
}
