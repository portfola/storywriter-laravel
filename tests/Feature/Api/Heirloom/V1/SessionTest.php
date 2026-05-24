<?php

namespace Tests\Feature\Api\Heirloom\V1;

use App\Models\Heirloom\Session;
use App\Models\Heirloom\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_session(): void
    {
        $user = User::factory()->create();
        $subject = Subject::create(['user_id' => $user->id, 'name' => 'Dorothy Ferreira']);

        $response = $this->actingAs($user)
            ->postJson('/api/heirloom/v1/sessions', [
                'subject_id' => $subject->id,
                'title' => 'First conversation',
            ]);

        $response->assertCreated()
                 ->assertJsonFragment(['title' => 'First conversation'])
                 ->assertJsonFragment(['status' => 'pending']);

        $this->assertDatabaseHas('heirloom_sessions', [
            'subject_id' => $subject->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_list_their_sessions(): void
    {
        $user = User::factory()->create();
        $subject = Subject::create(['user_id' => $user->id, 'name' => 'Dorothy Ferreira']);

        Session::create(['user_id' => $user->id, 'subject_id' => $subject->id, 'status' => 'pending']);
        Session::create(['user_id' => $user->id, 'subject_id' => $subject->id, 'status' => 'transcribed']);

        $response = $this->actingAs($user)
            ->getJson('/api/heirloom/v1/sessions');

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_view_another_users_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $subject = Subject::create(['user_id' => $other->id, 'name' => 'Ron Ashworth']);
        $session = Session::create(['user_id' => $other->id, 'subject_id' => $subject->id, 'status' => 'pending']);

        $this->actingAs($user)
            ->getJson("/api/heirloom/v1/sessions/{$session->id}")
            ->assertForbidden();
    }

    public function test_session_requires_valid_subject(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/heirloom/v1/sessions', [
                'subject_id' => 999,
            ])
            ->assertUnprocessable();
    }
}