<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoryGenerationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_and_saves_a_story_with_slug()
    {
        // 1. ARRANGEMENT
        $user = User::factory()->create();

        Http::fake([
            'api.together.xyz/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "Title: The Brave Test\n\nOnce upon a time...",
                        ],
                    ],
                ],
            ], 200),
        ]);

        // 2. ACTION (Using the CLEAN URL)
        $response = $this->actingAs($user)->postJson('/api/stories/generate', [
            'transcript' => 'Tell me a story about testing.',
        ]);

        // 3. ASSERTION
        $response->assertStatus(200);

        // Check strict fields (User, Name) - NOTE: 'slug' is NOT here
        $this->assertDatabaseHas('stories', [
            'user_id' => $user->id,
            'name' => 'The Brave Test',
        ]);

        // Check the slug separately (to handle the random suffix)
        $story = Story::where('name', 'The Brave Test')->first();
        $this->assertNotNull($story, 'Story was not saved to DB!');
        $this->assertStringStartsWith('the-brave-test', $story->slug);
    }

    /** @test */
    public function it_fails_if_transcript_is_missing()
    {
        $user = User::factory()->create();

        // Using the CLEAN URL
        $response = $this->actingAs($user)->postJson('/api/stories/generate', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['transcript']);
    }
}
