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
}
