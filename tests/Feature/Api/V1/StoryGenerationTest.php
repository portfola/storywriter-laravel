<?php

namespace Tests\Feature\Api\V1;

use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoryGenerationTest extends TestCase
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

    /**
     * Build a mock LLM response with [CHARACTERS], [ILLUSTRATION] directives,
     * and ---PAGE BREAK--- separators matching the new structured format.
     */
    private function mockStoryOutput(): string
    {
        return <<<'STORY'
[CHARACTERS]
Sparky: A small red dragon with bright orange wings, round green eyes, and tiny golden horns.
Luna: A curious girl with brown curly hair, a blue dress, and red rain boots.
[/CHARACTERS]

Title: Sparky and the Rainbow Cave

Page 1
Sparky the little dragon sat on a mossy rock, blowing tiny smoke rings into the morning air. His friend Luna skipped down the forest path, her red boots splashing in every puddle.

[ILLUSTRATION: A small red dragon with orange wings sitting on a mossy rock blowing smoke rings, while a girl with brown curly hair in a blue dress and red boots skips along a forest path, morning sunlight filtering through the trees]

---PAGE BREAK---

Page 2
"Look, Sparky!" Luna pointed to a cave entrance that sparkled with every color of the rainbow. Crystals hung from the ceiling like magical icicles, casting dancing light across the walls.

[ILLUSTRATION: A girl and a small red dragon standing at the entrance of a cave filled with rainbow-colored crystals hanging from the ceiling, colorful light dancing on the cave walls]

---PAGE BREAK---

Page 3
Deep inside the cave, they found a tiny bird with a broken wing. "Don't worry," Luna whispered softly. Sparky breathed a warm, gentle puff of golden light over the bird, and its wing began to glow and heal.

[ILLUSTRATION: Inside a crystal cave, a girl kneeling beside a tiny bird while a small red dragon breathes golden healing light onto the bird's broken wing, warm glow illuminating their faces]

---PAGE BREAK---

Page 4
The bird chirped happily and flew up to the highest crystal, singing a melody that made the whole cave shimmer. Sparky and Luna held hands and danced in the rainbow light, giggling together.

[ILLUSTRATION: A healed bird perched on a rainbow crystal singing while a girl and a small red dragon dance together in colorful shimmering light inside the cave, both laughing joyfully]
STORY;
    }

    /** @test */
    public function it_requires_transcript_field()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/stories/generate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['transcript']);
    }

    /** @test */
    public function it_generates_structured_story_with_pages_and_illustrations()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->fakeMediaDisk();

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
                    ['url' => 'https://example.com/page1-image.jpg'],
                ],
            ], 200),
            'example.com/*' => Http::response('fake-jpg-bytes'),
        ]);

        $payload = [
            'transcript' => 'The child wants a story about a dragon and a girl who explore a cave.',
            'options' => [
                'maxTokens' => 2000,
                'temperature' => 0.7,
            ],
        ];

        $response = $this->postJson('/api/v1/stories/generate', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['title', 'pages', 'cover_image', 'story_id', 'page_count'],
            ]);

        $data = $response->json('data');

        // Title parsed correctly
        $this->assertEquals('Sparky and the Rainbow Cave', $data['title']);

        // 4 pages generated
        $this->assertEquals(4, $data['page_count']);
        $this->assertCount(4, $data['pages']);

        // Cover image is page 1's image, served from our own copy rather than
        // Together's URL, which expires after a few hours.
        $storedPath = "stories/{$data['story_id']}/pages/1/image.png";

        $this->assertStringContainsString($storedPath, $data['cover_image']);
        $this->assertStringNotContainsString('page1-image.jpg', $data['cover_image']);
        Storage::disk('public')->assertExists($storedPath);

        // Page 1 has imageUrl, all pages have illustrationPrompt
        $this->assertEquals($data['cover_image'], $data['pages'][0]['imageUrl']);
        $this->assertNotEmpty($data['pages'][0]['illustrationPrompt']);

        // Pages 2-4 have illustrationPrompt but null imageUrl
        for ($i = 1; $i < 4; $i++) {
            $this->assertNull($data['pages'][$i]['imageUrl'], 'Page '.($i + 1).' should have null imageUrl');
            $this->assertNotEmpty($data['pages'][$i]['illustrationPrompt'], 'Page '.($i + 1).' should have illustrationPrompt');
        }

        // Verify story_pages records in database
        $story = Story::where('name', 'Sparky and the Rainbow Cave')->first();
        $this->assertNotNull($story);
        $this->assertNotEmpty($story->characters_description);
        $this->assertStringContainsString('Sparky', $story->characters_description);

        $storyPages = StoryPage::where('story_id', $story->id)->orderBy('page_number')->get();
        $this->assertCount(4, $storyPages);

        // Only page 1 has an image in the database, and what is saved is where the
        // file lives — not the signed URL, which would be dead by the time anyone
        // read the row back.
        $this->assertEquals($storedPath, $storyPages[0]->image_url);
        $this->assertStringNotContainsString('![](', $story->body);
        for ($i = 1; $i < 4; $i++) {
            $this->assertNull($storyPages[$i]->image_url, 'StoryPage '.($i + 1).' should have null image_url in DB');
        }

        // All pages have illustration_prompt in database
        foreach ($storyPages as $page) {
            $this->assertNotEmpty($page->illustration_prompt);
        }

        // Page numbers are sequential
        $this->assertEquals([1, 2, 3, 4], $storyPages->pluck('page_number')->toArray());
    }

    /** @test */
    public function it_handles_together_ai_failure_gracefully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::fake([
            'api.together.xyz/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Something went wrong',
                ],
            ], 400),
        ]);

        $payload = [
            'transcript' => 'Test transcript',
            'options' => [],
        ];

        $response = $this->postJson('/api/v1/stories/generate', $payload);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'Story text generation failed',
            ]);
    }
}
