<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The thumbnail column on the dashboard's story list (Fizzy #103).
 *
 * The view used to put the story's name in the img src, so every thumbnail on
 * the page 404'd. What belongs there is page 1's illustration, signed, which is
 * the same cover the bookshelf shows.
 */
class DashboardStoryThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMediaDisk(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
    }

    public function test_the_story_list_shows_the_cover_illustration_not_the_story_name(): void
    {
        $this->fakeMediaDisk();

        $admin = User::factory()->create(['is_admin' => true]);
        $story = Story::factory()->create([
            'user_id' => $admin->id,
            'name' => 'The Dragon Who Lost A Sock',
        ]);

        $imagePath = "stories/{$story->id}/pages/1/image.png";
        Storage::disk('public')->put($imagePath, 'png-bytes');

        $cover = StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => $imagePath,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard')->assertOk();

        $response->assertSee('src="'.e($cover->signed_image_url).'"', false);

        // The name belongs in alt text, and must never be the src again.
        $response->assertDontSee('src="'.e($story->name).'"', false);
        $response->assertSee('alt="'.e($story->name).'"', false);
    }

    public function test_a_story_with_no_illustration_yet_gets_a_placeholder(): void
    {
        $this->fakeMediaDisk();

        $admin = User::factory()->create(['is_admin' => true]);
        $story = Story::factory()->create(['user_id' => $admin->id]);

        StoryPage::factory()->create([
            'story_id' => $story->id,
            'page_number' => 1,
            'image_url' => null,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No cover');
    }
}
