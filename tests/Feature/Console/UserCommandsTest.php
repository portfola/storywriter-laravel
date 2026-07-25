<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_users(): void
    {
        User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        $this->artisan('users:list')
            ->expectsOutputToContain('ada@example.com')
            ->assertExitCode(0);
    }

    public function test_it_filters_the_list_to_admins(): void
    {
        User::factory()->create(['email' => 'storyteller@example.com', 'is_admin' => false]);
        User::factory()->create(['email' => 'admin@example.com', 'is_admin' => true]);

        $this->artisan('users:list --admin')
            ->expectsOutputToContain('admin@example.com')
            ->doesntExpectOutputToContain('storyteller@example.com')
            ->assertExitCode(0);
    }

    public function test_it_searches_by_email_or_name_case_insensitively(): void
    {
        User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
        User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

        $this->artisan('users:list --search=HOPPER')
            ->expectsOutputToContain('grace@example.com')
            ->doesntExpectOutputToContain('ada@example.com')
            ->assertExitCode(0);
    }

    public function test_it_says_so_when_nothing_matches(): void
    {
        $this->artisan('users:list --search=nobody')
            ->expectsOutputToContain('No matching users.')
            ->assertExitCode(0);
    }

    public function test_it_creates_a_user_that_can_log_in(): void
    {
        $this->artisan('users:create --name="Ada Lovelace" --email=Ada@Example.com --password=secret-password')
            ->assertExitCode(0);

        $user = User::firstWhere('email', 'ada@example.com');

        $this->assertNotNull($user, 'The email should be stored lowercased.');
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertFalse($user->isAdmin());
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->email_verified_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_it_can_create_an_admin(): void
    {
        $this->artisan('users:create --name=Admin --email=admin@example.com --password=secret-password --admin')
            ->assertExitCode(0);

        $this->assertTrue(User::firstWhere('email', 'admin@example.com')->isAdmin());
    }

    public function test_it_can_leave_the_email_unverified(): void
    {
        $this->artisan('users:create --name=Ada --email=ada@example.com --password=secret-password --unverified')
            ->assertExitCode(0);

        $this->assertNull(User::firstWhere('email', 'ada@example.com')->email_verified_at);
    }

    public function test_it_refuses_a_duplicate_email_whatever_the_case(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('users:create --name=Ada --email=ADA@example.com --password=secret-password')
            ->expectsOutputToContain('An account with this email address already exists.')
            ->assertExitCode(1);

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['ada@example.com'])->count());
    }

    public function test_it_refuses_a_short_password(): void
    {
        $this->artisan('users:create --name=Ada --email=ada@example.com --password=short')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }

    public function test_it_prompts_for_anything_not_passed_as_an_option(): void
    {
        $this->artisan('users:create')
            ->expectsQuestion('Name', 'Ada Lovelace')
            ->expectsQuestion('Email', 'ada@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }
}
