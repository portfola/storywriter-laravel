<?php

namespace App\Services\Heirloom;

use Illuminate\Support\Facades\Http;

class NarrativeService
{

    public function __construct()
    {
        $this->apiKey = config('services.together.api_key') ?? '';
        $this->model = config('services.together.text_model');
    }

    public function synthesise(string $transcriptText, string $format = 'memoir'): string
    {
        $prompt = $this->buildPrompt($transcriptText, $format);

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'max_tokens' => 1000,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Narrative synthesis failed: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    protected function buildPrompt(string $transcriptText, string $format): string
    {
        $formatInstruction = match($format) {
            'letter' => 'Write this as a legacy letter — in the subject\'s voice, addressed to future generations.',
            'timeline' => 'Write this as a timeline of key life moments, in the subject\'s voice.',
            default => 'Write this as the opening of a memoir — something a grandchild could read and feel they had sat in the room.',
        };

        return <<<PROMPT
You are a narrative editor helping to preserve a person's life story.

You will be given a transcript of a recorded interview. Your task is to synthesise 
it into a short, vivid first-person narrative — written in the subject's own voice, 
as if they are telling their story directly to someone they love.

Guidelines:
- Write in the first person, in the subject's voice. Do not write about them — write as them.
- Preserve their speech patterns, idioms, and rhythms. If they are formal, be formal. If they are wry, be wry.
- Pay attention to pauses, laughter, and hesitations marked in the transcript — these carry emotional weight.
- Do not invent facts, memories, or emotions not present in the transcript.
- Prioritise moments of feeling over moments of fact.
- Write between 400 and 600 words unless instructed otherwise.
- End on something that resonates — a thought, an image, a question. Not a summary.

{$formatInstruction}

Here is the transcript:

{$transcriptText}
PROMPT;
    }
}