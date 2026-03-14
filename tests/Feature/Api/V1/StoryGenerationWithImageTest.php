<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoryGenerationWithImageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a mock LLM response with the structured format including
     * [CHARACTERS], [ILLUSTRATION] directives, and ---PAGE BREAK--- separators.
     */
    private function mockStoryOutput(string $title = "The Dragon's Library", ?string $body = null): string
    {
        if ($body !== null) {
            return $body;
        }

        return <<<STORY
[CHARACTERS]
Ember: A friendly green dragon with sparkling blue scales on his belly, large round amber eyes, and small purple wings.
Mia: A brave girl with long black braids, a yellow raincoat, and purple sneakers.
[/CHARACTERS]

{$title}

Page 1
Once upon a time, in a magical library hidden beneath an old oak tree, Ember the dragon sat reading his favorite book. His friend Mia pushed open the heavy wooden door and called out, "Ember, I found a map!"

[ILLUSTRATION: A friendly green dragon with blue belly scales sitting in a cozy underground library reading a book, while a girl with black braids in a yellow raincoat pushes open a wooden door, warm lamplight filling the room]

---PAGE BREAK---

Page 2
The dragon loved to read, and together they followed the map through twisting tunnels filled with glowing mushrooms until they reached a hidden room full of golden books.

[ILLUSTRATION: A green dragon and a girl with black braids walking through a tunnel lit by glowing mushrooms, following an old treasure map, magical blue and green light around them]
STORY;
    }

    /**
     * Test that the story title is extracted from the text content,
     * NOT from the image URL markdown that gets prepended to the body.
     *
     * This is a regression test for the bug where image URLs were being
     * saved as story titles because the image was prepended before title extraction.
     */
    public function test_it_extracts_story_title_not_image_url_when_image_is_generated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $this->mockStoryOutput(),
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://api.together.ai/test-image-12345.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a dragon who loves to read',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Title should be "The Dragon's Library", NOT the image URL
        $this->assertEquals("The Dragon's Library", $story->name);
        $this->assertStringNotContainsString('api.together.ai', $story->name);
        $this->assertStringNotContainsString('http', $story->name);

        // Characters description should be saved
        $this->assertNotEmpty($story->characters_description);
        $this->assertStringContainsString('Ember', $story->characters_description);
        $this->assertStringContainsString('Mia', $story->characters_description);

        // Body SHOULD contain the image markdown at the top
        $this->assertStringStartsWith('![](', $story->body);
        $this->assertStringContainsString('https://api.together.ai/test-image-12345.jpg', $story->body);

        // Body should also contain the actual story text
        $this->assertStringContainsString("The Dragon's Library", $story->body);
        $this->assertStringContainsString('Once upon a time', $story->body);

        // Structured pages should exist
        $pages = StoryPage::where('story_id', $story->id)->orderBy('page_number')->get();
        $this->assertCount(2, $pages);
        $this->assertEquals('https://api.together.ai/test-image-12345.jpg', $pages[0]->image_url);
        $this->assertNull($pages[1]->image_url);
    }

    /**
     * Test that title extraction works correctly even when image generation fails.
     */
    public function test_it_extracts_story_title_correctly_when_image_generation_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $this->mockStoryOutput('The Brave Little Robot'),
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'error' => 'Service unavailable',
            ], 503),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a brave robot',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Title should still be extracted correctly
        $this->assertEquals('The Brave Little Robot', $story->name);

        // Characters description should still be saved even when image fails
        $this->assertNotEmpty($story->characters_description);

        // Body should NOT contain image markdown
        $this->assertStringNotContainsString('![](', $story->body);

        // Structured pages should exist but with no images
        $pages = StoryPage::where('story_id', $story->id)->orderBy('page_number')->get();
        $this->assertCount(2, $pages);
        $this->assertNull($pages[0]->image_url);
        $this->assertNull($pages[1]->image_url);
    }

    /**
     * Test title extraction when story starts with "Title:" prefix.
     */
    public function test_it_removes_title_prefix_from_story_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $this->mockStoryOutput('Title: The Magical Garden'),
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a magical garden',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // "Title:" prefix should be removed
        $this->assertEquals('The Magical Garden', $story->name);
        $this->assertStringNotContainsString('Title:', $story->name);

        // Characters description should be saved
        $this->assertNotEmpty($story->characters_description);
    }

    /**
     * Test that special characters are properly cleaned from title.
     */
    public function test_it_cleans_special_characters_from_title(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $this->mockStoryOutput('# "The Star\'s Journey" *'),
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a star',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Special characters should be removed
        $this->assertEquals("The Star's Journey", $story->name);
        $this->assertStringNotContainsString('#', $story->name);
        $this->assertStringNotContainsString('"', $story->name);
        $this->assertStringNotContainsString('*', $story->name);
    }

    /**
     * Test fallback to "New Story" when title is empty.
     */
    public function test_it_uses_fallback_title_when_story_text_is_empty(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '',
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'Generate a story',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Should use fallback title
        $this->assertEquals('New Story', $story->name);
    }

    /**
     * Test that characters_description is saved on the Story model.
     */
    public function test_it_saves_characters_description_on_story(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $this->mockStoryOutput(),
                        ],
                    ],
                ],
            ], 200),
            'api.together.xyz/v1/images/generations' => Http::response([
                'data' => [
                    ['url' => 'https://example.com/image.jpg'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/stories/generate', [
            'transcript' => 'A story about a dragon and a girl',
        ]);

        $response->assertStatus(200);

        $story = Story::latest()->first();

        // Characters description should contain both character descriptions
        $this->assertNotNull($story->characters_description);
        $this->assertStringContainsString('Ember', $story->characters_description);
        $this->assertStringContainsString('green dragon', $story->characters_description);
        $this->assertStringContainsString('Mia', $story->characters_description);
        $this->assertStringContainsString('black braids', $story->characters_description);
    }
}
