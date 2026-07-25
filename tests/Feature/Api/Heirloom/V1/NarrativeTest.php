<?php

namespace Tests\Feature\Api\Heirloom\V1;

use App\Models\Heirloom\Narrative;
use App\Models\Heirloom\Session;
use App\Models\Heirloom\Subject;
use App\Models\Heirloom\Transcript;
use App\Models\User;
use App\Services\Heirloom\NarrativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NarrativeTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithTranscript(): array
    {
        $user = User::factory()->create();
        $subject = Subject::create(['user_id' => $user->id, 'name' => 'Dorothy Ferreira']);
        $session = Session::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'status' => 'transcribed',
        ]);
        $transcript = Transcript::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'transcript_text' => 'It was beautiful. The colours, the heat, the smell of everything.',
            'status' => 'completed',
        ]);

        return [$user, $subject, $session, $transcript];
    }

    public function test_user_can_synthesise_a_narrative(): void
    {
        $this->mock(NarrativeService::class, function ($mock) {
            $mock->shouldReceive('synthesise')
                ->once()
                ->andReturn('I come from a place of colour and heat. Kingston was alive in a way England never was.');
        });

        [$user, $subject] = $this->createUserWithTranscript();

        $response = $this->actingAs($user)
            ->postJson("/api/heirloom/v1/subjects/{$subject->id}/narratives", [
                'format' => 'memoir',
            ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'narrative_text' => 'I come from a place of colour and heat. Kingston was alive in a way England never was.',
                'format' => 'memoir',
                'status' => 'completed',
            ]);

        $this->assertDatabaseHas('heirloom_narratives', [
            'subject_id' => $subject->id,
            'user_id' => $user->id,
            'format' => 'memoir',
        ]);
    }

    public function test_narrative_has_share_token(): void
    {
        $this->mock(NarrativeService::class, function ($mock) {
            $mock->shouldReceive('synthesise')
                ->once()
                ->andReturn('I come from a place of colour and heat.');
        });

        [$user, $subject] = $this->createUserWithTranscript();

        $response = $this->actingAs($user)
            ->postJson("/api/heirloom/v1/subjects/{$subject->id}/narratives");

        $response->assertCreated();
        $this->assertNotNull($response->json('share_token'));
    }

    public function test_narrative_is_accessible_by_share_token(): void
    {
        [$user, $subject, $session, $transcript] = $this->createUserWithTranscript();

        $narrative = Narrative::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'session_id' => $session->id,
            'transcript_id' => $transcript->id,
            'narrative_text' => 'I come from a place of colour and heat.',
            'format' => 'memoir',
            'status' => 'completed',
        ]);

        $this->getJson("/api/heirloom/share/{$narrative->share_token}")
            ->assertOk()
            ->assertJsonFragment(['narrative_text' => 'I come from a place of colour and heat.']);
    }

    public function test_user_cannot_synthesise_another_users_subject(): void
    {
        [$owner, $subject] = $this->createUserWithTranscript();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson("/api/heirloom/v1/subjects/{$subject->id}/narratives")
            ->assertForbidden();

        $this->assertDatabaseCount('heirloom_narratives', 0);
    }
}
