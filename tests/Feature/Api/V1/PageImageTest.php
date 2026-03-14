<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageImageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_image_for_page_with_illustration_prompt_but_no_image_url()
    {
        Http::fake([
            'api.together.xyz/*' => Http::response([
                'data' => [['url' => 'https://example.com/generated-image.png']],
            ]),
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'user_id' => $user->id,
            'characters_description' => 'A brave knight with silver armor.',
        ]);
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 2,
            'illustration_prompt' => 'A knight riding a dragon over a mountain',
            'image_url' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/stories/{$story->id}/pages/2/image");

        $response->assertOk()
            ->assertJsonPath('data.imageUrl', 'https://example.com/generated-image.png');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.together.xyz/v1/images/generations');
        });
    }

    /** @test */
    public function it_returns_existing_image_url_without_regenerating()
    {
        Http::fake();

        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => 'https://example.com/existing-image.png',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/stories/{$story->id}/pages/1/image");

        $response->assertOk()
            ->assertJsonPath('data.imageUrl', 'https://example.com/existing-image.png');

        Http::assertNothingSent();
    }

    /** @test */
    public function it_returns_404_for_nonexistent_page_number()
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/stories/{$story->id}/pages/99/image");

        $response->assertNotFound();
    }

    /** @test */
    public function it_persists_image_url_to_database_after_generation()
    {
        $imageUrl = 'https://example.com/persisted-image.png';

        Http::fake([
            'api.together.xyz/*' => Http::response([
                'data' => [['url' => $imageUrl]],
            ]),
        ]);

        $user = User::factory()->create();
        $story = Story::factory()->create([
            'user_id' => $user->id,
            'characters_description' => 'A fluffy cat with blue eyes.',
        ]);
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 3,
            'illustration_prompt' => 'A cat sleeping on a windowsill',
            'image_url' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/stories/{$story->id}/pages/3/image")
            ->assertOk();

        $this->assertDatabaseHas('story_pages', [
            'id' => $page->id,
            'image_url' => $imageUrl,
        ]);
    }

    /** @test */
    public function it_returns_403_for_another_users_story()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $owner->id]);
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => null,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/stories/{$story->id}/pages/1/image");

        $response->assertForbidden();
    }
}
