<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_screen_is_refused_when_registration_is_disabled(): void
    {
        config(['services.auth.registration_enabled' => false]);

        $this->get('/register')->assertForbidden();
    }

    public function test_new_users_cannot_register_when_registration_is_disabled(): void
    {
        config(['services.auth.registration_enabled' => false]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_web_registration_is_rate_limited(): void
    {
        $limit = (int) config('services.auth.rate_limit_per_minute');

        for ($i = 0; $i < $limit; $i++) {
            $this->get('/register')->assertOk();
        }

        $this->get('/register')->assertStatus(429);
    }
}
