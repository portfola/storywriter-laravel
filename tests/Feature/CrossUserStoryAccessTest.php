<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One user must not be able to reach another user's story (Fizzy #97).
 *
 * Every route here was open at some point: the API ones because StoryPolicy was
 * written but never called (#94), the dashboard one because its admin check was
 * missing and a second unguarded route reached the same page (#95). Nothing in
 * the suite asserted any of it, which is how the holes got there in the first
 * place.
 *
 * Two rules these follow, both learned from how the holes hid:
 *
 * A status code on its own is not enough. A 200 with an empty payload, or a
 * redirect to the login page, both pass a status-only assertion while still
 * being the wrong answer -- so every case also asserts the victim's page text is
 * nowhere in the response body, and that nothing about the story changed.
 *
 * The stranger here is a plain, verified, non-admin user. Admins deliberately
 * *can* read any story (#96) -- that half lives in AdminStoryAccessTest.
 */
class CrossUserStoryAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $victim;

    private User $stranger;

    private Story $story;

    private StoryPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->victim = User::factory()->create();
        $this->stranger = User::factory()->create(['is_admin' => false]);

        // Distinctive text so "did any of this leak?" is a substring search
        // rather than a guess about the response shape.
        $this->story = Story::factory()->for($this->victim)->create([
            'name' => 'The Secret Lighthouse',
            'slug' => 'the-secret-lighthouse',
            'body' => 'Private story body that only the owner should ever read.',
        ]);

        $this->page = StoryPage::factory()->for($this->story)->create([
            'page_number' => 1,
            'content' => 'Marlow the puffin found a brass key under the third stair.',
        ]);
    }

    public function test_stranger_cannot_read_another_users_story(): void
    {
        $response = $this->actingAs($this->stranger)
            ->getJson("/api/v1/stories/{$this->story->id}");

        $response->assertForbidden();
        $this->assertLeaksNothing($response->getContent());
    }

    public function test_stranger_cannot_update_another_users_story(): void
    {
        $response = $this->actingAs($this->stranger)
            ->putJson("/api/v1/stories/{$this->story->id}", [
                'name' => 'Hijacked',
                'body' => 'Overwritten by somebody else.',
            ]);

        $response->assertForbidden();
        $this->assertLeaksNothing($response->getContent());

        // The refusal has to be a refusal, not a 403 rendered after the write.
        $this->assertSame('The Secret Lighthouse', $this->story->fresh()->name);
        $this->assertSame(
            'Private story body that only the owner should ever read.',
            $this->story->fresh()->body
        );
    }

    public function test_stranger_cannot_delete_another_users_story(): void
    {
        $response = $this->actingAs($this->stranger)
            ->deleteJson("/api/v1/stories/{$this->story->id}");

        $response->assertForbidden();
        $this->assertLeaksNothing($response->getContent());

        $this->assertDatabaseHas('stories', ['id' => $this->story->id]);
        $this->assertNotNull($this->story->fresh());
    }

    public function test_stranger_cannot_save_another_users_story_to_their_library(): void
    {
        $response = $this->actingAs($this->stranger)
            ->postJson("/api/v1/stories/{$this->story->id}/save");

        $response->assertForbidden();
        $this->assertLeaksNothing($response->getContent());

        // A save that 403s but still writes the pivot row would put the story on
        // the stranger's bookshelf anyway, where the listing endpoint hands it
        // over without ever consulting the policy again.
        $this->assertFalse(
            $this->stranger->fresh()->savedStories()->where('story_id', $this->story->id)->exists()
        );

        $saved = $this->actingAs($this->stranger)->getJson('/api/v1/stories/saved');
        $saved->assertOk();
        $saved->assertJsonCount(0, 'data');
        $this->assertLeaksNothing($saved->getContent());
    }

    public function test_stranger_cannot_save_a_conversation_id_onto_another_users_story(): void
    {
        $response = $this->actingAs($this->stranger)
            ->postJson("/api/v1/stories/{$this->story->id}/save", [
                'elevenlabs_conversation_id' => 'conv_from_a_stranger',
            ]);

        $response->assertForbidden();
        $this->assertNull($this->story->fresh()->elevenlabs_conversation_id);
    }

    public function test_unsaving_another_users_story_leaves_their_bookshelf_alone(): void
    {
        // Unsave is open to anybody (#102): it detaches the caller's own pivot
        // row, which is the only way somebody can clear an entry saved onto
        // their shelf before #94 closed the hole. The property worth pinning is
        // therefore not a 403 -- it is that "anybody" only ever reaches their
        // own row, and gets none of the story back.
        $this->victim->savedStories()->attach($this->story->id);
        $this->stranger->savedStories()->attach($this->story->id);

        $response = $this->actingAs($this->stranger)
            ->deleteJson("/api/v1/stories/{$this->story->id}/unsave");

        $response->assertNoContent();
        $this->assertLeaksNothing($response->getContent());

        $this->assertFalse(
            $this->stranger->fresh()->savedStories()->where('story_id', $this->story->id)->exists()
        );
        $this->assertTrue(
            $this->victim->fresh()->savedStories()->where('story_id', $this->story->id)->exists()
        );
    }

    public function test_story_listing_does_not_include_another_users_story(): void
    {
        $response = $this->actingAs($this->stranger)->getJson('/api/v1/stories');

        // 200 is right here -- the stranger has a bookshelf, it is just empty.
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $this->assertLeaksNothing($response->getContent());
    }

    public function test_saved_listing_does_not_read_out_a_stray_row_on_the_strangers_shelf(): void
    {
        // The row is attached by hand on purpose (Fizzy #104). Saving is
        // owner-only now, so nothing new can land here -- but before #94 closed
        // the cross-user hole it could, and those rows are still sitting in the
        // pivot table. The listing endpoint used to hand them straight back.
        $this->stranger->savedStories()->attach($this->story->id);

        $response = $this->actingAs($this->stranger)->getJson('/api/v1/stories/saved');

        // 200 with nothing in it: the stranger has a shelf, and the one thing on
        // it is not theirs to read.
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $this->assertLeaksNothing($response->getContent());
    }

    public function test_the_saved_listing_still_returns_your_own_saved_stories(): void
    {
        // The filter has to hide the stray row without emptying the shelf.
        $own = Story::factory()->for($this->stranger)->create(['name' => 'My Own Story']);
        $this->stranger->savedStories()->attach([$own->id, $this->story->id]);

        $response = $this->actingAs($this->stranger)->getJson('/api/v1/stories/saved');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $own->id);
        $this->assertLeaksNothing($response->getContent());
    }

    public function test_stranger_cannot_open_another_users_story_in_the_dashboard(): void
    {
        $response = $this->actingAs($this->stranger)
            ->get(route('dashboard.stories.show', $this->story));

        $response->assertForbidden();
        $this->assertLeaksNothing($response->getContent());
    }

    public function test_the_dashboard_story_page_is_admin_only_even_for_your_own_story(): void
    {
        // The page is an admin tool, not a reader. Owning the story is not the
        // thing that gets you in, so a stranger cannot get in by making one.
        $own = Story::factory()->for($this->stranger)->create();

        $this->actingAs($this->stranger)
            ->get(route('dashboard.stories.show', $own))
            ->assertForbidden();
    }

    public function test_the_second_unguarded_route_to_the_story_page_is_gone(): void
    {
        // /stories/{slug} sat behind auth alone -- no verified email, no admin
        // check -- and rendered the same page. #95 deleted it rather than
        // guarding it, so the regression to catch is it coming back.
        $response = $this->actingAs($this->stranger)->get("/stories/{$this->story->slug}");

        $response->assertNotFound();
        $this->assertLeaksNothing($response->getContent());
    }

    public function test_the_owner_can_still_reach_their_own_story(): void
    {
        // The control. Without it, a policy that denied everybody would make
        // every assertion above pass while breaking the whole app.
        $response = $this->actingAs($this->victim)
            ->getJson("/api/v1/stories/{$this->story->id}");

        $response->assertOk();
        $response->assertJsonPath('data.slug', $this->story->slug);
        $this->assertStringContainsString($this->page->content, $response->getContent());

        $this->actingAs($this->victim)
            ->postJson("/api/v1/stories/{$this->story->id}/save")
            ->assertOk();

        $this->actingAs($this->victim)
            ->putJson("/api/v1/stories/{$this->story->id}", ['name' => 'Renamed By Its Owner'])
            ->assertOk();

        $this->assertSame('Renamed By Its Owner', $this->story->fresh()->name);
    }

    /**
     * Nothing private about the victim's story appears in a response body.
     *
     * This is the assertion the card is really about: a status code alone would
     * be satisfied by a 200 carrying an empty payload, or by a redirect.
     */
    private function assertLeaksNothing(string $body): void
    {
        foreach ([$this->page->content, $this->story->body, $this->story->name] as $secret) {
            $this->assertStringNotContainsString(
                $secret,
                $body,
                'Response body contained text from another user\'s story.'
            );
        }
    }
}
