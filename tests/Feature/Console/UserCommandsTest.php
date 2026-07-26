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

    public function test_it_updates_password_verification_and_admin_together(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'is_admin' => false,
            'email_verified_at' => null,
        ]);

        $this->artisan('users:update ada@example.com --password=new-password --verified --admin --force')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertTrue($user->isAdmin());
        $this->assertNotNull($user->email_verified_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'new-password',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_it_finds_the_account_by_id_or_by_email_whatever_the_case(): void
    {
        $byId = User::factory()->create(['email' => 'grace@example.com', 'is_admin' => false]);
        $byEmail = User::factory()->create(['email' => 'ada@example.com', 'is_admin' => false]);

        $this->artisan("users:update {$byId->id} --admin --force")->assertExitCode(0);
        $this->artisan('users:update ADA@Example.com --admin --force')->assertExitCode(0);

        $this->assertTrue($byId->refresh()->isAdmin());
        $this->assertTrue($byEmail->refresh()->isAdmin());
    }

    public function test_it_can_demote_and_unverify(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->artisan("users:update {$user->id} --no-admin --unverified --force")
            ->assertExitCode(0);

        $user->refresh();

        $this->assertFalse($user->isAdmin());
        $this->assertNull($user->email_verified_at);
    }

    public function test_it_prompts_for_the_password_when_asked_to(): void
    {
        $user = User::factory()->create();

        $this->artisan("users:update {$user->id} --prompt-password --force")
            ->expectsQuestion('New password', 'prompted-password')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('prompted-password', $user->refresh()->password));
    }

    public function test_it_revokes_api_tokens_on_request(): void
    {
        $user = User::factory()->create();
        $user->createToken('test')->plainTextToken;

        $this->artisan("users:update {$user->id} --password=new-password --revoke-tokens --force")
            ->assertExitCode(0);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_it_leaves_api_tokens_alone_by_default(): void
    {
        $user = User::factory()->create();
        $user->createToken('test');

        $this->artisan("users:update {$user->id} --password=new-password --force")
            ->assertExitCode(0);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_it_can_be_aborted_at_the_confirmation(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan("users:update {$user->id} --admin")
            ->expectsConfirmation('Apply these changes?', 'no')
            ->expectsOutputToContain('Aborted, nothing was changed.')
            ->assertExitCode(1);

        $this->assertFalse($user->refresh()->isAdmin());
    }

    public function test_it_fails_when_the_account_does_not_exist(): void
    {
        $this->artisan('users:update nobody@example.com --admin --force')
            ->expectsOutputToContain('No account matches')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_no_change_was_requested(): void
    {
        $user = User::factory()->create();

        $this->artisan("users:update {$user->id} --force")
            ->expectsOutputToContain('Nothing to change.')
            ->assertExitCode(1);
    }

    public function test_it_refuses_contradictory_flags(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan("users:update {$user->id} --admin --no-admin --force")
            ->expectsOutputToContain('contradict each other')
            ->assertExitCode(1);

        $this->assertFalse($user->refresh()->isAdmin());
    }

    public function test_it_refuses_an_email_another_account_already_uses(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);
        $user = User::factory()->create(['email' => 'grace@example.com']);

        $this->artisan("users:update {$user->id} --email=ADA@example.com --force")
            ->expectsOutputToContain('Another account already uses this email address.')
            ->assertExitCode(1);

        $this->assertSame('grace@example.com', $user->refresh()->email);
    }

    public function test_it_allows_an_account_to_keep_its_own_email(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com', 'name' => 'Ada']);

        $this->artisan("users:update {$user->id} --email=ADA@example.com --name=\"Ada Lovelace\" --force")
            ->assertExitCode(0);

        $user->refresh();

        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('Ada Lovelace', $user->name);
    }

    public function test_it_refuses_a_short_password_on_update(): void
    {
        $user = User::factory()->create();
        $original = $user->password;

        $this->artisan("users:update {$user->id} --password=short --force")
            ->assertExitCode(1);

        $this->assertSame($original, $user->refresh()->password);
    }
}
