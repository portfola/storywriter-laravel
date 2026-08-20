<?php

namespace App\Services\Heirloom;

use App\Models\Heirloom\Subject;
use Illuminate\Support\Facades\Http;

class NarrativeService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl = 'https://api.groq.com/openai/v1';
    

    public function __construct()
    {
        $this->apiKey = config('services.groq.key') ?? '';
        $this->model = config('services.groq.model');
    }

    public function synthesise(Subject $subject, string $format = 'memoir'): string
    {
        [$transcriptText, $sessionCount] = $this->buildTranscriptText($subject);

        $prompt = $this->buildPrompt($transcriptText, $format, $sessionCount);

        $response = Http::withToken($this->apiKey)
            ->timeout(120)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'max_tokens' => 2500,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Narrative synthesis failed: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    protected function buildTranscriptText(Subject $subject): array
    {
        $sessions = $subject->sessions()
            ->with(['transcript' => fn ($q) => $q->where('status', 'completed')])
            ->get()
            ->filter(fn ($s) => $s->transcript !== null)
            ->values();

        if ($sessions->isEmpty()) {
            throw new \RuntimeException('No completed transcripts found for this subject.');
        }

        $text = $sessions->map(function ($session, $index) {
            $label = $session->title
                ? "Session " . ($index + 1) . " — {$session->title}"
                : "Session " . ($index + 1);

            return "{$label}:\n\n{$session->transcript->transcript_text}";
        })->implode("\n\n---\n\n");

        return [$text, $sessions->count()];
    }

    protected function buildPrompt(string $transcriptText, string $format, int $sessionCount = 1): string
    {
        $wordCount = $sessionCount > 1 ? '600 and 900' : '400 and 600';

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
- Write between {$wordCount} words unless instructed otherwise.
- End on something that resonates — a thought, an image, a question. Not a summary.

{$formatInstruction}

Here is the transcript:

{$transcriptText}
PROMPT;
    }
}