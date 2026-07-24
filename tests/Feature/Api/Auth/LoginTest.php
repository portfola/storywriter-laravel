<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_correct_password(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'not-the-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_matches_the_email_case_insensitively(): void
    {
        User::factory()->create([
            'email' => 'Ada@Example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();
    }

    public function test_the_passwordless_login_endpoint_no_longer_exists(): void
    {
        // Until this was removed, POST /api/v1/login handed out a full Sanctum
        // token for any known email address with no password at all.
        User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'ada@example.com',
            'device_name' => 'web-browser',
        ]);

        $response->assertNotFound();
    }

    public function test_login_is_rate_limited_by_ip(): void
    {
        $limit = (int) config('services.auth.rate_limit_per_minute');

        for ($attempt = 0; $attempt < $limit; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'ada@example.com',
                'password' => 'guess',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'guess',
        ])->assertStatus(429);
    }
}
