<?php

namespace Tests\Feature\Api\Heirloom\V1;

use App\Models\Heirloom\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_subject(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/heirloom/v1/subjects', [
                'name' => 'Dorothy Ferreira',
                'birth_year' => 1941,
                'places_lived' => 'Kingston, Jamaica; Wolverhampton, England',
            ]);

        $response->assertCreated()
                 ->assertJsonFragment(['name' => 'Dorothy Ferreira']);

        $this->assertDatabaseHas('heirloom_subjects', [
            'name' => 'Dorothy Ferreira',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_list_their_subjects(): void
    {
        $user = User::factory()->create();
        Subject::create(['user_id' => $user->id, 'name' => 'Dorothy Ferreira']);
        Subject::create(['user_id' => $user->id, 'name' => 'Ron Ashworth']);

        $response = $this->actingAs($user)
            ->getJson('/api/heirloom/v1/subjects');

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_see_another_users_subjects(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Subject::create(['user_id' => $other->id, 'name' => 'Ron Ashworth']);

        $response = $this->actingAs($user)
            ->getJson('/api/heirloom/v1/subjects');

        $response->assertOk()
                 ->assertJsonCount(0, 'data');
    }
}