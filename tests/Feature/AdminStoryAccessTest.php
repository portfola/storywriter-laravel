<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * What an admin may see of somebody else's story (Fizzy #96).
 *
 * The decision is that admins can read every story's content, for moderation and
 * support, and that the access is read-only. These assert the admin half only —
 * that a non-owner who is *not* an admin is turned away is #97's suite.
 */
class AdminStoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_another_users_story(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $story = Story::factory()->create(['user_id' => User::factory()]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $story));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Story::class));
    }

    public function test_admin_read_access_does_not_extend_to_changing_a_story(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $story = Story::factory()->create(['user_id' => User::factory()]);

        foreach (['update', 'delete', 'restore', 'forceDelete'] as $ability) {
            $this->assertTrue(
                Gate::forUser($admin)->denies($ability, $story),
                "Admins should not be granted [{$ability}] on another user's story."
            );
        }
    }

    public function test_admin_read_access_does_not_extend_to_filing_a_story_away(): void
    {
        // Saving used to authorize on 'view', so the moderation grant above
        // doubled as permission to put a child's storybook on an admin's own
        // bookshelf (#102). Reading is not filing.
        $admin = User::factory()->create(['is_admin' => true]);
        $story = Story::factory()->create(['user_id' => User::factory()]);

        $this->assertTrue(Gate::forUser($admin)->denies('save', $story));

        $this->actingAs($admin)
            ->postJson("/api/v1/stories/{$story->id}/save")
            ->assertForbidden();

        $this->assertFalse(
            $admin->fresh()->savedStories()->where('story_id', $story->id)->exists()
        );
    }

    public function test_being_an_admin_is_what_grants_the_access(): void
    {
        $stranger = User::factory()->create(['is_admin' => false]);
        $story = Story::factory()->create(['user_id' => User::factory()]);

        $this->assertTrue(Gate::forUser($stranger)->denies('view', $story));
    }

    public function test_dashboard_story_page_plays_the_narration_a_page_has(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $story = Story::factory()->create(['user_id' => User::factory()]);
        $page = StoryPage::factory()->create([
            'story_id' => $story->id,
            'audio_url' => 'stories/'.$story->id.'/pages/1/narration.mp3',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.stories.show', $story));

        $response->assertStatus(200);
        $response->assertSee('<audio', false);
        $response->assertSee(e($page->signed_audio_url), false);
    }

    public function test_dashboard_story_page_says_so_when_a_page_was_never_narrated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $story = Story::factory()->create(['user_id' => User::factory()]);
        StoryPage::factory()->create([
            'story_id' => $story->id,
            'audio_url' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.stories.show', $story));

        $response->assertStatus(200);
        $response->assertDontSee('<audio', false);
        $response->assertSee('No narration recorded for this page.');
    }
}
