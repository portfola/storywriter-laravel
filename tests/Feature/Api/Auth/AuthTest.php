<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_user_cannot_login_with_incorrect_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user');

        $response->assertOk();
        $response->assertJson([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    public function test_unauthenticated_user_cannot_fetch_profile(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_user_with_invalid_token_gets_401(): void
    {
        $response = $this->getJson('/api/v1/user', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }
}
