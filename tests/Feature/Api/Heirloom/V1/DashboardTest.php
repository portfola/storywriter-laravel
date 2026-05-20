<?php

namespace Tests\Feature\Api\Heirloom\V1;

use App\Models\Heirloom\Session;
use App\Models\Heirloom\Subject;
use App\Models\Heirloom\Transcript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_heirloom_dashboard(): void
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
            'source' => 'manual',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/heirloom/v1/dashboard');

        $response->assertOk()
                 ->assertJsonStructure([
                     'stats' => [
                         'subjects',
                         'sessions',
                         'transcripts',
                         'narratives',
                         'audio_sessions',
                         'manual_sessions',
                     ],
                     'subjects',
                     'recent_activity',
                 ])
                 ->assertJsonPath('stats.subjects', 1)
                 ->assertJsonPath('stats.sessions', 1)
                 ->assertJsonPath('stats.transcripts', 1)
                 ->assertJsonPath('stats.manual_sessions', 1)
                 ->assertJsonPath('stats.audio_sessions', 0);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $this->getJson('/api/heirloom/v1/dashboard')
             ->assertUnauthorized();
    }

    public function test_dashboard_only_shows_current_users_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $subject = Subject::create(['user_id' => $other->id, 'name' => 'Ron Ashworth']);
        Session::create([
            'user_id' => $other->id,
            'subject_id' => $subject->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/heirloom/v1/dashboard');

        $response->assertOk()
                 ->assertJsonPath('stats.subjects', 0)
                 ->assertJsonPath('stats.sessions', 0);
    }
}