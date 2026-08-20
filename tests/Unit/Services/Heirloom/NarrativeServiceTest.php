<?php

namespace Tests\Unit\Services\Heirloom;

use App\Models\Heirloom\Session;
use App\Models\Heirloom\Subject;
use App\Models\Heirloom\Transcript;
use App\Models\User;
use App\Services\Heirloom\NarrativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The model used to be hardcoded here. Groq retired it, and every synthesis
 * came back a 500 with "model_not_found". These cover the model actually
 * reaching Groq, so a config change is enough to survive the next retirement.
 */
class NarrativeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function subjectWithTranscript(): Subject
    {
        $user = User::factory()->create();
        $subject = Subject::create(['user_id' => $user->id, 'name' => 'Dorothy Ferreira']);
        $session = Session::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'status' => 'transcribed',
        ]);
        Transcript::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'transcript_text' => 'It was beautiful. The colours, the heat.',
            'status' => 'completed',
        ]);

        return $subject;
    }

    private function fakeGroqReply(string $text = 'I come from a place of colour and heat.'): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => $text]]],
            ]),
        ]);
    }

    public function test_it_sends_the_configured_model(): void
    {
        config(['services.groq.model' => 'openai/gpt-oss-120b']);
        $this->fakeGroqReply();

        (new NarrativeService)->synthesise($this->subjectWithTranscript());

        Http::assertSent(fn ($request) => $request['model'] === 'openai/gpt-oss-120b');
    }

    public function test_the_model_can_be_overridden_by_config(): void
    {
        config(['services.groq.model' => 'qwen/qwen3.6-27b']);
        $this->fakeGroqReply();

        (new NarrativeService)->synthesise($this->subjectWithTranscript());

        Http::assertSent(fn ($request) => $request['model'] === 'qwen/qwen3.6-27b');
    }

    public function test_it_defaults_to_a_model_that_exists_on_groq(): void
    {
        $this->assertSame('openai/gpt-oss-120b', config('services.groq.model'));
    }

    public function test_it_throws_when_groq_rejects_the_request(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'The model `gone` does not exist or you do not have access to it.'],
            ], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Narrative synthesis failed');

        (new NarrativeService)->synthesise($this->subjectWithTranscript());
    }
}
