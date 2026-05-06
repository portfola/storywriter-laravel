<?php

namespace Tests\Feature\Heirloom;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_heirloom_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/heirloom/v1/dashboard');

        $response->assertOk()
                 ->assertJsonStructure([
                     'message'
                 ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $this->getJson('/api/heirloom/v1/dashboard')
             ->assertUnauthorized();
    }
}