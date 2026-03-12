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

        // Remove the [CHARACTERS] block from text for further parsing
        $text = preg_replace('/\[CHARACTERS\]\s*\n.*?\n\s*\[\/CHARACTERS\]\s*/s', '', $rawOutput);
        $text = trim($text);

        // Extract title from the first line
        $firstLine = strtok($text, "\n");
        $title = trim(str_replace(['Title:', '"', '#', '*'], '', $firstLine)) ?: 'New Story';

        // Remove the title line
        $body = preg_replace('/^.*\n/', '', $text, 1);
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

            // Fallback if no illustration directive: use truncated page content
            if (! $illustrationPrompt) {
                $illustrationPrompt = mb_substr($clean, 0, 200);
            }

            $pages[] = [
                'content' => $clean,
                'illustrationPrompt' => $illustrationPrompt,
            ];
        }

        // Fallback: if parsing produced no pages, use the whole body as one page
        if (empty($pages)) {
            $fallbackPrompt = mb_substr($body, 0, 200);

            $pages[] = [
                'content' => $body,
                'illustrationPrompt' => $fallbackPrompt,
            ];
        }

        return [
            'title' => $title,
            'characters' => $characters,
            'pages' => $pages,
        ];
    }
}
