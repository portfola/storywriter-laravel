<?php

namespace Tests\Feature\Api\Heirloom\V1;

use App\Models\Heirloom\Session;
use App\Models\Heirloom\Subject;
use App\Models\User;
use App\Services\Heirloom\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_transcribe_a_session(): void
    {
        Storage::fake('s3');

        $this->mock(TranscriptionService::class, function ($mock) {
            $mock->shouldReceive('transcribe')
                 ->once()
                 ->andReturn('It was a beautiful place. The colours, the heat, the smell of everything.');
        });

        $user = User::factory()->create();
        $subject = Subject::create(['user_id' => $user->id, 'name' => 'Dorothy Ferreira']);
        $session = Session::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'status' => 'pending',
        ]);

        $file = UploadedFile::fake()->create('audio.mp3', 1000, 'audio/mpeg');

        $response = $this->actingAs($user)
            ->postJson("/api/heirloom/v1/sessions/{$session->id}/transcribe", [
                'audio' => $file,
            ]);

        $response->assertCreated()
                 ->assertJsonFragment([
                     'transcript_text' => 'It was a beautiful place. The colours, the heat, the smell of everything.',
                     'status' => 'completed',
                 ]);

        $this->assertDatabaseHas('heirloom_transcripts', [
            'session_id' => $session->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('heirloom_sessions', [
            'id' => $session->id,
            'status' => 'transcribed',
        ]);
    }

    public function test_user_cannot_transcribe_another_users_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $subject = Subject::create(['user_id' => $other->id, 'name' => 'Ron Ashworth']);
        $session = Session::create([
            'user_id' => $other->id,
            'subject_id' => $subject->id,
            'status' => 'pending',
        ]);

        $file = UploadedFile::fake()->create('audio.mp3', 1000, 'audio/mpeg');

        $this->actingAs($user)
            ->postJson("/api/heirloom/v1/sessions/{$session->id}/transcribe", [
                'audio' => $file,
            ])
            ->assertForbidden();
    }

    public function test_transcription_requires_audio_file(): void
    {
        $user = User::factory()->create();
        $subject = Subject::create(['user_id' => $user->id, 'name' => 'Dorothy Ferreira']);
        $session = Session::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->postJson("/api/heirloom/v1/sessions/{$session->id}/transcribe", [])
            ->assertUnprocessable();
    }
}