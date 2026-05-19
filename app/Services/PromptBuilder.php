<?php

namespace App\Services;

class PromptBuilder
{
    public function buildStoryPrompt(string $conversation): array
    {
        $systemPrompt = config('prompts.story_generator.system');
        $userTemplate = config('prompts.story_generator.user_template');

        // Replace the conversation placeholder
        $userPrompt = str_replace('{conversation}', $conversation, $userTemplate);

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ];
    }

    /**
     * Parse raw LLM story output into structured data with characters,
     * title, and per-page content with illustration prompts.
     *
     * @return array{title: string, characters: string, pages: array<int, array{content: string, illustrationPrompt: string}>}
     */
    public function parseStoryOutput(string $rawOutput): array
    {
        // Extract [CHARACTERS] block
        $characters = '';
        if (preg_match('/\[CHARACTERS\]\s*\n(.*?)\n\s*\[\/CHARACTERS\]/s', $rawOutput, $charMatch)) {
            $characters = trim($charMatch[1]);
        }

        // Strip characters block and any STORY: / TITLE: headers from body
        $body = preg_replace('/\[CHARACTERS\].*?\[\/CHARACTERS\]/s', '', $rawOutput);
        $body = preg_replace('/^(TITLE|STORY):\s*/mi', '', $body);

        // Extract title: prefer explicit "Title:" line anywhere; otherwise fall back to first line
        $title = 'New Story';
        if (preg_match('/^Title:\s*(.+)$/mi', $body, $titleMatch)) {
            $title = trim(str_replace(['"', '#', '*'], '', $titleMatch[1]));
            $body = preg_replace('/^Title:.*$/mi', '', $body, 1);
        } else {
            $firstLine = strtok(trim($body), "\n");
            if ($firstLine !== false) {
                $extracted = trim(str_replace(['"', '#', '*'], '', $firstLine));
                if ($extracted !== '') {
                    $title = $extracted;
                    $body = preg_replace('/^.*\n?/', '', trim($body), 1);
                }
            }
        }
        $body = trim($body);

        // Split on ---PAGE BREAK--- separator
        $rawChunks = preg_split('/---PAGE BREAK---/i', $body);

        $pages = [];
        foreach ($rawChunks as $chunk) {
            $chunk = trim($chunk);
            if (strlen($chunk) < 20) {
                continue;
            }

            // Extract [ILLUSTRATION: ...] directive
            $illustrationPrompt = '';
            if (preg_match('/\[ILLUSTRATION:\s*(.*?)\]/s', $chunk, $illMatch)) {
                $illustrationPrompt = trim($illMatch[1]);
            }

            // Remove illustration directive and Page N headers from content
            $clean = preg_replace('/\[ILLUSTRATION:\s*.*?\]/s', '', $chunk);
            $clean = preg_replace('/^Page\s*\d+[:.]?\s*/im', '', $clean);
            $clean = trim($clean);

            if (! $clean) {
                continue;
            }

            $pages[] = [
                'content' => $clean,
                'illustrationPrompt' => $illustrationPrompt ?: mb_substr($clean, 0, 200),
            ];
        }

        // Fallback: if parsing produced no pages, use the whole body as one page
        if (empty($pages)) {
            $pages[] = [
                'content' => $body,
                'illustrationPrompt' => mb_substr($body, 0, 200),
            ];
        }

        return [
            'title' => $title,
            'characters' => $characters,
            'pages' => $pages,
        ];
    }

    /**
     * Build a complete image generation prompt by combining the style prefix,
     * character descriptions, and the page's illustration directive.
     */
    public function buildImagePrompt(string $characters, string $illustrationDirective): string
    {
        $stylePrefix = config('prompts.story_generator.image_style_prefix');

        $parts = [$stylePrefix];

        if ($characters !== '') {
            $parts[] = $characters;
        }

        $parts[] = $illustrationDirective;

        return implode(' ', $parts);
    }
}
