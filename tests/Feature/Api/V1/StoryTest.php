<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_list_of_stories(): void
    {
        // Arrange: create a user with 2 stories, and 1 story for another user
        $user = User::factory()->create();
        Story::factory()->count(2)->for($user)->create();
        Story::factory()->for(User::factory()->create())->create();

        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/stories');

        // Assert: only the authenticated user's 2 stories are returned
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                ['id', 'name', 'slug', 'body', 'prompt', 'user_id', 'created_at', 'updated_at'],
            ],
        ]);
    }

    public function test_user_can_get_single_story(): void
    {
        // Arrange: create a user and one story
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Act: GET by slug (route key)
        $response = $this->getJson('/api/v1/stories/'.$story->slug);

        // Assert: 200 with the full resource shape and correct values
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'body', 'prompt', 'user_id', 'created_at', 'updated_at'],
        ]);
        $response->assertJson([
            'data' => [
                'id' => $story->id,
                'name' => $story->name,
                'slug' => $story->slug,
                'body' => $story->body,
                'user_id' => $story->user_id,
            ],
        ]);
    }

    public function test_single_story_pages_expose_an_audio_url(): void
    {
        // Arrange: a story with one page, no narration generated yet
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();
        StoryPage::factory()->for($story)->create();

        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/stories/'.$story->slug);

        // Assert: audioUrl is part of the page shape, and null until narration is stored
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'pages' => [
                    ['pageNumber', 'content', 'illustrationPrompt', 'imageUrl', 'audioUrl'],
                ],
            ],
        ]);
        $this->assertNull($response->json('data.pages.0.audioUrl'));
    }

    public function test_page_media_comes_back_as_signed_urls_not_stored_paths(): void
    {
        // Arrange: a page whose media has been stored, so the columns hold object
        // paths. The "local" disk signs URLs, which is what S3 does in staging.
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();
        StoryPage::factory()->for($story)->create([
            'page_number' => 1,
            'image_url' => 'stories/1/pages/1/image.png',
            'audio_url' => 'stories/1/pages/1/narration.mp3',
        ]);

        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/stories/'.$story->slug);

        // Assert: the client gets something it can actually fetch, not the raw
        // path — a path on a private bucket loads nothing.
        $response->assertOk();

        $imageUrl = $response->json('data.pages.0.imageUrl');
        $audioUrl = $response->json('data.pages.0.audioUrl');

        $this->assertStringStartsWith('http', $imageUrl);
        $this->assertStringContainsString('signature=', $imageUrl);
        $this->assertStringStartsWith('http', $audioUrl);
        $this->assertStringContainsString('signature=', $audioUrl);
    }

    public function test_story_list_includes_a_signed_cover_image(): void
    {
        // Arrange: the cover is page 1's illustration. It used to be pasted into
        // the body as markdown; the bookshelf gets it from the API now.
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();
        StoryPage::factory()->for($story)->create([
            'page_number' => 1,
            'image_url' => 'stories/1/pages/1/image.png',
        ]);

        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/stories');

        // Assert
        $response->assertOk();

        $coverUrl = $response->json('data.0.coverImageUrl');

        $this->assertStringContainsString('stories/1/pages/1/image.png', $coverUrl);
        $this->assertStringContainsString('signature=', $coverUrl);
    }

    public function test_cover_image_is_null_when_page_one_has_no_illustration(): void
    {
        // Arrange: a story whose cover was never generated. The bookshelf has to
        // get a null it can fall back on, not a URL to nothing.
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();
        StoryPage::factory()->for($story)->create(['page_number' => 1, 'image_url' => null]);

        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/stories');

        // Assert
        $response->assertOk();
        $this->assertNull($response->json('data.0.coverImageUrl'));
    }

    public function test_story_list_does_not_run_a_query_per_story_for_covers(): void
    {
        // Arrange: three stories, each with a cover. The bookshelf shows every
        // story at once, so loading covers must not cost a query each.
        config(['filesystems.default' => 'local']);

        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $story = Story::factory()->for($user)->create();
            // Page numbers are set here rather than left to the factory, whose
            // counter carries over between tests in the same class.
            StoryPage::factory()->for($story)->create([
                'page_number' => 1,
                'image_url' => "stories/{$story->id}/pages/1/image.png",
            ]);
            StoryPage::factory()->for($story)->create(['page_number' => 2]);
        }

        Sanctum::actingAs($user);

        $queries = 0;
        \DB::listen(function () use (&$queries) {
            $queries++;
        });

        // Act
        $response = $this->getJson('/api/v1/stories');

        // Assert: every card really does get its cover — otherwise a low query
        // count would just mean the covers were never loaded at all.
        $response->assertOk();

        foreach (range(0, 2) as $i) {
            $this->assertStringContainsString('signature=', (string) $response->json("data.{$i}.coverImageUrl"));
        }

        // And loading them is a fixed number of queries, not one per story.
        // Generous ceiling so auth queries don't make this brittle.
        $this->assertLessThan(8, $queries, "Expected a constant number of queries, got {$queries}");
    }

    public function test_user_can_get_saved_stories(): void
    {
        // Arrange: create a user with two of their own stories saved
        $user = User::factory()->create();
        $story1 = Story::factory()->for($user)->create();
        $story2 = Story::factory()->for($user)->create();
        $user->savedStories()->attach([$story1->id, $story2->id]);

        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/stories/saved');

        // Assert: returns saved stories ordered by most recent first
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_user_can_save_story(): void
    {
        // Arrange: create a user and a story they own
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Act
        $response = $this->postJson("/api/v1/stories/{$story->id}/save");

        // Assert: 200 with story data
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'body', 'prompt', 'user_id', 'created_at', 'updated_at'],
        ]);
        $this->assertTrue($user->fresh()->savedStories()->where('story_id', $story->id)->exists());
    }

    public function test_save_and_unsave_are_addressed_by_id_not_slug(): void
    {
        // The app only ever holds the numeric id the generate call handed back,
        // so it addressed these two routes by id while they were still bound by
        // slug -- and every save 404'd, silently, for the life of the feature.
        // These tests all passed because they were the only caller using a slug.
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Act + assert: the slug is no longer an address for these two routes
        $this->postJson("/api/v1/stories/{$story->slug}/save")->assertNotFound();
        $this->deleteJson("/api/v1/stories/{$story->slug}/unsave")->assertNotFound();

        // Act + assert: the id is
        $this->postJson("/api/v1/stories/{$story->id}/save")->assertOk();
        $this->assertTrue($user->fresh()->savedStories()->where('story_id', $story->id)->exists());

        $this->deleteJson("/api/v1/stories/{$story->id}/unsave")->assertNoContent();
        $this->assertFalse($user->fresh()->savedStories()->where('story_id', $story->id)->exists());
    }

    public function test_user_can_unsave_story(): void
    {
        // Arrange: create a user with one of their own stories saved
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();
        $user->savedStories()->attach($story->id);

        Sanctum::actingAs($user);

        // Act
        $response = $this->deleteJson("/api/v1/stories/{$story->id}/unsave");

        // Assert: 204 No Content and story is no longer saved
        $response->assertNoContent();
        $this->assertFalse($user->fresh()->savedStories()->where('story_id', $story->id)->exists());
    }

    public function test_saving_story_twice_does_not_create_duplicate(): void
    {
        // Arrange: create a user and a story they own
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Act: save the story twice
        $this->postJson("/api/v1/stories/{$story->id}/save");
        $response = $this->postJson("/api/v1/stories/{$story->id}/save");

        // Assert: still successful and only one entry in pivot table
        $response->assertOk();
        $this->assertEquals(1, $user->fresh()->savedStories()->where('story_id', $story->id)->count());
    }

    public function test_save_persists_elevenlabs_conversation_id_on_story(): void
    {
        // Arrange: create a user and a story without a conversation ID
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create();

        Sanctum::actingAs($user);

        // Act: save the story with an ElevenLabs conversation ID payload
        $response = $this->postJson("/api/v1/stories/{$story->id}/save", [
            'elevenlabs_conversation_id' => 'conv_abc123',
        ]);

        // Assert: 200 and the conversation ID is now stored on the story row
        $response->assertOk();
        $this->assertEquals('conv_abc123', $story->fresh()->elevenlabs_conversation_id);
    }

    public function test_save_does_not_overwrite_existing_elevenlabs_conversation_id(): void
    {
        // Arrange: the user's own story already has a conversation ID stored
        $user = User::factory()->create();
        $story = Story::factory()->for($user)->create([
            'elevenlabs_conversation_id' => 'conv_original',
        ]);

        Sanctum::actingAs($user);

        // Act: save with a different conversation ID
        $response = $this->postJson("/api/v1/stories/{$story->id}/save", [
            'elevenlabs_conversation_id' => 'conv_new',
        ]);

        // Assert: original ID is preserved
        $response->assertOk();
        $this->assertEquals('conv_original', $story->fresh()->elevenlabs_conversation_id);
    }
}
