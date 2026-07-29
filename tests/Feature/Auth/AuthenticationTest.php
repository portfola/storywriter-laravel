<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_both_logout_controls_submit_their_form_without_script(): void
    {
        // Log Out used to be a link with an onclick that submitted the form for
        // it, once in the desktop dropdown and once in the mobile menu. The
        // content security policy denies handler attributes now, and a denied
        // one throws nothing -- the link just stops logging anyone out. A submit
        // button needs no script at all, so this checks the shape of both.
        $admin = User::factory()->create(['is_admin' => true]);

        $html = (string) $this->actingAs($admin)->get('/dashboard')->getContent();

        preg_match_all(
            '#<form[^>]*action="[^"]*/logout"[^>]*>(.*?)</form>#is',
            $html,
            $forms
        );

        $this->assertCount(2, $forms[1], 'Expected a logout form in the desktop dropdown and in the mobile menu.');

        foreach ($forms[1] as $form) {
            $this->assertMatchesRegularExpression('/<button[^>]*type="submit"/i', $form);
            $this->assertStringNotContainsString('<a ', $form);
        }
    }
}
