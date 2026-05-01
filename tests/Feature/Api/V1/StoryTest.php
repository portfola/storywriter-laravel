<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
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

    public function test_user_can_get_saved_stories(): void
    {
        // Arrange: create a user with saved stories
        $user = User::factory()->create();
        $story1 = Story::factory()->for(User::factory())->create();
        $story2 = Story::factory()->for(User::factory())->create();
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
        // Arrange: create users and a story
        $user = User::factory()->create();
        $story = Story::factory()->for(User::factory())->create();

        Sanctum::actingAs($user);

        // Act
        $response = $this->postJson("/api/v1/stories/{$story->slug}/save");

        // Assert: 200 with story data
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'body', 'prompt', 'user_id', 'created_at', 'updated_at'],
        ]);
        $this->assertTrue($user->fresh()->savedStories()->where('story_id', $story->id)->exists());
    }

    public function test_user_can_unsave_story(): void
    {
        // Arrange: create a user with a saved story
        $user = User::factory()->create();
        $story = Story::factory()->for(User::factory())->create();
        $user->savedStories()->attach($story->id);

        Sanctum::actingAs($user);

        // Act
        $response = $this->deleteJson("/api/v1/stories/{$story->slug}/unsave");

        // Assert: 204 No Content and story is no longer saved
        $response->assertNoContent();
        $this->assertFalse($user->fresh()->savedStories()->where('story_id', $story->id)->exists());
    }

    public function test_saving_story_twice_does_not_create_duplicate(): void
    {
        // Arrange: create users and a story
        $user = User::factory()->create();
        $story = Story::factory()->for(User::factory())->create();

        Sanctum::actingAs($user);

        // Act: save the story twice
        $this->postJson("/api/v1/stories/{$story->slug}/save");
        $response = $this->postJson("/api/v1/stories/{$story->slug}/save");

        // Assert: still successful and only one entry in pivot table
        $response->assertOk();
        $this->assertEquals(1, $user->fresh()->savedStories()->where('story_id', $story->id)->count());
    }

    public function test_save_persists_elevenlabs_conversation_id_on_story(): void
    {
        // Arrange: create a user and a story without a conversation ID
        $user = User::factory()->create();
        $story = Story::factory()->for(User::factory())->create();

        Sanctum::actingAs($user);

        // Act: save the story with an ElevenLabs conversation ID payload
        $response = $this->postJson("/api/v1/stories/{$story->slug}/save", [
            'elevenlabs_conversation_id' => 'conv_abc123',
        ]);

        // Assert: 200 and the conversation ID is now stored on the story row
        $response->assertOk();
        $this->assertEquals('conv_abc123', $story->fresh()->elevenlabs_conversation_id);
    }

    public function test_save_does_not_overwrite_existing_elevenlabs_conversation_id(): void
    {
        // Arrange: story already has a conversation ID stored
        $user = User::factory()->create();
        $story = Story::factory()->for(User::factory())->create([
            'elevenlabs_conversation_id' => 'conv_original',
        ]);

        Sanctum::actingAs($user);

        // Act: save with a different conversation ID
        $response = $this->postJson("/api/v1/stories/{$story->slug}/save", [
            'elevenlabs_conversation_id' => 'conv_new',
        ]);

        // Assert: original ID is preserved
        $response->assertOk();
        $this->assertEquals('conv_original', $story->fresh()->elevenlabs_conversation_id);
    }
}
