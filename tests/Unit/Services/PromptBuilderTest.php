<?php

namespace Tests\Unit\Services;

use App\Services\PromptBuilder;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    private PromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PromptBuilder;
    }

    // ─── parseStoryOutput: well-formed input ────────────────────────

    public function test_parse_story_output_extracts_all_blocks(): void
    {
        $raw = <<<'TEXT'
[CHARACTERS]
Sparky: A small red dragon with bright orange wings.
Luna: A curious girl with brown curly hair and a blue dress.
[/CHARACTERS]

Title: Sparky and the Rainbow Cave

Page 1
Sparky peeked out of his cave and sniffed the morning air. The sky was painted in shades of pink and gold.
[ILLUSTRATION: A small red dragon emerging from a cave at sunrise with pink and gold sky]
---PAGE BREAK---

Page 2
Luna walked through the meadow, her boots squishing in the wet grass. She spotted Sparky and waved hello.
[ILLUSTRATION: A girl in a blue dress waving at a red dragon in a green meadow]
---PAGE BREAK---

Page 3
Together they discovered a hidden waterfall that sparkled with every color of the rainbow. They splashed and laughed until the sun went down.
[ILLUSTRATION: A girl and dragon playing under a rainbow waterfall at sunset]
TEXT;

        $result = $this->builder->parseStoryOutput($raw);

        $this->assertEquals('Sparky and the Rainbow Cave', $result['title']);
        $this->assertStringContainsString('Sparky: A small red dragon', $result['characters']);
        $this->assertStringContainsString('Luna: A curious girl', $result['characters']);
        $this->assertCount(3, $result['pages']);

        // Page 1
        $this->assertStringContainsString('Sparky peeked out', $result['pages'][0]['content']);
        $this->assertStringNotContainsString('[ILLUSTRATION', $result['pages'][0]['content']);
        $this->assertStringNotContainsString('Page 1', $result['pages'][0]['content']);
        $this->assertEquals('A small red dragon emerging from a cave at sunrise with pink and gold sky', $result['pages'][0]['illustrationPrompt']);

        // Page 2
        $this->assertStringContainsString('Luna walked through the meadow', $result['pages'][1]['content']);
        $this->assertEquals('A girl in a blue dress waving at a red dragon in a green meadow', $result['pages'][1]['illustrationPrompt']);

        // Page 3
        $this->assertStringContainsString('hidden waterfall', $result['pages'][2]['content']);
        $this->assertEquals('A girl and dragon playing under a rainbow waterfall at sunset', $result['pages'][2]['illustrationPrompt']);
    }

    // ─── parseStoryOutput: missing [CHARACTERS] block ───────────────

    public function test_parse_story_output_handles_missing_characters_block(): void
    {
        $raw = <<<'TEXT'
Title: A Simple Tale

Page 1
Once upon a time there was a brave little rabbit who loved to explore.
[ILLUSTRATION: A small white rabbit standing at the edge of a forest path]
---PAGE BREAK---

Page 2
The rabbit found a shiny golden key hidden under a mushroom. What could it unlock?
[ILLUSTRATION: A white rabbit holding a golden key next to a red spotted mushroom]
TEXT;

        $result = $this->builder->parseStoryOutput($raw);

        $this->assertEquals('A Simple Tale', $result['title']);
        $this->assertEquals('', $result['characters']);
        $this->assertCount(2, $result['pages']);
        $this->assertStringContainsString('brave little rabbit', $result['pages'][0]['content']);
    }

    // ─── parseStoryOutput: missing [ILLUSTRATION] directives ────────

    public function test_parse_story_output_handles_missing_illustration_directives(): void
    {
        $raw = <<<'TEXT'
[CHARACTERS]
Bear: A big brown bear with a red hat.
[/CHARACTERS]

Title: Bear Goes Fishing

Page 1
Bear woke up early and stretched his big furry arms. Today was a perfect day for fishing at the river.
---PAGE BREAK---

Page 2
He caught the biggest fish anyone had ever seen! All his friends cheered and clapped their paws.
TEXT;

        $result = $this->builder->parseStoryOutput($raw);

        $this->assertEquals('Bear Goes Fishing', $result['title']);
        $this->assertCount(2, $result['pages']);

        // Without [ILLUSTRATION], fallback to truncated page content
        $this->assertStringContainsString('Bear woke up early', $result['pages'][0]['illustrationPrompt']);
        $this->assertStringContainsString('biggest fish', $result['pages'][1]['illustrationPrompt']);
    }

    // ─── parseStoryOutput: no page breaks (single page fallback) ────

    public function test_parse_story_output_handles_no_page_breaks(): void
    {
        $raw = <<<'TEXT'
Title: A Tiny Adventure

Once upon a time a little caterpillar crawled across a big green leaf. She looked up at the bright blue sky and smiled. It was going to be a wonderful day full of surprises.
TEXT;

        $result = $this->builder->parseStoryOutput($raw);

        $this->assertEquals('A Tiny Adventure', $result['title']);
        $this->assertCount(1, $result['pages']);
        $this->assertStringContainsString('little caterpillar', $result['pages'][0]['content']);
        // Fallback illustration prompt is truncated content
        $this->assertNotEmpty($result['pages'][0]['illustrationPrompt']);
    }

    // ─── parseStoryOutput: empty/minimal input ──────────────────────

    public function test_parse_story_output_handles_empty_input(): void
    {
        $result = $this->builder->parseStoryOutput('');

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('characters', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertNotEmpty($result['pages']);
    }

    public function test_parse_story_output_handles_minimal_input(): void
    {
        $raw = 'Title: Untitled Story';

        $result = $this->builder->parseStoryOutput($raw);

        $this->assertEquals('Untitled Story', $result['title']);
        $this->assertNotEmpty($result['pages']);
    }

    // ─── parseStoryOutput: title edge cases ─────────────────────────

    public function test_parse_story_output_strips_markdown_from_title(): void
    {
        $raw = <<<'TEXT'
## **"The Magic Garden"**

A little girl planted a seed and watched it grow into the most wonderful garden full of singing flowers and dancing butterflies.
TEXT;

        $result = $this->builder->parseStoryOutput($raw);

        $this->assertEquals('The Magic Garden', $result['title']);
    }

    public function test_parse_story_output_filters_short_chunks(): void
    {
        $raw = <<<'TEXT'
Title: Test Story

Page 1
The brave knight rode through the enchanted forest looking for the lost golden crown. Birds sang sweet melodies above.
[ILLUSTRATION: A knight on horseback riding through a magical forest]
---PAGE BREAK---

Too short
---PAGE BREAK---

Page 3
The knight found the crown sitting on a velvet cushion inside a crystal cave. Light danced on the walls.
[ILLUSTRATION: A knight discovering a golden crown in a sparkling crystal cave]
TEXT;

        $result = $this->builder->parseStoryOutput($raw);

        $this->assertCount(2, $result['pages']);
        $this->assertStringContainsString('brave knight', $result['pages'][0]['content']);
        $this->assertStringContainsString('found the crown', $result['pages'][1]['content']);
    }

    // ─── buildImagePrompt ───────────────────────────────────────────

    public function test_build_image_prompt_combines_all_parts(): void
    {
        $characters = 'Luna: A girl with brown hair and a blue dress.';
        $directive = 'A girl standing in a sunny meadow picking flowers';

        $result = $this->builder->buildImagePrompt($characters, $directive);

        $stylePrefix = config('prompts.story_generator.image_style_prefix');
        $this->assertStringStartsWith($stylePrefix, $result);
        $this->assertStringContainsString($characters, $result);
        $this->assertStringContainsString($directive, $result);
    }

    public function test_build_image_prompt_works_without_characters(): void
    {
        $directive = 'A sunny meadow with colorful wildflowers';

        $result = $this->builder->buildImagePrompt('', $directive);

        $stylePrefix = config('prompts.story_generator.image_style_prefix');
        $this->assertStringStartsWith($stylePrefix, $result);
        $this->assertStringContainsString($directive, $result);
        // Characters part should be omitted entirely (not an empty string between spaces)
        $this->assertStringNotContainsString('   ', $result);
    }
}
