<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase; 
    public function test_user_can_login_and_receive_token(): void 
    {
        $user = User::factory()->create(); 

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email, 
            'password' => 'password',
        ]);

        $response->assertOk(); 
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_user_cannot_login_with_incorrect_credentials(): void 
    {
        $suer = User::factory()->create(); 

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email, 
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422); 
        $response->assertJsonValidationErrors(['email']);
    }
}
