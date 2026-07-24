<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageImageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Point media storage at a throwaway disk so tests never write real files.
     */
    private function fakeMediaDisk(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
    }

    /** @test */
    public function it_generates_image_for_page_with_illustration_prompt_but_no_image_url()
    {
        $this->fakeMediaDisk();

        Http::fake([
            'api.together.xyz/*' => Http::response([
                'data' => [['url' => 'https://example.com/generated-image.png']],
            ]),
            'example.com/*' => Http::response('fake-png-bytes'),
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
            ->postJson("/api/v1/stories/{$story->id}/pages/2/image");

        // The response points at our stored copy, not Together's expiring URL.
        $storedPath = "stories/{$story->id}/pages/2/image.png";

        $response->assertOk();
        $this->assertStringContainsString($storedPath, $response->json('data.imageUrl'));
        $this->assertStringNotContainsString('generated-image.png', $response->json('data.imageUrl'));

        Storage::disk('public')->assertExists($storedPath);

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
            ->postJson("/api/v1/stories/{$story->id}/pages/1/image");

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
            ->postJson("/api/v1/stories/{$story->id}/pages/99/image");

        $response->assertNotFound();
    }

    /** @test */
    public function it_persists_stored_image_url_to_database_after_generation()
    {
        $this->fakeMediaDisk();

        $imageUrl = 'https://example.com/persisted-image.png';

        Http::fake([
            'api.together.xyz/*' => Http::response([
                'data' => [['url' => $imageUrl]],
            ]),
            'example.com/*' => Http::response('fake-png-bytes'),
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
            ->postJson("/api/v1/stories/{$story->id}/pages/3/image")
            ->assertOk();

        // What lands in the DB is our stored copy, so the illustration survives
        // Together's URL expiring.
        $page->refresh();

        $this->assertStringContainsString("stories/{$story->id}/pages/3/image.png", $page->image_url);
        $this->assertStringNotContainsString('persisted-image.png', $page->image_url);

        Storage::disk('public')->assertExists("stories/{$story->id}/pages/3/image.png");
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
            ->postJson("/api/v1/stories/{$story->id}/pages/1/image");

        $response->assertForbidden();
    }
}
