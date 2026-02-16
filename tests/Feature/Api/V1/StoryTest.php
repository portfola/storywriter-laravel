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
}
